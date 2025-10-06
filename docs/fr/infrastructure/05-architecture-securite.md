# Architecture Securite - Plateforme E-Commerce Microservices

## Table des Matieres

1. [Vue d'Ensemble Securite](#vue-densemble-securite)
2. [Diagramme Securite Multi-Couches](#diagramme-securite-multi-couches)
3. [Gestion Secrets](#gestion-secrets)
4. [RBAC Kubernetes](#rbac-kubernetes)
5. [Politiques Reseau](#politiques-reseau)
6. [Standards Securite Pod](#standards-securite-pod)
7. [Securite Images](#securite-images)
8. [Gestion TLS/SSL](#gestion-tlsssl)
9. [Journalisation Audit](#journalisation-audit)
10. [Conformite](#conformite)
11. [Gestion Vulnerabilites](#gestion-vulnerabilites)
12. [Reponse Incidents](#reponse-incidents)

---

## Vue d'Ensemble Securite

### Defense en Profondeur

La plateforme implemente une strategie de securite multi-couches ou chaque couche fournit une protection independante :

**Couches Securite :**
- **Couche Perimetre** : Pare-feu, protection DDoS, CDN
- **Couche Ingress** : Terminaison TLS, Web Application Firewall (WAF)
- **Couche Reseau** : Politiques Reseau, service mesh avec mTLS
- **Couche Application** : Authentification JWT, RBAC, validation entrees
- **Couche Donnees** : Chiffrement au repos, sauvegardes chiffrees

### Architecture Zero-Trust

**Principes :**
- Ne jamais faire confiance, toujours verifier
- Mentalite supposer violation
- Acces privilege minimum
- Micro-segmentation
- Verification continue

**Implementation :**
- Pas confiance implicite entre services
- Toute communication service-a-service authentifiee
- Politiques reseau imposent segmentation
- Controle acces base identite

### Principe Moindre Privilege

**Applique a :**
- ServiceAccounts : Permissions minimales par service
- RBAC : Acces specifique role uniquement
- Politiques Reseau : Seul trafic requis autorise
- Securite Conteneur : Utilisateurs non-root, systemes fichiers lecture seule
- Acces Secrets : Uniquement services qui en ont besoin

---

## Diagramme Securite Multi-Couches

```mermaid
graph TB
    subgraph "Couche 1: Securite Perimetre"
        CDN[CDN / Protection DDoS]
        FW[Pare-feu Cloud]
        LB[Load Balancer]
    end

    subgraph "Couche 2: Securite Ingress"
        INGRESS[Controleur Ingress Nginx]
        WAF[Web Application Firewall]
        TLS[Terminaison TLS]
        CERT[Cert-Manager]
    end

    subgraph "Couche 3: Securite Reseau"
        NP[Politiques Reseau]
        MESH[Service Mesh - mTLS]
        DNS[CoreDNS]
    end

    subgraph "Couche 4: Securite Application"
        AUTH[Service Auth - JWT]
        RBAC[RBAC / Permissions]
        VAL[Validation Entrees]
        GATEWAY[Passerelle API]
    end

    subgraph "Couche 5: Securite Donnees"
        ENC[Chiffrement au Repos]
        BACKUP[Sauvegardes Chiffrees]
        VAULT[Coffre-fort Secrets]
        DB[(Bases Donnees Chiffrees)]
    end

    subgraph "Couche 6: Securite Runtime"
        POD[Politiques Securite Pod]
        SCAN[Scanning Conteneurs]
        AUDIT[Journalisation Audit]
        SIEM[Integration SIEM]
    end

    CDN --> FW
    FW --> LB
    LB --> INGRESS
    INGRESS --> WAF
    INGRESS --> TLS
    TLS --> CERT
    WAF --> NP
    NP --> MESH
    MESH --> GATEWAY
    GATEWAY --> AUTH
    AUTH --> RBAC
    RBAC --> VAL
    VAL --> ENC
    ENC --> DB
    VAULT --> AUTH
    VAULT --> DB
    POD -.surveille.-> MESH
    SCAN -.valide.-> POD
    AUDIT -.journalise.-> SIEM
```

### Flux Securite

```mermaid
sequenceDiagram
    participant Client
    participant CDN
    participant Ingress
    participant Gateway
    participant AuthService
    participant Microservice
    participant Database

    Client->>CDN: Requete HTTPS
    CDN->>CDN: Verification DDoS
    CDN->>Ingress: Transmettre si valide
    Ingress->>Ingress: Terminaison TLS
    Ingress->>Ingress: Verification Regles WAF
    Ingress->>Gateway: Router vers Gateway
    Gateway->>AuthService: Valider JWT
    AuthService->>AuthService: Verifier Token + RBAC
    AuthService-->>Gateway: Resultat Auth
    Gateway->>Microservice: Requete Autorisee (mTLS)
    Microservice->>Microservice: Validation Entrees
    Microservice->>Database: Requete (Connexion Chiffree)
    Database-->>Microservice: Donnees Chiffrees
    Microservice->>Microservice: Assainissement Reponse
    Microservice-->>Gateway: Reponse
    Gateway-->>Ingress: Reponse
    Ingress-->>CDN: Reponse HTTPS
    CDN-->>Client: Reponse Securisee
```

---

## Gestion Secrets

### Architecture Vue d'Ensemble

```mermaid
graph LR
    subgraph "Secrets Externes"
        VAULT[HashiCorp Vault]
        AWS[AWS Secrets Manager]
        GCP[GCP Secret Manager]
    end

    subgraph "Cluster Kubernetes"
        ESO[Operateur External Secrets]
        SS[Sealed Secrets]
        K8S_SECRET[Secrets Kubernetes]
    end

    subgraph "Applications"
        POD1[Service Auth]
        POD2[Service Orders]
        POD3[Service Products]
    end

    VAULT --> ESO
    AWS --> ESO
    GCP --> ESO
    ESO --> K8S_SECRET
    SS --> K8S_SECRET
    K8S_SECRET --> POD1
    K8S_SECRET --> POD2
    K8S_SECRET --> POD3
```

### Operateur External Secrets

**Installation :**

```yaml
# external-secrets-operator.yaml
apiVersion: v1
kind: Namespace
metadata:
  name: external-secrets
---
apiVersion: helm.cattle.io/v1
kind: HelmChart
metadata:
  name: external-secrets
  namespace: kube-system
spec:
  chart: external-secrets
  repo: https://charts.external-secrets.io
  targetNamespace: external-secrets
  valuesContent: |-
    installCRDs: true
    webhook:
      port: 9443
```

**Configuration SecretStore (HashiCorp Vault) :**

```yaml
# vault-secret-store.yaml
apiVersion: external-secrets.io/v1beta1
kind: SecretStore
metadata:
  name: vault-backend
  namespace: e-commerce
spec:
  provider:
    vault:
      server: "https://vault.example.com"
      path: "secret"
      version: "v2"
      auth:
        kubernetes:
          mountPath: "kubernetes"
          role: "e-commerce-role"
          serviceAccountRef:
            name: external-secrets-sa
```

**Ressource ExternalSecret :**

```yaml
# database-credentials-external-secret.yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: mysql-credentials
  namespace: e-commerce
spec:
  refreshInterval: 1h
  secretStoreRef:
    name: vault-backend
    kind: SecretStore
  target:
    name: mysql-secret
    creationPolicy: Owner
  data:
  - secretKey: username
    remoteRef:
      key: database/mysql
      property: username
  - secretKey: password
    remoteRef:
      key: database/mysql
      property: password
  - secretKey: root-password
    remoteRef:
      key: database/mysql
      property: root-password
```

### Integration AWS Secrets Manager

```yaml
# aws-secrets-manager-store.yaml
apiVersion: external-secrets.io/v1beta1
kind: SecretStore
metadata:
  name: aws-secrets-manager
  namespace: e-commerce
spec:
  provider:
    aws:
      service: SecretsManager
      region: us-east-1
      auth:
        jwt:
          serviceAccountRef:
            name: external-secrets-sa
---
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: jwt-secret
  namespace: e-commerce
spec:
  refreshInterval: 12h
  secretStoreRef:
    name: aws-secrets-manager
    kind: SecretStore
  target:
    name: jwt-secret
    creationPolicy: Owner
  data:
  - secretKey: JWT_SECRET
    remoteRef:
      key: /e-commerce/production/jwt-secret
      property: secret
```

### Sealed Secrets (Bitnami)

**Installation Controleur :**

```bash
# Installer controleur sealed-secrets
kubectl apply -f https://github.com/bitnami-labs/sealed-secrets/releases/download/v0.24.0/controller.yaml

# Installer CLI kubeseal
wget https://github.com/bitnami-labs/sealed-secrets/releases/download/v0.24.0/kubeseal-0.24.0-linux-amd64.tar.gz
tar -xvzf kubeseal-0.24.0-linux-amd64.tar.gz
sudo install -m 755 kubeseal /usr/local/bin/kubeseal
```

**Creation Sealed Secrets :**

```bash
# Creer fichier secret regulier
kubectl create secret generic rabbitmq-credentials \
  --from-literal=username=ecommerce \
  --from-literal=password=SecurePass123! \
  --dry-run=client -o yaml > rabbitmq-secret.yaml

# Sceller secret
kubeseal --format=yaml < rabbitmq-secret.yaml > rabbitmq-sealed-secret.yaml

# Appliquer sealed secret (sur pour commit git)
kubectl apply -f rabbitmq-sealed-secret.yaml
```

**Exemple Sealed Secret :**

```yaml
# rabbitmq-sealed-secret.yaml
apiVersion: bitnami.com/v1alpha1
kind: SealedSecret
metadata:
  name: rabbitmq-credentials
  namespace: e-commerce
spec:
  encryptedData:
    username: AgBxK8jP... (chiffre)
    password: AgCmQw9... (chiffre)
  template:
    metadata:
      name: rabbitmq-credentials
      namespace: e-commerce
    type: Opaque
```

### Rotation Secrets

**Rotation Automatisee avec External Secrets :**

```yaml
# auto-rotate-secret.yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: database-password
  namespace: e-commerce
  annotations:
    reloader.stakater.com/match: "true"
spec:
  refreshInterval: 24h  # Verifier mises a jour toutes 24h
  secretStoreRef:
    name: vault-backend
    kind: SecretStore
  target:
    name: db-password
    creationPolicy: Owner
    template:
      type: Opaque
      metadata:
        annotations:
          reloader.stakater.com/match: "true"
  data:
  - secretKey: password
    remoteRef:
      key: database/orders-db
      property: password
```

**Reloader pour Redemarrage Pod Automatique :**

```yaml
# reloader-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: orders-service
  namespace: e-commerce
  annotations:
    reloader.stakater.com/auto: "true"  # Auto-reload changement secret
spec:
  template:
    spec:
      containers:
      - name: orders-service
        image: orders-service:latest
        envFrom:
        - secretRef:
            name: db-password
```

---

## RBAC Kubernetes

### ServiceAccounts par Service

```yaml
# service-accounts.yaml
---
apiVersion: v1
kind: ServiceAccount
metadata:
  name: auth-service-sa
  namespace: e-commerce
---
apiVersion: v1
kind: ServiceAccount
metadata:
  name: orders-service-sa
  namespace: e-commerce
---
apiVersion: v1
kind: ServiceAccount
metadata:
  name: products-service-sa
  namespace: e-commerce
---
apiVersion: v1
kind: ServiceAccount
metadata:
  name: gateway-sa
  namespace: e-commerce
```

### Roles et ClusterRoles

**Role Specifique Namespace :**

```yaml
# orders-service-role.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: orders-service-role
  namespace: e-commerce
rules:
# Lire ConfigMaps
- apiGroups: [""]
  resources: ["configmaps"]
  verbs: ["get", "list", "watch"]
# Lire Secrets (uniquement specifiques)
- apiGroups: [""]
  resources: ["secrets"]
  resourceNames: ["orders-db-secret", "rabbitmq-credentials"]
  verbs: ["get"]
# Lire propre Service
- apiGroups: [""]
  resources: ["services"]
  resourceNames: ["orders-service"]
  verbs: ["get"]
```

**ClusterRole pour Operateur External Secrets :**

```yaml
# external-secrets-cluster-role.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: external-secrets-operator
rules:
- apiGroups: [""]
  resources: ["secrets"]
  verbs: ["get", "list", "watch", "create", "update", "patch", "delete"]
- apiGroups: ["external-secrets.io"]
  resources: ["externalsecrets", "secretstores", "clustersecretstores"]
  verbs: ["get", "list", "watch", "create", "update", "patch", "delete"]
- apiGroups: [""]
  resources: ["events"]
  verbs: ["create", "patch"]
```

### RoleBindings

```yaml
# orders-service-rolebinding.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: orders-service-binding
  namespace: e-commerce
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: Role
  name: orders-service-role
subjects:
- kind: ServiceAccount
  name: orders-service-sa
  namespace: e-commerce
```

**ClusterRoleBinding :**

```yaml
# external-secrets-cluster-rolebinding.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRoleBinding
metadata:
  name: external-secrets-operator-binding
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: ClusterRole
  name: external-secrets-operator
subjects:
- kind: ServiceAccount
  name: external-secrets-sa
  namespace: external-secrets
```

### Exemple Permissions Minimales

```yaml
# gateway-minimal-permissions.yaml
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: gateway-role
  namespace: e-commerce
rules:
# Lire uniquement sa propre configuration
- apiGroups: [""]
  resources: ["configmaps"]
  resourceNames: ["gateway-config"]
  verbs: ["get", "watch"]
# Lire uniquement secret JWT
- apiGroups: [""]
  resources: ["secrets"]
  resourceNames: ["jwt-secret"]
  verbs: ["get"]
# Decouvrir services pour routage
- apiGroups: [""]
  resources: ["services", "endpoints"]
  verbs: ["get", "list", "watch"]
```

---

## Politiques Reseau

### Deny All par Defaut

```yaml
# default-deny-all.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: default-deny-all
  namespace: e-commerce
spec:
  podSelector: {}  # S'applique tous pods namespace
  policyTypes:
  - Ingress
  - Egress
```

### Approche Whitelist - Passerelle API

```yaml
# gateway-network-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: gateway-network-policy
  namespace: e-commerce
spec:
  podSelector:
    matchLabels:
      app: api-gateway
  policyTypes:
  - Ingress
  - Egress

  # Ingress: Accepter uniquement Controleur Ingress
  ingress:
  - from:
    - namespaceSelector:
        matchLabels:
          name: ingress-nginx
    - podSelector:
        matchLabels:
          app.kubernetes.io/name: ingress-nginx
    ports:
    - protocol: TCP
      port: 8100

  # Egress: Autoriser vers microservices et RabbitMQ
  egress:
  # Resolution DNS
  - to:
    - namespaceSelector:
        matchLabels:
          name: kube-system
    - podSelector:
        matchLabels:
          k8s-app: kube-dns
    ports:
    - protocol: UDP
      port: 53

  # RabbitMQ
  - to:
    - podSelector:
        matchLabels:
          app: rabbitmq
    ports:
    - protocol: TCP
      port: 5672

  # Tous microservices
  - to:
    - podSelector:
        matchLabels:
          tier: backend
    ports:
    - protocol: TCP
      port: 9000
```

### Politique Reseau Microservice

```yaml
# orders-service-network-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: orders-service-network-policy
  namespace: e-commerce
spec:
  podSelector:
    matchLabels:
      app: orders-service
  policyTypes:
  - Ingress
  - Egress

  # Ingress: Uniquement depuis Gateway
  ingress:
  - from:
    - podSelector:
        matchLabels:
          app: api-gateway
    ports:
    - protocol: TCP
      port: 9000

  # Regles Egress
  egress:
  # DNS
  - to:
    - namespaceSelector:
        matchLabels:
          name: kube-system
    ports:
    - protocol: UDP
      port: 53

  # Base donnees MySQL
  - to:
    - podSelector:
        matchLabels:
          app: orders-mysql
    ports:
    - protocol: TCP
      port: 3306

  # RabbitMQ
  - to:
    - podSelector:
        matchLabels:
          app: rabbitmq
    ports:
    - protocol: TCP
      port: 5672

  # MinIO (pour uploads fichiers si besoin)
  - to:
    - podSelector:
        matchLabels:
          app: minio
    ports:
    - protocol: TCP
      port: 9000
```

### Politique Reseau Base Donnees

```yaml
# mysql-network-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: mysql-network-policy
  namespace: e-commerce
spec:
  podSelector:
    matchLabels:
      app: orders-mysql
  policyTypes:
  - Ingress
  - Egress

  # Ingress: Uniquement depuis orders-service
  ingress:
  - from:
    - podSelector:
        matchLabels:
          app: orders-service
    ports:
    - protocol: TCP
      port: 3306

  # Egress: DNS uniquement
  egress:
  - to:
    - namespaceSelector:
        matchLabels:
          name: kube-system
    ports:
    - protocol: UDP
      port: 53
```

### Politique Reseau RabbitMQ

```yaml
# rabbitmq-network-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: rabbitmq-network-policy
  namespace: e-commerce
spec:
  podSelector:
    matchLabels:
      app: rabbitmq
  policyTypes:
  - Ingress
  - Egress

  # Ingress: Tous services backend
  ingress:
  # AMQP de tous services backend
  - from:
    - podSelector:
        matchLabels:
          tier: backend
    ports:
    - protocol: TCP
      port: 5672

  # UI Management (restreint namespace monitoring)
  - from:
    - namespaceSelector:
        matchLabels:
          name: monitoring
    ports:
    - protocol: TCP
      port: 15672

  # Egress: DNS uniquement
  egress:
  - to:
    - namespaceSelector:
        matchLabels:
          name: kube-system
    ports:
    - protocol: UDP
      port: 53
```

### Controles Egress - APIs Externes

```yaml
# external-api-egress-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: external-api-egress
  namespace: e-commerce
spec:
  podSelector:
    matchLabels:
      app: payments-service
  policyTypes:
  - Egress

  egress:
  # DNS
  - to:
    - namespaceSelector:
        matchLabels:
          name: kube-system
    ports:
    - protocol: UDP
      port: 53

  # Autoriser HTTPS vers passerelle paiement (exemple API Stripe)
  - to:
    - namespaceSelector: {}
    ports:
    - protocol: TCP
      port: 443
    # Optionnel: Ajouter blocs CIDR pour IPs specifiques
    # podSelector: {}
    # - to:
    #   - ipBlock:
    #       cidr: 54.187.205.235/32  # exemple IP Stripe
```

---

## Standards Securite Pod

### Admission Securite Pod

**Configuration Namespace :**

```yaml
# namespace-with-pod-security.yaml
apiVersion: v1
kind: Namespace
metadata:
  name: e-commerce
  labels:
    pod-security.kubernetes.io/enforce: restricted
    pod-security.kubernetes.io/audit: restricted
    pod-security.kubernetes.io/warn: restricted
```

### Application Profil Restricted

**Contexte Securite Pod :**

```yaml
# orders-service-deployment-secure.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: orders-service
  namespace: e-commerce
spec:
  replicas: 3
  selector:
    matchLabels:
      app: orders-service
  template:
    metadata:
      labels:
        app: orders-service
        tier: backend
    spec:
      serviceAccountName: orders-service-sa

      # Contexte securite niveau pod
      securityContext:
        runAsNonRoot: true
        runAsUser: 1000
        runAsGroup: 3000
        fsGroup: 2000
        seccompProfile:
          type: RuntimeDefault

      containers:
      - name: orders-service
        image: orders-service:v1.0.0

        # Contexte securite niveau conteneur
        securityContext:
          allowPrivilegeEscalation: false
          readOnlyRootFilesystem: true
          runAsNonRoot: true
          runAsUser: 1000
          capabilities:
            drop:
            - ALL

        # Volume inscriptible pour cache/logs Laravel
        volumeMounts:
        - name: cache
          mountPath: /var/www/html/storage/framework/cache
        - name: logs
          mountPath: /var/www/html/storage/logs
        - name: tmp
          mountPath: /tmp

        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"

      volumes:
      - name: cache
        emptyDir: {}
      - name: logs
        emptyDir: {}
      - name: tmp
        emptyDir: {}
```

### Conteneurs Non-Root

**Exemple Dockerfile :**

```dockerfile
# services/orders-service/Dockerfile
FROM php:8.3-fpm-alpine

# Creer utilisateur non-root
RUN addgroup -g 1000 laravel && \
    adduser -D -u 1000 -G laravel laravel

# Installer dependances
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client

# Copier application
WORKDIR /var/www/html
COPY --chown=laravel:laravel . .

# Installer dependances composer
RUN composer install --no-dev --optimize-autoloader

# Definir permissions appropriees
RUN chown -R laravel:laravel /var/www/html/storage /var/www/html/bootstrap/cache

# Basculer utilisateur non-root
USER laravel

EXPOSE 9000

CMD ["php-fpm"]
```

### Systeme Fichiers Racine Lecture Seule

```yaml
# Exemple complet avec racine lecture seule
apiVersion: apps/v1
kind: Deployment
metadata:
  name: products-service
  namespace: e-commerce
spec:
  template:
    spec:
      securityContext:
        runAsNonRoot: true
        runAsUser: 1000
        fsGroup: 2000

      containers:
      - name: products-service
        image: products-service:v1.0.0

        securityContext:
          allowPrivilegeEscalation: false
          readOnlyRootFilesystem: true
          runAsNonRoot: true
          runAsUser: 1000
          capabilities:
            drop: ["ALL"]

        # Monter volumes inscriptibles uniquement ou necessaire
        volumeMounts:
        - name: storage-cache
          mountPath: /var/www/html/storage/framework/cache
        - name: storage-logs
          mountPath: /var/www/html/storage/logs
        - name: storage-sessions
          mountPath: /var/www/html/storage/framework/sessions
        - name: storage-views
          mountPath: /var/www/html/storage/framework/views
        - name: tmp
          mountPath: /tmp
        - name: php-tmp
          mountPath: /var/run/php-fpm

      volumes:
      - name: storage-cache
        emptyDir: {}
      - name: storage-logs
        emptyDir: {}
      - name: storage-sessions
        emptyDir: {}
      - name: storage-views
        emptyDir: {}
      - name: tmp
        emptyDir: {}
      - name: php-tmp
        emptyDir: {}
```

### Bonnes Pratiques Contextes Securite

```yaml
# security-contexts-template.yaml
apiVersion: v1
kind: Pod
metadata:
  name: secure-pod
spec:
  # Securite niveau pod
  securityContext:
    runAsNonRoot: true        # Empecher execution root
    runAsUser: 1000           # UID specifique
    runAsGroup: 3000          # GID specifique
    fsGroup: 2000             # Propriete volume
    supplementalGroups: [4000]
    seccompProfile:
      type: RuntimeDefault    # Profil Seccomp

  containers:
  - name: app
    image: app:latest

    # Securite niveau conteneur
    securityContext:
      allowPrivilegeEscalation: false  # Pas escalade privilege
      readOnlyRootFilesystem: true     # Racine immuable
      runAsNonRoot: true
      runAsUser: 1000
      capabilities:
        drop:
        - ALL                  # Supprimer toutes capacites
        add:
        - NET_BIND_SERVICE     # Ajouter uniquement requises
```

---

## Securite Images

### Scanning Conteneurs avec Trivy

**Integration CI/CD (GitHub Actions) :**

```yaml
# .github/workflows/security-scan.yml
name: Security Scan

on:
  push:
    branches: [main, dev]
  pull_request:
    branches: [main]

jobs:
  trivy-scan:
    runs-on: ubuntu-latest
    steps:
    - name: Checkout code
      uses: actions/checkout@v3

    - name: Build Docker image
      run: |
        docker build -t orders-service:${{ github.sha }} \
          -f services/orders-service/Dockerfile \
          services/orders-service

    - name: Run Trivy vulnerability scanner
      uses: aquasecurity/trivy-action@master
      with:
        image-ref: 'orders-service:${{ github.sha }}'
        format: 'sarif'
        output: 'trivy-results.sarif'
        severity: 'CRITICAL,HIGH'
        exit-code: '1'  # Echouer sur vulnerabilites

    - name: Upload Trivy results to GitHub Security
      uses: github/codeql-action/upload-sarif@v2
      if: always()
      with:
        sarif_file: 'trivy-results.sarif'
```

**Job Kubernetes pour Scanning Planifie :**

```yaml
# trivy-scanner-cronjob.yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: trivy-image-scanner
  namespace: security
spec:
  schedule: "0 2 * * *"  # Quotidien a 2h
  jobTemplate:
    spec:
      template:
        spec:
          serviceAccountName: trivy-scanner-sa
          containers:
          - name: trivy
            image: aquasec/trivy:latest
            command:
            - trivy
            - image
            - --severity
            - CRITICAL,HIGH
            - --exit-code
            - "1"
            - --format
            - json
            - --output
            - /reports/trivy-report.json
            - orders-service:latest
            volumeMounts:
            - name: reports
              mountPath: /reports

          volumes:
          - name: reports
            persistentVolumeClaim:
              claimName: trivy-reports-pvc

          restartPolicy: OnFailure
```

### Signature Images avec Cosign

**Signer Images :**

```bash
# Generer paire cles
cosign generate-key-pair

# Signer image
cosign sign --key cosign.key registry.example.com/orders-service:v1.0.0

# Verifier signature
cosign verify --key cosign.pub registry.example.com/orders-service:v1.0.0
```

**Controleur Admission pour Verification Signature :**

```yaml
# cosign-policy-webhook.yaml
apiVersion: admissionregistration.k8s.io/v1
kind: ValidatingWebhookConfiguration
metadata:
  name: cosign-policy-webhook
webhooks:
- name: image-signature.cosign.io
  clientConfig:
    service:
      name: cosign-policy-webhook
      namespace: cosign-system
      path: "/validate"
  rules:
  - apiGroups: [""]
    apiVersions: ["v1"]
    operations: ["CREATE", "UPDATE"]
    resources: ["pods"]
  admissionReviewVersions: ["v1"]
  sideEffects: None
  failurePolicy: Fail  # Rejeter images non signees
```

### Registry Prive

**Configuration Registry Harbor :**

```yaml
# harbor-values.yaml (valeurs Helm)
expose:
  type: ingress
  tls:
    enabled: true
    certSource: secret
    secret:
      secretName: harbor-tls
  ingress:
    hosts:
      core: registry.example.com
    annotations:
      cert-manager.io/cluster-issuer: letsencrypt-prod

persistence:
  enabled: true
  persistentVolumeClaim:
    registry:
      size: 100Gi
    database:
      size: 10Gi

# Activer scanning vulnerabilites
trivy:
  enabled: true

# Activer signature images
notary:
  enabled: true
```

**Configuration Pull Secret :**

```bash
# Creer pull secret
kubectl create secret docker-registry harbor-pull-secret \
  --docker-server=registry.example.com \
  --docker-username=robot$ecommerce \
  --docker-password=<token> \
  --namespace=e-commerce

# Utiliser dans deployment
kubectl patch serviceaccount default \
  -p '{"imagePullSecrets": [{"name": "harbor-pull-secret"}]}' \
  -n e-commerce
```

### Politiques Pull Image

```yaml
# image-pull-policy-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: orders-service
  namespace: e-commerce
spec:
  template:
    spec:
      # Utiliser pull secret
      imagePullSecrets:
      - name: harbor-pull-secret

      containers:
      - name: orders-service
        # Toujours utiliser tags specifiques, jamais :latest
        image: registry.example.com/e-commerce/orders-service:v1.2.3

        # Toujours pull pour assurer derniers patchs
        imagePullPolicy: Always

        # Ou utiliser IfNotPresent pour tags stables
        # imagePullPolicy: IfNotPresent
```

---

## Gestion TLS/SSL

### Installation Cert-Manager

```yaml
# cert-manager-install.yaml
apiVersion: v1
kind: Namespace
metadata:
  name: cert-manager
---
apiVersion: helm.cattle.io/v1
kind: HelmChart
metadata:
  name: cert-manager
  namespace: kube-system
spec:
  chart: cert-manager
  repo: https://charts.jetstack.io
  targetNamespace: cert-manager
  valuesContent: |-
    installCRDs: true
    global:
      leaderElection:
        namespace: cert-manager
```

### ClusterIssuers Let's Encrypt

**Issuer Production :**

```yaml
# letsencrypt-prod-issuer.yaml
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@example.com
    privateKeySecretRef:
      name: letsencrypt-prod-account-key
    solvers:
    # Challenge HTTP01
    - http01:
        ingress:
          class: nginx
    # Challenge DNS01 pour certificats wildcard
    - dns01:
        cloudflare:
          email: admin@example.com
          apiTokenSecretRef:
            name: cloudflare-api-token
            key: api-token
      selector:
        dnsZones:
        - example.com
```

**Issuer Staging (pour tests) :**

```yaml
# letsencrypt-staging-issuer.yaml
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-staging
spec:
  acme:
    server: https://acme-staging-v02.api.letsencrypt.org/directory
    email: admin@example.com
    privateKeySecretRef:
      name: letsencrypt-staging-account-key
    solvers:
    - http01:
        ingress:
          class: nginx
```

### Ressources Certificate

**Ingress avec Certificat Automatique :**

```yaml
# ingress-with-tls.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: ecommerce-ingress
  namespace: e-commerce
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
    nginx.ingress.kubernetes.io/force-ssl-redirect: "true"
spec:
  ingressClassName: nginx
  tls:
  - hosts:
    - api.example.com
    - www.example.com
    secretName: ecommerce-tls-cert  # cert-manager cree ceci
  rules:
  - host: api.example.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: api-gateway
            port:
              number: 8100
```

**Certificat Wildcard :**

```yaml
# wildcard-certificate.yaml
apiVersion: cert-manager.io/v1
kind: Certificate
metadata:
  name: wildcard-cert
  namespace: e-commerce
spec:
  secretName: wildcard-tls-secret
  issuerRef:
    name: letsencrypt-prod
    kind: ClusterIssuer
  commonName: "*.example.com"
  dnsNames:
  - "*.example.com"
  - example.com
```

### TLS Mutuel (mTLS) Entre Services

**Approche Service Mesh (Istio) :**

```yaml
# istio-mtls-policy.yaml
apiVersion: security.istio.io/v1beta1
kind: PeerAuthentication
metadata:
  name: default-mtls
  namespace: e-commerce
spec:
  mtls:
    mode: STRICT  # Imposer mTLS pour tout trafic
---
apiVersion: networking.istio.io/v1beta1
kind: DestinationRule
metadata:
  name: default-mtls-dr
  namespace: e-commerce
spec:
  host: "*.e-commerce.svc.cluster.local"
  trafficPolicy:
    tls:
      mode: ISTIO_MUTUAL  # Utiliser certificats geres Istio
```

**mTLS Manuel avec Ressources Certificate :**

```yaml
# service-mtls-certificate.yaml
apiVersion: cert-manager.io/v1
kind: Certificate
metadata:
  name: orders-service-mtls
  namespace: e-commerce
spec:
  secretName: orders-service-mtls-cert
  duration: 2160h  # 90 jours
  renewBefore: 360h  # Renouveler 15 jours avant expiration
  subject:
    organizations:
    - e-commerce
  commonName: orders-service.e-commerce.svc.cluster.local
  isCA: false
  privateKey:
    algorithm: RSA
    encoding: PKCS1
    size: 2048
  usages:
  - server auth
  - client auth
  dnsNames:
  - orders-service.e-commerce.svc.cluster.local
  - orders-service
  issuerRef:
    name: internal-ca-issuer
    kind: ClusterIssuer
```

---

## Journalisation Audit

### Journaux Audit Kubernetes

**Politique Audit :**

```yaml
# audit-policy.yaml
apiVersion: audit.k8s.io/v1
kind: Policy
rules:
# Journaliser toutes requetes niveau Metadata
- level: Metadata
  omitStages:
  - RequestReceived

# Journaliser secrets, configmaps niveau Request (inclut corps requete)
- level: Request
  resources:
  - group: ""
    resources: ["secrets", "configmaps"]

# Journaliser authentification et autorisation
- level: RequestResponse
  verbs: ["create", "update", "patch", "delete"]
  resources:
  - group: ""
    resources: ["serviceaccounts"]
  - group: "rbac.authorization.k8s.io"
    resources: ["roles", "rolebindings", "clusterroles", "clusterrolebindings"]

# Journaliser exec et attach pod
- level: RequestResponse
  resources:
  - group: ""
    resources: ["pods/exec", "pods/attach", "pods/portforward"]

# Ne pas journaliser URLs lecture seule
- level: None
  verbs: ["get", "list", "watch"]
  resources:
  - group: ""
    resources: ["events", "nodes", "nodes/status", "persistentvolumes", "persistentvolumeclaims"]
```

**Configuration Serveur API :**

```yaml
# kube-apiserver.yaml (manifeste pod statique)
apiVersion: v1
kind: Pod
metadata:
  name: kube-apiserver
  namespace: kube-system
spec:
  containers:
  - name: kube-apiserver
    image: k8s.gcr.io/kube-apiserver:v1.28.0
    command:
    - kube-apiserver
    - --audit-policy-file=/etc/kubernetes/audit-policy.yaml
    - --audit-log-path=/var/log/kubernetes/audit.log
    - --audit-log-maxage=30
    - --audit-log-maxbackup=10
    - --audit-log-maxsize=100
    volumeMounts:
    - name: audit-policy
      mountPath: /etc/kubernetes/audit-policy.yaml
      readOnly: true
    - name: audit-logs
      mountPath: /var/log/kubernetes
  volumes:
  - name: audit-policy
    hostPath:
      path: /etc/kubernetes/audit-policy.yaml
      type: File
  - name: audit-logs
    hostPath:
      path: /var/log/kubernetes
      type: DirectoryOrCreate
```

### Journaux Application - Journalisation Centralisee

**DaemonSet Fluent Bit :**

```yaml
# fluent-bit-daemonset.yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: fluent-bit
  namespace: logging
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRole
metadata:
  name: fluent-bit
rules:
- apiGroups: [""]
  resources: ["namespaces", "pods"]
  verbs: ["get", "list", "watch"]
---
apiVersion: rbac.authorization.k8s.io/v1
kind: ClusterRoleBinding
metadata:
  name: fluent-bit
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: ClusterRole
  name: fluent-bit
subjects:
- kind: ServiceAccount
  name: fluent-bit
  namespace: logging
---
apiVersion: apps/v1
kind: DaemonSet
metadata:
  name: fluent-bit
  namespace: logging
spec:
  selector:
    matchLabels:
      app: fluent-bit
  template:
    metadata:
      labels:
        app: fluent-bit
    spec:
      serviceAccountName: fluent-bit
      containers:
      - name: fluent-bit
        image: fluent/fluent-bit:2.1
        volumeMounts:
        - name: varlog
          mountPath: /var/log
          readOnly: true
        - name: varlibdockercontainers
          mountPath: /var/lib/docker/containers
          readOnly: true
        - name: fluent-bit-config
          mountPath: /fluent-bit/etc/
      volumes:
      - name: varlog
        hostPath:
          path: /var/log
      - name: varlibdockercontainers
        hostPath:
          path: /var/lib/docker/containers
      - name: fluent-bit-config
        configMap:
          name: fluent-bit-config
```

**Configuration Fluent Bit :**

```yaml
# fluent-bit-configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: fluent-bit-config
  namespace: logging
data:
  fluent-bit.conf: |
    [SERVICE]
        Flush         5
        Daemon        Off
        Log_Level     info
        Parsers_File  parsers.conf

    [INPUT]
        Name              tail
        Path              /var/log/containers/*.log
        Parser            docker
        Tag               kube.*
        Refresh_Interval  5
        Mem_Buf_Limit     50MB
        Skip_Long_Lines   On

    [FILTER]
        Name                kubernetes
        Match               kube.*
        Kube_URL            https://kubernetes.default.svc:443
        Kube_CA_File        /var/run/secrets/kubernetes.io/serviceaccount/ca.crt
        Kube_Token_File     /var/run/secrets/kubernetes.io/serviceaccount/token
        Kube_Tag_Prefix     kube.var.log.containers.
        Merge_Log           On
        Keep_Log            Off

    [OUTPUT]
        Name                elasticsearch
        Match               *
        Host                elasticsearch.logging.svc.cluster.local
        Port                9200
        Logstash_Format     On
        Logstash_Prefix     k8s-logs
        Retry_Limit         False

  parsers.conf: |
    [PARSER]
        Name   docker
        Format json
        Time_Key time
        Time_Format %Y-%m-%dT%H:%M:%S.%LZ
        Time_Keep On
```

### Integration SIEM

**Elastic Stack (ELK) :**

```yaml
# elasticsearch-deployment.yaml
apiVersion: apps/v1
kind: StatefulSet
metadata:
  name: elasticsearch
  namespace: logging
spec:
  serviceName: elasticsearch
  replicas: 3
  selector:
    matchLabels:
      app: elasticsearch
  template:
    metadata:
      labels:
        app: elasticsearch
    spec:
      containers:
      - name: elasticsearch
        image: docker.elastic.co/elasticsearch/elasticsearch:8.10.0
        env:
        - name: discovery.type
          value: "zen"
        - name: cluster.name
          value: "k8s-logs"
        - name: ES_JAVA_OPTS
          value: "-Xms2g -Xmx2g"
        - name: xpack.security.enabled
          value: "true"
        - name: xpack.security.audit.enabled
          value: "true"
        ports:
        - containerPort: 9200
          name: http
        - containerPort: 9300
          name: transport
        volumeMounts:
        - name: data
          mountPath: /usr/share/elasticsearch/data
  volumeClaimTemplates:
  - metadata:
      name: data
    spec:
      accessModes: ["ReadWriteOnce"]
      storageClassName: fast-ssd
      resources:
        requests:
          storage: 100Gi
```

**Deploiement Kibana :**

```yaml
# kibana-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: kibana
  namespace: logging
spec:
  replicas: 1
  selector:
    matchLabels:
      app: kibana
  template:
    metadata:
      labels:
        app: kibana
    spec:
      containers:
      - name: kibana
        image: docker.elastic.co/kibana/kibana:8.10.0
        env:
        - name: ELASTICSEARCH_HOSTS
          value: "http://elasticsearch.logging.svc.cluster.local:9200"
        - name: ELASTICSEARCH_USERNAME
          value: "elastic"
        - name: ELASTICSEARCH_PASSWORD
          valueFrom:
            secretKeyRef:
              name: elasticsearch-credentials
              key: password
        ports:
        - containerPort: 5601
          name: http
```

---

## Conformite

### Considerations RGPD

**Mesures Protection Donnees :**

1. **Chiffrement Donnees**
   - Au repos : Volumes chiffres pour bases donnees
   - En transit : TLS pour toutes communications
   - Chiffrement sauvegardes

2. **Droit d'Acces**
   ```yaml
   # Point terminaison API pour export donnees utilisateur
   GET /api/v1/users/{id}/export
   ```

3. **Droit Effacement (Droit a l'Oubli)**
   ```yaml
   # Point terminaison suppression donnees
   DELETE /api/v1/users/{id}/data
   ```

4. **Politiques Retention Donnees**
   ```yaml
   # CronJob pour nettoyage donnees automatise
   apiVersion: batch/v1
   kind: CronJob
   metadata:
     name: gdpr-data-cleanup
     namespace: e-commerce
   spec:
     schedule: "0 3 * * 0"  # Hebdomadaire dimanche
     jobTemplate:
       spec:
         template:
           spec:
             containers:
             - name: cleanup
               image: data-cleanup:latest
               command:
               - /scripts/gdpr-cleanup.sh
               env:
               - name: RETENTION_DAYS
                 value: "365"
             restartPolicy: OnFailure
   ```

5. **Pistes Audit**
   - Journaliser tous acces donnees
   - Suivre changements consentement
   - Enregistrer activites traitement donnees

### PCI-DSS (Payment Card Industry)

**Si gestion paiements directe :**

1. **Segmentation Reseau**
   ```yaml
   # Namespace separe pour traitement paiement
   apiVersion: v1
   kind: Namespace
   metadata:
     name: pci-zone
     labels:
       pod-security.kubernetes.io/enforce: restricted
   ```

2. **Exigences Chiffrement**
   - TLS 1.2+ uniquement
   - Suites chiffrement fortes
   - Rotation certificats

3. **Controle Acces**
   ```yaml
   # RBAC strict pour services paiement
   apiVersion: rbac.authorization.k8s.io/v1
   kind: Role
   metadata:
     name: payment-service-role
     namespace: pci-zone
   rules:
   - apiGroups: [""]
     resources: ["secrets"]
     resourceNames: ["payment-gateway-credentials"]
     verbs: ["get"]
   ```

4. **Journalisation et Surveillance**
   - Tous acces journalises
   - Tentatives connexion echouees suivies
   - Alertes sur activite suspecte

### ISO 27001

**Gestion Securite Information :**

1. **Politique Controle Acces**
   - Application RBAC
   - Authentification multi-facteurs
   - Revisions acces regulieres

2. **Gestion Changements**
   ```yaml
   # Workflow GitOps avec processus approbation
   # Tous changements via Pull Requests
   # Tests automatises avant fusion
   # Portes approbation deploiement
   ```

3. **Continuite Activite**
   - Plan reprise apres sinistre
   - Sauvegardes regulieres
   - Procedures restauration testees

4. **Gestion Incidents**
   - Playbook reponse incidents
   - Plan communication
   - Revisions post-incidents

### SOC 2

**Controle Organisation Service :**

1. **Securite**
   - Controles acces
   - Chiffrement
   - Regles pare-feu

2. **Disponibilite**
   - Conception haute disponibilite
   - Equilibrage charge
   - Auto-scaling

3. **Integrite Traitement**
   - Validation donnees
   - Gestion erreurs
   - Journalisation transactions

4. **Confidentialite**
   - Classification donnees
   - Restrictions acces
   - Elimination securisee

5. **Vie Privee**
   - Politique confidentialite
   - Gestion consentement
   - Minimisation donnees

**ConfigMap Conformite :**

```yaml
# compliance-config.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: compliance-config
  namespace: e-commerce
data:
  gdpr.enabled: "true"
  gdpr.retention-days: "365"
  pci-dss.enabled: "false"  # Definir true si gestion cartes
  iso27001.enabled: "true"
  soc2.enabled: "true"
  audit-logging.level: "detailed"
  data-encryption.required: "true"
  mfa.required: "true"
```

---

## Gestion Vulnerabilites

### Scanning CVE

**Operateur Trivy pour Scanning Continu :**

```yaml
# trivy-operator-install.yaml
apiVersion: v1
kind: Namespace
metadata:
  name: trivy-system
---
apiVersion: helm.cattle.io/v1
kind: HelmChart
metadata:
  name: trivy-operator
  namespace: kube-system
spec:
  chart: trivy-operator
  repo: https://aquasecurity.github.io/helm-charts/
  targetNamespace: trivy-system
  valuesContent: |-
    trivy:
      ignoreUnfixed: true
      severity: CRITICAL,HIGH

    operator:
      vulnerabilityReportsPlugin: Trivy

    serviceMonitor:
      enabled: true
```

**VulnerabilityReport Ressource Personnalisee :**

```yaml
# Exemple VulnerabilityReport (auto-genere par operateur)
apiVersion: aquasecurity.github.io/v1alpha1
kind: VulnerabilityReport
metadata:
  name: orders-service-6d8f9b7c5d-abc123
  namespace: e-commerce
report:
  artifact:
    repository: orders-service
    tag: v1.0.0
  registry:
    server: registry.example.com
  scanner:
    name: Trivy
    version: 0.45.0
  summary:
    criticalCount: 0
    highCount: 2
    mediumCount: 5
    lowCount: 12
  vulnerabilities:
  - vulnerabilityID: CVE-2023-12345
    severity: HIGH
    title: "Execution code arbitraire dans package xyz"
    fixedVersion: "1.2.3"
    installedVersion: "1.2.0"
```

### Gestion Patchs

**Workflow Patch Automatise :**

```yaml
# renovate.json (pour mises a jour dependances automatisees)
{
  "$schema": "https://docs.renovatebot.com/renovate-schema.json",
  "extends": ["config:base"],
  "kubernetes": {
    "fileMatch": ["k8s/.+\\.yaml$"],
    "ignorePaths": ["k8s/secrets/"]
  },
  "docker": {
    "enabled": true,
    "pinDigests": true
  },
  "schedule": ["before 6am on monday"],
  "automerge": false,
  "prConcurrentLimit": 5,
  "vulnerabilityAlerts": {
    "enabled": true,
    "labels": ["security"],
    "assignees": ["@security-team"]
  }
}
```

**CronJob pour Mises a Jour Securite :**

```yaml
# security-update-check.yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: security-update-check
  namespace: security
spec:
  schedule: "0 6 * * *"  # Quotidien a 6h
  jobTemplate:
    spec:
      template:
        spec:
          serviceAccountName: security-scanner
          containers:
          - name: update-checker
            image: update-checker:latest
            command:
            - /scripts/check-updates.sh
            env:
            - name: SEVERITY_THRESHOLD
              value: "HIGH"
            - name: SLACK_WEBHOOK
              valueFrom:
                secretKeyRef:
                  name: slack-webhook
                  key: url
          restartPolicy: OnFailure
```

### Mises a Jour Securite

**Strategie Mise a Jour GitOps :**

```yaml
# argocd-application.yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: e-commerce
  namespace: argocd
spec:
  project: default
  source:
    repoURL: https://github.com/org/e-commerce-k8s
    targetRevision: main
    path: k8s/overlays/production
  destination:
    server: https://kubernetes.default.svc
    namespace: e-commerce
  syncPolicy:
    automated:
      prune: true
      selfHeal: true
      allowEmpty: false
    syncOptions:
    - CreateNamespace=true
    retry:
      limit: 5
      backoff:
        duration: 5s
        factor: 2
        maxDuration: 3m
```

---

## Reponse Incidents

### Plan Reponse Incidents

**Phase 1 : Detection**

```yaml
# alertmanager-config.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: alertmanager-config
  namespace: monitoring
data:
  alertmanager.yml: |
    global:
      resolve_timeout: 5m
      slack_api_url: 'https://hooks.slack.com/services/XXX'

    route:
      group_by: ['alertname', 'cluster', 'service']
      group_wait: 10s
      group_interval: 10s
      repeat_interval: 12h
      receiver: 'security-team'
      routes:
      - match:
          severity: critical
        receiver: 'pagerduty'
        continue: true
      - match:
          severity: warning
        receiver: 'slack'

    receivers:
    - name: 'security-team'
      email_configs:
      - to: 'security@example.com'
        send_resolved: true

    - name: 'pagerduty'
      pagerduty_configs:
      - service_key: '<pagerduty-key>'
        description: '{{ .CommonAnnotations.summary }}'

    - name: 'slack'
      slack_configs:
      - channel: '#security-alerts'
        text: '{{ range .Alerts }}{{ .Annotations.description }}{{ end }}'
```

**Alertes Securite :**

```yaml
# prometheus-rules-security.yaml
apiVersion: monitoring.coreos.com/v1
kind: PrometheusRule
metadata:
  name: security-alerts
  namespace: monitoring
spec:
  groups:
  - name: security
    interval: 30s
    rules:
    - alert: UnauthorizedAccessAttempt
      expr: rate(nginx_ingress_controller_requests{status="401"}[5m]) > 10
      for: 2m
      labels:
        severity: warning
      annotations:
        summary: "Taux eleve reponses 401"
        description: "{{ $value }} requetes non autorisees par seconde"

    - alert: PodSecurityViolation
      expr: pod_security_policy_error > 0
      for: 1m
      labels:
        severity: critical
      annotations:
        summary: "Violation Politique Securite Pod detectee"

    - alert: SuspiciousNetworkActivity
      expr: rate(network_policy_drops[5m]) > 100
      for: 5m
      labels:
        severity: warning
      annotations:
        summary: "Taux eleve rejets politique reseau"

    - alert: HighPrivilegeContainerDetected
      expr: kube_pod_container_status_privileged == 1
      labels:
        severity: critical
      annotations:
        summary: "Conteneur privilegie en execution"
        description: "Pod {{ $labels.pod }} dans {{ $labels.namespace }} execute en mode privilegie"
```

### Phase 2 : Confinement

**Isolation Reseau :**

```yaml
# incident-isolation-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: incident-isolation
  namespace: e-commerce
spec:
  podSelector:
    matchLabels:
      incident: "isolated"
  policyTypes:
  - Ingress
  - Egress
  # Isolation complete - aucun ingress ou egress autorise
```

**Reduire Service Compromis :**

```bash
# Scale down urgence
kubectl scale deployment/compromised-service --replicas=0 -n e-commerce

# Appliquer label isolation
kubectl label pod compromised-pod incident=isolated -n e-commerce
```

### Phase 3 : Forensique

**Capturer Etat Pod :**

```bash
# Creer instantane forensique
kubectl get pod suspicious-pod -n e-commerce -o yaml > pod-snapshot.yaml
kubectl describe pod suspicious-pod -n e-commerce > pod-describe.txt
kubectl logs suspicious-pod -n e-commerce --all-containers=true > pod-logs.txt
kubectl logs suspicious-pod -n e-commerce --previous > pod-previous-logs.txt

# Exporter evenements
kubectl get events -n e-commerce --sort-by='.lastTimestamp' > events.txt
```

**Conteneur Forensique :**

```yaml
# forensic-sidecar.yaml
apiVersion: v1
kind: Pod
metadata:
  name: forensic-analysis
  namespace: e-commerce
spec:
  containers:
  - name: forensic-tools
    image: forensic-tools:latest
    command: ["/bin/sh", "-c", "sleep 3600"]
    volumeMounts:
    - name: evidence
      mountPath: /evidence
    securityContext:
      capabilities:
        add: ["SYS_ADMIN", "SYS_PTRACE"]
  volumes:
  - name: evidence
    persistentVolumeClaim:
      claimName: forensic-evidence-pvc
```

### Phase 4 : Recuperation

**Sauvegarde et Restauration :**

```yaml
# velero-backup-schedule.yaml
apiVersion: velero.io/v1
kind: Schedule
metadata:
  name: daily-backup
  namespace: velero
spec:
  schedule: "0 1 * * *"  # Quotidien a 1h
  template:
    includedNamespaces:
    - e-commerce
    - e-commerce-staging
    excludedResources:
    - events
    - events.events.k8s.io
    storageLocation: default
    volumeSnapshotLocations:
    - default
    ttl: 720h  # 30 jours
```

**Procedure Restauration :**

```bash
# Lister sauvegardes
velero backup get

# Restaurer depuis sauvegarde specifique
velero restore create --from-backup daily-backup-20231201010000

# Restaurer namespace specifique
velero restore create --from-backup daily-backup-20231201010000 \
  --include-namespaces e-commerce

# Verifier statut restauration
velero restore describe <restore-name>
```

### Phase 5 : Revue Post-Incident

**Template Rapport Incident :**

```yaml
# incident-report.yaml
incident_id: INC-2023-001
date: 2023-12-01
severity: high
status: resolved

timeline:
  detection: "2023-12-01T10:15:00Z"
  containment: "2023-12-01T10:30:00Z"
  eradication: "2023-12-01T11:45:00Z"
  recovery: "2023-12-01T13:00:00Z"
  closure: "2023-12-01T15:00:00Z"

affected_systems:
  - orders-service
  - payments-service

impact:
  users_affected: 1500
  downtime_minutes: 45
  data_compromised: false

root_cause: |
  Dependance obsolete avec vulnerabilite CVE-2023-12345 connue
  exploitee via point terminaison API /api/v1/orders/export

actions_taken:
  - Isole pods affectes
  - Applique patch urgence
  - Rotation toutes credentials
  - Revue journaux audit

preventive_measures:
  - Implementer scanning dependances automatise
  - Ajouter limitation debit points terminaison export
  - Ameliorer surveillance modeles trafic anormaux
  - Mettre a jour playbook reponse incidents

lessons_learned: |
  Besoin pipeline deploiement patchs plus rapide
  Necessite pre-approbation points terminaison haut risque
  Ameliorer communication pendant incidents
```

---

## Liste Verification Securite

### Validation Securite Pre-Production

```yaml
# security-checklist.yaml
cluster_security:
  ✓ RBAC active et configure
  ✓ Standards Securite Pod appliques (profil restricted)
  ✓ Politiques Reseau en place (deny par defaut)
  ✓ Journalisation audit activee
  ✓ Serveur API securise

secrets_management:
  ✓ Operateur External Secrets configure
  ✓ Vault/gestionnaire secrets cloud integre
  ✓ Pas secrets codees en dur dans code
  ✓ Sealed Secrets pour GitOps
  ✓ Rotation secrets automatisee

image_security:
  ✓ Images scannees vulnerabilites (Trivy)
  ✓ Images signees (Cosign)
  ✓ Registry prive configure
  ✓ Politiques pull images definies correctement
  ✓ Conteneurs non-root appliques

network_security:
  ✓ Certificats TLS/SSL configures
  ✓ Ingress avec WAF active
  ✓ mTLS entre services
  ✓ Politiques Reseau testees
  ✓ Controles egress en place

application_security:
  ✓ Authentification JWT implementee
  ✓ Permissions RBAC configurees
  ✓ Validation entrees tous points terminaison
  ✓ Prevention injection SQL
  ✓ Protection XSS activee

data_security:
  ✓ Chiffrement au repos active
  ✓ Connexions base donnees chiffrees
  ✓ Chiffrement sauvegardes configure
  ✓ Politiques retention donnees definies
  ✓ Mesures conformite RGPD

monitoring_logging:
  ✓ Journalisation centralisee (ELK/Loki)
  ✓ Alertes securite configurees
  ✓ Journaux audit transmis SIEM
  ✓ Detection anomalies activee
  ✓ Plan reponse incidents teste

compliance:
  ✓ Exigences RGPD satisfaites
  ✓ PCI-DSS si applicable
  ✓ Controles ISO 27001 implementes
  ✓ Conformite SOC 2 validee
  ✓ Audits securite reguliers planifies
```

---

## Conclusion

Cette architecture securite implemente defense en profondeur a travers toutes couches de la plateforme e-commerce Kubernetes. Revisions regulieres, scanning automatise et surveillance continue assurent amelioration continue posture securite.

**Points Cles :**

1. **Defense Multi-Couches** : Securite aux couches perimetre, reseau, application et donnees
2. **Modele Zero-Trust** : Pas confiance implicite, verification continue
3. **Securite Automatisee** : Scanning, patching et surveillance automatises
4. **Prete Conformite** : Considerations RGPD, PCI-DSS, ISO 27001, SOC 2
5. **Preparation Incidents** : Plan reponse clair et capacites forensiques

**Prochaines Etapes :**

1. Implementer gestion secrets (Operateur External Secrets + Vault)
2. Deployer Politiques Reseau pour tous services
3. Configurer cert-manager avec Let's Encrypt
4. Configurer journalisation centralisee (stack ELK)
5. Implementer scanning vulnerabilites automatise
6. Conduire audit securite et tests penetration
7. Former equipe procedures reponse incidents

**References :**

- [Bonnes Pratiques Securite Kubernetes](https://kubernetes.io/docs/concepts/security/)
- [Benchmark CIS Kubernetes](https://www.cisecurity.org/benchmark/kubernetes)
- [Fiche Securite Kubernetes OWASP](https://cheatsheetseries.owasp.org/cheatsheets/Kubernetes_Security_Cheat_Sheet.html)
- [Framework Cybersecurite NIST](https://www.nist.gov/cyberframework)
