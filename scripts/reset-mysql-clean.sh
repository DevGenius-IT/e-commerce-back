#!/bin/bash

# Script to cleanly reset all MySQL deployments
# This ensures emptyDir volumes are completely cleaned

set -e

echo "🔄 Resetting MySQL deployments cleanly..."

# Step 1: Delete all MySQL deployments
echo "📦 Deleting MySQL deployments..."
kubectl delete deployment -n e-commerce -l component=database --wait=true

# Step 2: Wait for all pods to be completely terminated
echo "⏳ Waiting for pod termination..."
while kubectl get pods -n e-commerce -l component=database 2>/dev/null | grep -q mysql; do
  echo "   Still terminating..."
  sleep 5
done
echo "✅ All MySQL pods terminated"

# Step 3: Recreate deployments from manifests
echo "🚀 Recreating MySQL deployments..."
for file in k8s/base/services/*-mysql.yaml; do
  kubectl apply -f "$file"
done

echo "✅ MySQL deployments recreated"
echo "⏳ Waiting 120s for MySQL initialization..."
sleep 120

# Step 4: Check pod status
echo "📊 Current status:"
kubectl get pods -n e-commerce -l component=database

echo ""
echo "🎉 Reset complete! Check pod status above."
echo "   Pods should be Running with 0 or 1 restarts."
echo "   Wait for READY 1/1 (readiness probes take ~45s)"
