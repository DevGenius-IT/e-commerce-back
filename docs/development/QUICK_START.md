# 🚀 DÉMARRAGE IMMÉDIAT : 0 → Kubernetes en 2h

## ⚡ Quick Start - Premier Déploiement

### **Prérequis (15 min)**
```bash
# 1. Vérifier votre cluster Kubernetes
kubectl cluster-info
kubectl get nodes
kubectl version --short

# 2. Vérifier les outils
helm version
docker version
git --version

# 3. Vérifier les ressources disponibles
kubectl top nodes
kubectl describe nodes | grep -A5 "Allocated resources"
```

### **Configuration Rapide (30 min)**

#### **Étape 1: Cloner et Configurer (5 min)**
```bash
cd /Users/kbrdn1/Projects/MNS/e-commerce-back

# Vérifier la structure
ls -la k8s/
ls -la helm/
ls -la docker/

# Rendre les scripts exécutables
chmod +x k8s/scripts/*.sh
chmod +x scripts/*.sh 2>/dev/null || echo "Scripts directory will be created"
```

#### **Étape 2: Configuration Secrets (10 min)**
```bash
# Script de configuration automatique des secrets
cat > scripts/quick-setup-secrets.sh << 'EOF'
#!/bin/bash
set -e

NAMESPACE=${1:-e-commerce-dev}

echo "🔑 Setting up secrets for $NAMESPACE..."

# Générer des secrets sécurisés si .env n'existe pas
if [ ! -f .env ]; then
  echo "📝 Creating .env file with secure defaults..."
  cat > .env << 'ENVEOF'
# JWT Configuration
JWT_SECRET=$(openssl rand -base64 32)

# Application
APP_KEY=base64:$(openssl rand -base64 32)

# Database
DB_ROOT_PASSWORD=$(openssl rand -base64 16)

# RabbitMQ
RABBITMQ_USER=admin
RABBITMQ_PASSWORD=$(openssl rand -base64 16)

# Redis
REDIS_PASSWORD=$(openssl rand -base64 16)
ENVEOF

  echo "✅ .env file created with secure passwords"
fi

# Lire les valeurs du .env
source .env

# Créer le namespace s'il n'existe pas
kubectl create namespace $NAMESPACE --dry-run=client -o yaml | kubectl apply -f -

# Créer les secrets Kubernetes
kubectl create secret generic global-secrets \
  --from-literal=JWT_SECRET="$JWT_SECRET" \
  --from-literal=APP_KEY="$APP_KEY" \
  --from-literal=DB_ROOT_PASSWORD="$DB_ROOT_PASSWORD" \
  --from-literal=RABBITMQ_USER="$RABBITMQ_USER" \
  --from-literal=RABBITMQ_PASSWORD="$RABBITMQ_PASSWORD" \
  --from-literal=REDIS_PASSWORD="$REDIS_PASSWORD" \
  --namespace=$NAMESPACE \
  --dry-run=client -o yaml | kubectl apply -f -

echo "✅ Secrets configured for $NAMESPACE"
echo "🔐 Passwords generated and stored in .env and Kubernetes secrets"
EOF

chmod +x scripts/quick-setup-secrets.sh
./scripts/quick-setup-secrets.sh
```

#### **Étape 3: Build et Push Images (15 min)**
```bash
# Script de build rapide (local ou registry)
cat > scripts/quick-build.sh << 'EOF'
#!/bin/bash
set -e

REGISTRY=${1:-localhost}
TAG=${2:-dev-$(date +%Y%m%d-%H%M)}

echo "🏗️ Quick build for development - Registry: $REGISTRY, Tag: $TAG"

# Services prioritaires pour le premier test
PRIORITY_SERVICES=("auth-service" "api-gateway" "products-service")

for service in "${PRIORITY_SERVICES[@]}"; do
  echo "Building $service..."
  
  # Build local rapide
  docker build \
    --build-arg SERVICE_NAME=$service \
    --build-arg BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ') \
    --build-arg VCS_REF=$(git rev-parse --short HEAD) \
    -t $REGISTRY/$service:$TAG \
    -t $REGISTRY/$service:latest \
    -f services/$service/Dockerfile . || {
    
    echo "⚠️ Dockerfile not found for $service, using generic template..."
    
    # Créer un Dockerfile temporaire basé sur le template
    cp docker/Dockerfile.microservice services/$service/Dockerfile.temp
    sed -i "s/\${SERVICE_NAME}/$service/g" services/$service/Dockerfile.temp
    
    docker build \
      --build-arg SERVICE_NAME=$service \
      -t $REGISTRY/$service:$TAG \
      -t $REGISTRY/$service:latest \
      -f services/$service/Dockerfile.temp .
    
    rm services/$service/Dockerfile.temp
  }
  
  echo "✅ $service image built"
done

echo "🎉 Priority services built successfully!"
echo "Images: ${PRIORITY_SERVICES[@]/#/$REGISTRY/}"
EOF

chmod +x scripts/quick-build.sh
./scripts/quick-build.sh
```

### **Déploiement Infrastructure (30 min)**

#### **Étape 4: Infrastructure de Base (15 min)**
```bash
echo "🏗️ Deploying base infrastructure..."

# 1. Déployer namespaces et configuration de base
kubectl apply -k k8s/overlays/development

# 2. Attendre que les namespaces soient prêts
kubectl wait --for=condition=Ready namespace/e-commerce-dev --timeout=60s
kubectl wait --for=condition=Ready namespace/e-commerce-messaging --timeout=60s
kubectl wait --for=condition=Ready namespace/monitoring --timeout=60s

# 3. Déployer Redis (rapide)
kubectl apply -f k8s/base/services/redis.yaml
kubectl wait --for=condition=Ready pod -l app=redis -n e-commerce-dev --timeout=180s

echo "✅ Base infrastructure deployed"
```

#### **Étape 5: Services Critiques (15 min)**
```bash
echo "🐰 Deploying critical services..."

# 1. RabbitMQ (version simple pour dev)
cat > temp-rabbitmq-simple.yaml << 'EOF'
apiVersion: apps/v1
kind: Deployment
metadata:
  name: rabbitmq
  namespace: e-commerce-messaging
spec:
  replicas: 1
  selector:
    matchLabels:
      app: rabbitmq
  template:
    metadata:
      labels:
        app: rabbitmq
    spec:
      containers:
      - name: rabbitmq
        image: rabbitmq:3.12-management
        ports:
        - containerPort: 5672
        - containerPort: 15672
        env:
        - name: RABBITMQ_DEFAULT_USER
          value: admin
        - name: RABBITMQ_DEFAULT_PASS
          valueFrom:
            secretKeyRef:
              name: global-secrets
              key: RABBITMQ_PASSWORD
---
apiVersion: v1
kind: Service
metadata:
  name: rabbitmq
  namespace: e-commerce-messaging
spec:
  selector:
    app: rabbitmq
  ports:
  - name: amqp
    port: 5672
    targetPort: 5672
  - name: management
    port: 15672
    targetPort: 15672
EOF

kubectl apply -f temp-rabbitmq-simple.yaml
kubectl wait --for=condition=Ready pod -l app=rabbitmq -n e-commerce-messaging --timeout=300s
rm temp-rabbitmq-simple.yaml

# 2. MySQL simple pour dev
cat > temp-mysql-simple.yaml << 'EOF'
apiVersion: apps/v1
kind: Deployment
metadata:
  name: mysql
  namespace: e-commerce-dev
spec:
  replicas: 1
  selector:
    matchLabels:
      app: mysql
  template:
    metadata:
      labels:
        app: mysql
    spec:
      containers:
      - name: mysql
        image: mysql:8.0
        ports:
        - containerPort: 3306
        env:
        - name: MYSQL_ROOT_PASSWORD
          valueFrom:
            secretKeyRef:
              name: global-secrets
              key: DB_ROOT_PASSWORD
        - name: MYSQL_DATABASE
          value: auth_service_db
        volumeMounts:
        - name: mysql-data
          mountPath: /var/lib/mysql
      volumes:
      - name: mysql-data
        emptyDir: {}
---
apiVersion: v1
kind: Service
metadata:
  name: mysql
  namespace: e-commerce-dev
spec:
  selector:
    app: mysql
  ports:
  - port: 3306
    targetPort: 3306
EOF

kubectl apply -f temp-mysql-simple.yaml
kubectl wait --for=condition=Ready pod -l app=mysql -n e-commerce-dev --timeout=300s
rm temp-mysql-simple.yaml

echo "✅ Critical services deployed"
```

### **Premier Service : Auth Service (30 min)**

#### **Étape 6: Déploiement Auth Service (20 min)**
```bash
echo "🔐 Deploying Auth Service..."

# 1. Créer le déploiement Auth Service
cat > temp-auth-deployment.yaml << 'EOF'
apiVersion: apps/v1
kind: Deployment
metadata:
  name: auth-service
  namespace: e-commerce-dev
  labels:
    app: auth-service
    component: microservice
spec:
  replicas: 1
  selector:
    matchLabels:
      app: auth-service
  template:
    metadata:
      labels:
        app: auth-service
        component: microservice
    spec:
      containers:
      - name: auth-service
        image: localhost/auth-service:latest
        imagePullPolicy: Never  # Pour dev local
        ports:
        - containerPort: 8000
          name: http
        env:
        - name: APP_NAME
          value: "Auth Service"
        - name: APP_ENV
          value: "development"
        - name: APP_DEBUG
          value: "true"
        - name: SERVICE_NAME
          value: "auth-service"
        
        # Database
        - name: DB_HOST
          value: "mysql.e-commerce-dev.svc.cluster.local"
        - name: DB_PORT
          value: "3306"
        - name: DB_DATABASE
          value: "auth_service_db"
        - name: DB_USERNAME
          value: "root"
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: global-secrets
              key: DB_ROOT_PASSWORD
        
        # JWT
        - name: JWT_SECRET
          valueFrom:
            secretKeyRef:
              name: global-secrets
              key: JWT_SECRET
        - name: APP_KEY
          valueFrom:
            secretKeyRef:
              name: global-secrets
              key: APP_KEY
        
        # RabbitMQ
        - name: RABBITMQ_HOST
          value: "rabbitmq.e-commerce-messaging.svc.cluster.local"
        - name: RABBITMQ_PORT
          value: "5672"
        - name: RABBITMQ_USER
          value: "admin"
        - name: RABBITMQ_PASSWORD
          valueFrom:
            secretKeyRef:
              name: global-secrets
              key: RABBITMQ_PASSWORD
        
        # Health checks
        livenessProbe:
          httpGet:
            path: /health
            port: 8000
          initialDelaySeconds: 60
          periodSeconds: 30
        readinessProbe:
          httpGet:
            path: /health
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
---
apiVersion: v1
kind: Service
metadata:
  name: auth-service
  namespace: e-commerce-dev
  labels:
    app: auth-service
spec:
  selector:
    app: auth-service
  ports:
  - port: 80
    targetPort: 8000
    name: http
  type: ClusterIP
EOF

kubectl apply -f temp-auth-deployment.yaml

# 2. Attendre que le service soit prêt
echo "⏳ Waiting for auth-service to be ready..."
kubectl wait --for=condition=Ready pod -l app=auth-service -n e-commerce-dev --timeout=300s

rm temp-auth-deployment.yaml
echo "✅ Auth Service deployed"
```

#### **Étape 7: Tests et Validation (10 min)**
```bash
echo "🧪 Testing Auth Service..."

# 1. Port-forward pour tester
kubectl port-forward svc/auth-service 8001:80 -n e-commerce-dev &
PF_PID=$!
sleep 10

# 2. Test health check
echo "Testing health endpoint..."
if curl -f http://localhost:8001/health; then
  echo "✅ Health check passed"
else
  echo "❌ Health check failed"
fi

# 3. Test base API
echo "Testing API endpoints..."
curl -X GET http://localhost:8001/api/health -H "Accept: application/json" || echo "API might need Laravel setup"

# 4. Arrêter port-forward
kill $PF_PID 2>/dev/null || true

echo "✅ Basic tests completed"
```

### **Dashboard et Monitoring (15 min)**

#### **Étape 8: Monitoring de Base (15 min)**
```bash
echo "📊 Setting up basic monitoring..."

# 1. Déployer Grafana standalone pour démarrer
cat > temp-grafana.yaml << 'EOF'
apiVersion: apps/v1
kind: Deployment
metadata:
  name: grafana
  namespace: monitoring
spec:
  replicas: 1
  selector:
    matchLabels:
      app: grafana
  template:
    metadata:
      labels:
        app: grafana
    spec:
      containers:
      - name: grafana
        image: grafana/grafana:latest
        ports:
        - containerPort: 3000
        env:
        - name: GF_SECURITY_ADMIN_PASSWORD
          value: admin
        volumeMounts:
        - name: grafana-data
          mountPath: /var/lib/grafana
      volumes:
      - name: grafana-data
        emptyDir: {}
---
apiVersion: v1
kind: Service
metadata:
  name: grafana
  namespace: monitoring
spec:
  selector:
    app: grafana
  ports:
  - port: 3000
    targetPort: 3000
  type: ClusterIP
EOF

kubectl apply -f temp-grafana.yaml
kubectl wait --for=condition=Ready pod -l app=grafana -n monitoring --timeout=180s
rm temp-grafana.yaml

# 2. Accès Grafana
echo "📊 Grafana deployed! Access with:"
echo "kubectl port-forward svc/grafana 3000:3000 -n monitoring"
echo "Then go to http://localhost:3000 (admin/admin)"

echo "✅ Basic monitoring ready"
```

### **Validation Finale (15 min)**

#### **Étape 9: Status Check Complet**
```bash
echo "🔍 Final status check..."

cat > scripts/status-check.sh << 'EOF'
#!/bin/bash

echo "🏥 E-commerce Platform Health Check"
echo "=================================="

# Namespaces
echo "📁 Namespaces:"
kubectl get namespaces | grep -E "(e-commerce|monitoring)"

# Pods status
echo ""
echo "🏗️ Infrastructure Pods:"
kubectl get pods -n e-commerce-dev
kubectl get pods -n e-commerce-messaging
kubectl get pods -n monitoring

# Services
echo ""
echo "🌐 Services:"
kubectl get svc -n e-commerce-dev
kubectl get svc -n e-commerce-messaging
kubectl get svc -n monitoring

# Resource usage
echo ""
echo "📊 Resource Usage:"
kubectl top nodes 2>/dev/null || echo "Metrics not available yet"
kubectl top pods -n e-commerce-dev 2>/dev/null || echo "Pod metrics not available yet"

# Quick connectivity test
echo ""
echo "🔗 Connectivity Tests:"
kubectl exec -n e-commerce-dev deployment/auth-service -- curl -f http://mysql:3306 &>/dev/null && echo "✅ Auth → MySQL: OK" || echo "❌ Auth → MySQL: Failed"
kubectl exec -n e-commerce-dev deployment/auth-service -- nc -zv rabbitmq.e-commerce-messaging.svc.cluster.local 5672 &>/dev/null && echo "✅ Auth → RabbitMQ: OK" || echo "❌ Auth → RabbitMQ: Failed"

echo ""
echo "🎉 Status check completed!"
echo ""
echo "🚀 Next steps:"
echo "1. Access Grafana: kubectl port-forward svc/grafana 3000:3000 -n monitoring"
echo "2. Access Auth Service: kubectl port-forward svc/auth-service 8001:80 -n e-commerce-dev"
echo "3. Check logs: kubectl logs -f deployment/auth-service -n e-commerce-dev"
echo "4. Deploy more services: ./k8s/scripts/deploy.sh deploy development"
EOF

chmod +x scripts/status-check.sh
./scripts/status-check.sh
```

## 🎯 **RÉSULTAT : Plateforme Opérationnelle en 2h !**

### **Ce qui fonctionne maintenant :**
- ✅ **Kubernetes cluster** configuré et opérationnel
- ✅ **Auth Service** déployé et accessible
- ✅ **MySQL** base de données fonctionnelle
- ✅ **RabbitMQ** messaging prêt
- ✅ **Redis** cache disponible
- ✅ **Grafana** monitoring basique
- ✅ **Secrets** sécurisés et automatiques

### **Accès aux Services :**
```bash
# Auth Service API
kubectl port-forward svc/auth-service 8001:80 -n e-commerce-dev
curl http://localhost:8001/health

# Grafana Dashboard
kubectl port-forward svc/grafana 3000:3000 -n monitoring
# http://localhost:3000 (admin/admin)

# RabbitMQ Management
kubectl port-forward svc/rabbitmq 15672:15672 -n e-commerce-messaging
# http://localhost:15672 (admin/[password from secret])

# MySQL Database
kubectl port-forward svc/mysql 3306:3306 -n e-commerce-dev
# Connection available on localhost:3306
```

### **Prochaines Actions Immédiates :**
1. **Tester Auth Service** : Créer un utilisateur, login, JWT
2. **Déployer API Gateway** : Point d'entrée centralisé
3. **Ajouter Products Service** : Service métier principal
4. **Configurer monitoring avancé** : Prometheus + dashboards

**🚀 Votre plateforme e-commerce Kubernetes est LIVE et opérationnelle !**

Voulez-vous que je vous guide pour les prochaines étapes spécifiques ?