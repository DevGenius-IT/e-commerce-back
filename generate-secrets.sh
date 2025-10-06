#!/bin/bash

# =========================================
# Script de génération de secrets de production
# =========================================

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║   GÉNÉRATION DES SECRETS DE PRODUCTION                        ║"
echo "║   E-Commerce Platform                                         ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "⚠️  ATTENTION: Gardez ces valeurs en sécurité!"
echo "   Copiez-les dans votre fichier .env"
echo ""
echo "═══════════════════════════════════════════════════════════════"
echo ""

# Fonction pour générer un secret
generate_secret() {
    openssl rand -base64 32
}

# Fonction pour générer un JWT secret (plus long)
generate_jwt_secret() {
    openssl rand -base64 64
}

# Fonction pour générer une APP_KEY Laravel
generate_app_key() {
    # Vérifier si PHP est disponible
    if command -v php &> /dev/null; then
        php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
    else
        # Fallback si PHP n'est pas installé
        echo "base64:$(openssl rand -base64 32)"
    fi
}

# ═════════════════════════════════════════
# LARAVEL APP KEY
# ═════════════════════════════════════════
echo "📦 LARAVEL APPLICATION"
echo "───────────────────────────────────────"
echo "APP_KEY=$(generate_app_key)"
echo ""

# ═════════════════════════════════════════
# JWT SECRET
# ═════════════════════════════════════════
echo "🔐 JWT AUTHENTICATION"
echo "───────────────────────────────────────"
echo "JWT_SECRET=$(generate_jwt_secret)"
echo ""

# ═════════════════════════════════════════
# DATABASE PASSWORDS
# ═════════════════════════════════════════
echo "💾 DATABASE PASSWORDS"
echo "───────────────────────────────────────"
echo "DB_ROOT_PASSWORD=$(generate_secret)"
echo ""
echo "# Auth Service"
echo "AUTH_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Products Service"
echo "PRODUCTS_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Baskets Service"
echo "BASKETS_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Orders Service"
echo "ORDERS_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Deliveries Service"
echo "DELIVERIES_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Addresses Service"
echo "ADDRESSES_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Contacts Service"
echo "CONTACTS_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Newsletters Service"
echo "NEWSLETTERS_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# SAV Service"
echo "SAV_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Websites Service"
echo "WEBSITES_DB_PASSWORD=$(generate_secret)"
echo ""
echo "# Questions Service"
echo "QUESTIONS_DB_PASSWORD=$(generate_secret)"
echo ""

# ═════════════════════════════════════════
# RABBITMQ
# ═════════════════════════════════════════
echo "🐰 RABBITMQ"
echo "───────────────────────────────────────"
echo "RABBITMQ_USER=admin"
echo "RABBITMQ_PASSWORD=$(generate_secret)"
echo ""

# ═════════════════════════════════════════
# REDIS
# ═════════════════════════════════════════
echo "🔴 REDIS"
echo "───────────────────────────────────────"
echo "REDIS_PASSWORD=$(generate_secret)"
echo ""

# ═════════════════════════════════════════
# MINIO
# ═════════════════════════════════════════
echo "📦 MINIO (Object Storage)"
echo "───────────────────────────────────────"
echo "MINIO_ROOT_USER=admin"
echo "MINIO_ROOT_PASSWORD=$(generate_secret)"
echo ""

# ═════════════════════════════════════════
# MONITORING (optionnel)
# ═════════════════════════════════════════
echo "📊 MONITORING (Grafana, etc.)"
echo "───────────────────────────────────────"
echo "GRAFANA_ADMIN_PASSWORD=$(generate_secret)"
echo ""

# ═════════════════════════════════════════
# SESSION ET ENCRYPTION
# ═════════════════════════════════════════
echo "🔒 SESSION & ENCRYPTION"
echo "───────────────────────────────────────"
echo "SESSION_SECRET=$(generate_secret)"
echo "ENCRYPTION_KEY=$(generate_secret)"
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo ""
echo "✅ Secrets générés avec succès!"
echo ""
echo "📋 PROCHAINES ÉTAPES:"
echo "   1. Copiez ces valeurs dans votre fichier .env"
echo "   2. Gardez une copie sécurisée (gestionnaire de mots de passe)"
echo "   3. Ne commitez JAMAIS ces secrets dans Git"
echo "   4. Changez ces secrets régulièrement (tous les 3-6 mois)"
echo ""
echo "⚠️  SÉCURITÉ:"
echo "   - Supprimez ce fichier de sortie après usage"
echo "   - Utilisez des canaux sécurisés pour partager les secrets"
echo "   - Activez l'authentification multi-facteurs sur les services"
echo ""
echo "═══════════════════════════════════════════════════════════════"
