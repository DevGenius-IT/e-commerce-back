#!/bin/bash

# Script pour optimiser les ressources K8s et réduire la consommation CPU

set -e

echo "🔧 Optimisation des ressources Kubernetes..."

# Réduire les ressources MySQL (250m → 50m CPU, 256Mi → 128Mi RAM)
echo "📊 Réduction des ressources MySQL..."
for file in k8s/base/services/*-mysql.yaml; do
    if [ -f "$file" ]; then
        echo "  - $(basename $file)"
        sed -i '' 's/cpu: 200m/cpu: 50m/g' "$file"
        sed -i '' 's/cpu: 250m/cpu: 50m/g' "$file"
        sed -i '' 's/cpu: 500m/cpu: 100m/g' "$file"
        sed -i '' 's/memory: 256Mi/memory: 128Mi/g' "$file"
        sed -i '' 's/memory: 512Mi/memory: 256Mi/g' "$file"
    fi
done

# Réduire les ressources des services applicatifs (200m → 100m CPU)
echo "📊 Réduction des ressources services applicatifs..."
for file in k8s/overlays/development/services/*-service.yaml; do
    if [ -f "$file" ]; then
        echo "  - $(basename $file)"
        sed -i '' 's/cpu: 200m/cpu: 100m/g' "$file"
        sed -i '' 's/cpu: 250m/cpu: 150m/g' "$file"
        sed -i '' 's/cpu: 400m/cpu: 200m/g' "$file"
        sed -i '' 's/memory: 256Mi/memory: 128Mi/g' "$file"
        sed -i '' 's/memory: 512Mi/memory: 256Mi/g' "$file"
    fi
done

# Réduire RabbitMQ
echo "📊 Réduction des ressources RabbitMQ..."
if [ -f "k8s/base/services/rabbitmq.yaml" ]; then
    sed -i '' 's/cpu: 200m/cpu: 100m/g' k8s/base/services/rabbitmq.yaml
    sed -i '' 's/cpu: 500m/cpu: 200m/g' k8s/base/services/rabbitmq.yaml
    sed -i '' 's/memory: 512Mi/memory: 256Mi/g' k8s/base/services/rabbitmq.yaml
    sed -i '' 's/memory: 1Gi/memory: 512Mi/g' k8s/base/services/rabbitmq.yaml
fi

# Réduire Redis
echo "📊 Réduction des ressources Redis..."
if [ -f "k8s/base/services/redis.yaml" ]; then
    sed -i '' 's/cpu: 100m/cpu: 50m/g' k8s/base/services/redis.yaml
    sed -i '' 's/cpu: 200m/cpu: 100m/g' k8s/base/services/redis.yaml
    sed -i '' 's/memory: 128Mi/memory: 64Mi/g' k8s/base/services/redis.yaml
    sed -i '' 's/memory: 256Mi/memory: 128Mi/g' k8s/base/services/redis.yaml
fi

echo "✅ Optimisation terminée!"
echo ""
echo "📊 Résumé des changements:"
echo "  MySQL:    250m → 50m CPU,  256Mi → 128Mi RAM"
echo "  Services: 200m → 100m CPU, 256Mi → 128Mi RAM"
echo "  RabbitMQ: 200m → 100m CPU, 512Mi → 256Mi RAM"
echo "  Redis:    100m → 50m CPU,  128Mi → 64Mi RAM"
echo ""
echo "🔄 Pour appliquer: kubectl apply -k k8s/overlays/development"
