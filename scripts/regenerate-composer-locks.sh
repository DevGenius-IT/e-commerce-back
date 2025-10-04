#!/bin/bash

cd /Users/kbrdn1/Projects/MNS/e-commerce-back

SERVICES=(
  "api-gateway"
  "auth-service"
  "messages-broker"
  "addresses-service"
  "products-service"
  "baskets-service"
  "orders-service"
  "deliveries-service"
  "newsletters-service"
  "sav-service"
  "contacts-service"
  "websites-service"
  "questions-service"
)

for service in "${SERVICES[@]}"; do
  echo "========================================="
  echo "Regenerating composer.lock for $service"
  echo "========================================="

  rm -f "services/$service/composer.lock"

  if composer update --no-interaction --working-dir="services/$service"; then
    echo "✓ $service composer.lock regenerated"
  else
    echo "✗ $service failed to regenerate composer.lock"
  fi

  echo ""
done

echo "All composer.lock files regenerated!"
