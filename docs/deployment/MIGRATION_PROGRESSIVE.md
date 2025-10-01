# 🔄 Migration Progressive : Docker Compose → Kubernetes

## 🎯 Stratégie de Migration Blue-Green

### Phase 1 : Préparation et Infrastructure (Semaine 1)

#### **1.1 Déploiement Infrastructure Kubernetes**
```bash
# 1. Déployer l'infrastructure de base
kubectl apply -k k8s/overlays/staging

# 2. Vérifier que tous les services d'infrastructure sont up
kubectl get pods -n e-commerce-staging
kubectl get pods -n e-commerce-messaging  
kubectl get pods -n monitoring

# 3. Tester la connectivité
./k8s/scripts/deploy.sh verify staging
```

#### **1.2 Build et Push des Images**
```bash
# Build des images pour tous les services
for service in api-gateway auth-service products-service baskets-service orders-service; do
  docker build -t ghcr.io/yourorg/$service:v1.0.0 \
    -f services/$service/Dockerfile .
  docker push ghcr.io/yourorg/$service:v1.0.0
done

# Test local des images
docker run --rm ghcr.io/yourorg/auth-service:v1.0.0 php artisan --version
```

#### **1.3 Configuration des Secrets**
```bash
# Créer les secrets Kubernetes avec les mêmes valeurs que Docker Compose
kubectl create secret generic global-secrets \
  --from-literal=JWT_SECRET="$(grep JWT_SECRET .env | cut -d'=' -f2)" \
  --from-literal=DB_ROOT_PASSWORD="$(grep DB_ROOT_PASSWORD .env | cut -d'=' -f2)" \
  -n e-commerce-staging

# Vérifier les secrets
kubectl get secrets -n e-commerce-staging
```

### Phase 2 : Migration Service par Service (Semaines 2-4)

#### **2.1 Service 1: Messages Broker (Jour 1-2)**
```bash
# Messages Broker est critique - le migrer en premier
echo "🐰 Migrating RabbitMQ..."

# 1. Déployer RabbitMQ cluster en K8s
kubectl apply -f k8s/manifests/messaging/rabbitmq-cluster.yaml

# 2. Attendre que le cluster soit prêt
kubectl wait --for=condition=Ready pod -l app.kubernetes.io/name=rabbitmq-cluster -n e-commerce-messaging --timeout=300s

# 3. Migrer les données (si nécessaire)
./scripts/migrate-rabbitmq-data.sh

# 4. Tester la connectivité
kubectl port-forward svc/rabbitmq-management 15672:15672 -n e-commerce-messaging &
curl -u admin:password http://localhost:15672/api/queues

# 5. Modifier la configuration Docker Compose pour pointer vers K8s
# Dans .env:
RABBITMQ_HOST=<k8s-cluster-ip>
RABBITMQ_PORT=30672  # NodePort ou LoadBalancer

# 6. Redémarrer les services Docker
docker-compose restart

echo "✅ RabbitMQ migration completed"
```

#### **2.2 Service 2: Auth Service (Jour 3-5)**
```bash
echo "🔐 Migrating Auth Service..."

# 1. Exporter les données de base
docker-compose exec auth-service mysqldump -h auth-db -u root -p auth_service_db > auth_backup.sql

# 2. Déployer Auth Service en K8s
helm upgrade --install auth-service-staging ./helm \
  --namespace e-commerce-staging \
  --set services.auth-service.enabled=true \
  --set global.imageTag=v1.0.0 \
  --wait

# 3. Importer les données
kubectl exec -i mysql-cluster-0 -n e-commerce-staging -- mysql -u root -p auth_service_db < auth_backup.sql

# 4. Test de fonctionnement
kubectl port-forward svc/auth-service 8001:80 -n e-commerce-staging &

# Test JWT
curl -X POST http://localhost:8001/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'

# 5. Configurer la redirection progressive (Load Balancer)
# 10% du trafic vers K8s, 90% vers Docker
./scripts/configure-traffic-split.sh auth 10 90

# 6. Monitorer pendant 24h
kubectl logs -f deployment/auth-service -n e-commerce-staging

echo "✅ Auth Service migration completed"
```

#### **2.3 Service 3: Products Service (Jour 6-8)**
```bash
echo "📦 Migrating Products Service..."

# 1. Synchroniser les images produits
kubectl exec -it products-service-pod -n e-commerce-staging -- \
  php artisan products:sync-images --from-docker

# 2. Déployer Products Service
helm upgrade --install products-service-staging ./helm \
  --namespace e-commerce-staging \
  --set services.products-service.enabled=true \
  --set global.imageTag=v1.0.0 \
  --wait

# 3. Migrer la base de données
./scripts/migrate-service-db.sh products-service

# 4. Test des endpoints critiques
curl http://localhost:8003/api/products/health
curl http://localhost:8003/api/products?limit=10

# 5. Redirection 25% vers K8s
./scripts/configure-traffic-split.sh products 25 75

echo "✅ Products Service migration completed"
```

#### **2.4 Services 4-6: Baskets, Orders, Addresses (Jour 9-15)**
```bash
# Migration en parallèle des services business
for service in baskets-service orders-service addresses-service; do
  echo "🛒 Migrating $service..."
  
  # Déploiement
  helm upgrade --install $service-staging ./helm \
    --namespace e-commerce-staging \
    --set services.$service.enabled=true \
    --set global.imageTag=v1.0.0 \
    --wait
  
  # Migration DB
  ./scripts/migrate-service-db.sh $service
  
  # Tests
  kubectl port-forward svc/$service 808X:80 -n e-commerce-staging &
  ./scripts/test-service.sh $service
  
  # Traffic split progressif : 50%
  ./scripts/configure-traffic-split.sh $service 50 50
  
  echo "✅ $service migration completed"
done
```

#### **2.5 Services 7-13: Autres Services (Jour 16-21)**
```bash
# Migration des services de support
services=(deliveries-service newsletters-service sav-service questions-service contacts-service websites-service)

for service in "${services[@]}"; do
  echo "🔧 Migrating $service..."
  
  # Déploiement simplifié (moins critique)
  kubectl apply -k k8s/overlays/staging
  
  # Tests basiques
  ./scripts/test-service.sh $service
  
  # Migration complète en une fois (100%)
  ./scripts/configure-traffic-split.sh $service 100 0
  
  echo "✅ $service migration completed"
done
```

#### **2.6 Service Final: API Gateway (Jour 22-23)**
```bash
echo "🚪 Migrating API Gateway (Final Step)..."

# 1. Déployer API Gateway en K8s
helm upgrade --install api-gateway-staging ./helm \
  --namespace e-commerce-staging \
  --set services.api-gateway.enabled=true \
  --set global.imageTag=v1.0.0 \
  --wait

# 2. Configuration Nginx pour router vers K8s
kubectl apply -f k8s/manifests/gateway/ingress.yaml

# 3. Tests complets end-to-end
./scripts/e2e-tests.sh

# 4. Basculement DNS progressif
# 10% → 25% → 50% → 100%
for percentage in 10 25 50 100; do
  ./scripts/configure-dns-split.sh $percentage
  echo "Traffic at ${percentage}%, monitoring for 2h..."
  sleep 7200  # 2 heures
  ./scripts/check-health.sh
done

echo "✅ API Gateway migration completed"
echo "🎉 ALL SERVICES MIGRATED TO KUBERNETES!"
```

### Phase 3 : Validation et Nettoyage (Semaine 5)

#### **3.1 Tests de Performance**
```bash
# Tests de charge sur K8s
echo "🔥 Running load tests..."

# Test API Gateway
ab -n 10000 -c 100 http://api-staging.yourcompany.com/api/health

# Test Auth Service
ab -n 5000 -c 50 http://api-staging.yourcompany.com/api/auth/validate

# Test Products
ab -n 8000 -c 80 http://api-staging.yourcompany.com/api/products

echo "✅ Load tests completed"
```

#### **3.2 Monitoring et Alerting**
```bash
# Vérifier que le monitoring fonctionne
kubectl port-forward svc/grafana 3000:3000 -n monitoring &
echo "📊 Grafana available at http://localhost:3000"

# Vérifier les métriques
kubectl port-forward svc/prometheus 9090:9090 -n monitoring &
echo "📈 Prometheus available at http://localhost:9090"

# Tests des alertes
./scripts/test-alerts.sh
```

#### **3.3 Cleanup Docker Compose**
```bash
# Une fois tout validé en K8s, arrêter Docker Compose
echo "🧹 Cleaning up Docker Compose..."

# Backup final
docker-compose exec mysql mysqldump --all-databases > final_backup.sql

# Arrêt des services
docker-compose down

# Archive des logs
tar -czf docker-logs-archive.tar.gz logs/

# Nettoyage des volumes (ATTENTION!)
# docker volume prune  # À faire manuellement après validation

echo "✅ Docker Compose cleanup completed"
```

## 🔧 Scripts de Migration

### **Script Principal de Migration**
```bash
# scripts/migrate-progressive.sh
#!/bin/bash

PHASE=${1:-1}
SERVICE=${2:-all}

case $PHASE in
  1)
    echo "🏗️ Phase 1: Infrastructure Setup"
    ./scripts/setup-infrastructure.sh
    ;;
  2)
    echo "🔄 Phase 2: Service Migration"
    ./scripts/migrate-services.sh $SERVICE
    ;;
  3)
    echo "✅ Phase 3: Validation & Cleanup"
    ./scripts/validate-and-cleanup.sh
    ;;
  *)
    echo "Usage: $0 {1|2|3} [service-name]"
    exit 1
    ;;
esac
```

### **Script de Test de Service**
```bash
# scripts/test-service.sh
#!/bin/bash

SERVICE=$1
NAMESPACE=${2:-e-commerce-staging}

echo "🧪 Testing $SERVICE..."

# Health check
kubectl exec deployment/$SERVICE -n $NAMESPACE -- curl -f http://localhost:8000/health

# Database connectivity
kubectl exec deployment/$SERVICE -n $NAMESPACE -- php artisan migrate:status

# RabbitMQ connectivity  
kubectl exec deployment/$SERVICE -n $NAMESPACE -- php artisan rabbitmq:test

# Performance test
kubectl exec deployment/$SERVICE -n $NAMESPACE -- ab -n 100 -c 10 http://localhost:8000/api/health

echo "✅ $SERVICE tests completed"
```

### **Script de Configuration Traffic Split**
```bash
# scripts/configure-traffic-split.sh
#!/bin/bash

SERVICE=$1
K8S_PERCENTAGE=$2
DOCKER_PERCENTAGE=$3

echo "🔀 Configuring traffic split for $SERVICE: ${K8S_PERCENTAGE}% K8s, ${DOCKER_PERCENTAGE}% Docker"

# Configuration Traefik ou Load Balancer
cat <<EOF | kubectl apply -f -
apiVersion: traefik.containo.us/v1alpha1
kind: TraefikService
metadata:
  name: ${SERVICE}-weighted
  namespace: e-commerce-staging
spec:
  weighted:
    services:
    - name: ${SERVICE}-k8s
      weight: ${K8S_PERCENTAGE}
    - name: ${SERVICE}-docker
      weight: ${DOCKER_PERCENTAGE}
EOF

echo "✅ Traffic split configured"
```

## 📊 Monitoring de Migration

### **Dashboard Migration**
```yaml
# Métriques à surveiller pendant la migration
migration_metrics:
  error_rate: "< 0.1%"
  response_time_p95: "< 500ms"  
  availability: "> 99.9%"
  database_connections: "stable"
  memory_usage: "< 80%"
  cpu_usage: "< 70%"
```

### **Alertes Critiques**
```yaml
migration_alerts:
  - name: "Service Down"
    condition: "up == 0"
    severity: "critical"
    
  - name: "High Error Rate"
    condition: "error_rate > 1%"
    severity: "warning"
    
  - name: "Response Time Degradation"
    condition: "response_time_p95 > 1000ms"
    severity: "warning"
```

## 🚨 Plan de Rollback

### **Rollback Complet**
```bash
# En cas de problème critique
echo "🚨 EMERGENCY ROLLBACK"

# 1. Rediriger tout le trafic vers Docker Compose
./scripts/configure-traffic-split.sh all 0 100

# 2. Redémarrer Docker Compose
docker-compose up -d

# 3. Vérifier que tout fonctionne
./scripts/test-docker-services.sh

# 4. Investiguer les problèmes K8s
kubectl get events -n e-commerce-staging --sort-by='.lastTimestamp'

echo "✅ Rollback completed"
```

### **Rollback Sélectif**
```bash
# Rollback d'un service spécifique
SERVICE=$1
./scripts/configure-traffic-split.sh $SERVICE 0 100
echo "✅ $SERVICE traffic redirected to Docker"
```

## 🎯 Critères de Succès

### **Métriques Techniques**
- ✅ **Availability**: 99.9% pendant la migration
- ✅ **Performance**: Pas de dégradation > 10%
- ✅ **Error Rate**: < 0.1% erreurs
- ✅ **Data Integrity**: 0 perte de données

### **Métriques Business**
- ✅ **Commandes**: Aucune commande perdue
- ✅ **Authentification**: Aucune déconnexion utilisateur
- ✅ **Paiements**: Aucune transaction échouée
- ✅ **Recherche**: Fonctionnalité maintenue

### **Timeline**
- ✅ **Semaine 1**: Infrastructure ready
- ✅ **Semaine 2-4**: Migration progressive 
- ✅ **Semaine 5**: Validation et cleanup
- ✅ **Total**: 5 semaines maximum

Cette stratégie de migration progressive garantit une transition sans interruption de service tout en validant chaque étape ! 🚀