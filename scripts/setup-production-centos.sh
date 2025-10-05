#!/bin/bash

###############################################################################
# Script d'installation automatique pour CentOS 10 Stream
# E-Commerce Platform - Production Setup
###############################################################################

set -e  # Arrêter en cas d'erreur

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher des messages
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Banner
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                                                               ║"
echo "║   E-COMMERCE PLATFORM - PRODUCTION SETUP                      ║"
echo "║   CentOS 10 Stream                                            ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Vérifier que le script est exécuté avec sudo/root
if [[ $EUID -eq 0 ]]; then
   log_error "Ce script ne doit PAS être exécuté en tant que root"
   log_info "Exécutez: ./setup-production-centos.sh"
   exit 1
fi

# Vérifier que l'utilisateur est dans le groupe wheel
if ! groups | grep -q wheel; then
    log_error "L'utilisateur actuel n'est pas dans le groupe wheel (sudo)"
    log_info "Exécutez: sudo usermod -aG wheel $USER && newgrp wheel"
    exit 1
fi

# Variables
INSTALL_DIR="/var/www/e-commerce-back"
BACKUP_DIR="/var/backups/e-commerce"

echo ""
log_info "Ce script va installer et configurer:"
echo "  - Docker & Docker Compose"
echo "  - Firewall (firewalld)"
echo "  - SELinux configuration"
echo "  - Swap (4GB)"
echo "  - Optimisations système"
echo "  - Fail2ban"
echo ""
read -p "Continuer? (y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    log_warning "Installation annulée"
    exit 0
fi

###############################################################################
# 1. MISE À JOUR DU SYSTÈME
###############################################################################
echo ""
log_info "Étape 1/10: Mise à jour du système"
sudo dnf update -y
sudo dnf install -y curl wget git vim htop net-tools bind-utils dnf-plugins-core
log_success "Système mis à jour"

###############################################################################
# 2. INSTALLATION DOCKER
###############################################################################
echo ""
log_info "Étape 2/10: Installation de Docker"

# Supprimer les anciennes versions
sudo dnf remove -y docker docker-client docker-common docker-latest podman runc 2>/dev/null || true

# Ajouter le repository Docker
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

# Installer Docker
sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Démarrer Docker
sudo systemctl start docker
sudo systemctl enable docker

# Ajouter l'utilisateur au groupe docker
sudo usermod -aG docker $USER

log_success "Docker installé"
docker --version
docker compose version

###############################################################################
# 3. CONFIGURATION FIREWALL
###############################################################################
echo ""
log_info "Étape 3/10: Configuration du Firewall"

# Démarrer firewalld
sudo systemctl start firewalld
sudo systemctl enable firewalld

# Autoriser les ports nécessaires
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https

# Rich rule pour limiter SSH (protection brute force)
sudo firewall-cmd --permanent --add-rich-rule='rule service name="ssh" limit value="10/m" accept'

# Recharger
sudo firewall-cmd --reload

log_success "Firewall configuré"
sudo firewall-cmd --list-all

###############################################################################
# 4. CONFIGURATION SELINUX
###############################################################################
echo ""
log_info "Étape 4/10: Configuration SELinux"

CURRENT_SELINUX=$(getenforce)
log_info "SELinux actuel: $CURRENT_SELINUX"

echo "Choisissez l'option SELinux:"
echo "  1) Désactiver (plus simple, moins sécurisé)"
echo "  2) Configurer pour Docker (recommandé pour production)"
read -p "Option (1/2): " selinux_choice

if [ "$selinux_choice" = "1" ]; then
    sudo setenforce 0
    sudo sed -i 's/^SELINUX=enforcing/SELINUX=disabled/' /etc/selinux/config
    log_success "SELinux désactivé (redémarrage requis pour appliquer définitivement)"
else
    sudo dnf install -y policycoreutils-python-utils
    sudo setsebool -P container_manage_cgroup on
    sudo setsebool -P container_connect_any on
    log_success "SELinux configuré pour Docker"
fi

###############################################################################
# 5. CONFIGURATION SWAP
###############################################################################
echo ""
log_info "Étape 5/10: Configuration du Swap"

if [ -f /swapfile ]; then
    log_warning "Swap déjà configuré, passage à l'étape suivante"
else
    sudo fallocate -l 4G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
    log_success "Swap de 4GB créé"
fi

free -h

###############################################################################
# 6. OPTIMISATIONS SYSTÈME
###############################################################################
echo ""
log_info "Étape 6/10: Optimisations système"

# Limites de fichiers
sudo tee -a /etc/security/limits.conf << EOF
*  soft  nofile  65536
*  hard  nofile  65536
root soft nofile 65536
root hard nofile 65536
EOF

# Optimisations réseau et mémoire
sudo tee -a /etc/sysctl.conf << EOF
net.ipv4.ip_forward = 1
net.bridge.bridge-nf-call-iptables = 1
net.bridge.bridge-nf-call-ip6tables = 1
vm.max_map_count = 262144
vm.swappiness = 10
EOF

sudo sysctl -p
log_success "Optimisations appliquées"

###############################################################################
# 7. INSTALLATION FAIL2BAN
###############################################################################
echo ""
log_info "Étape 7/10: Installation de Fail2ban"

sudo dnf install -y fail2ban fail2ban-systemd

# Créer la configuration locale
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Activer la protection SSH
sudo tee -a /etc/fail2ban/jail.local << EOF

[sshd]
enabled = true
port = ssh
logpath = /var/log/secure
maxretry = 5
bantime = 3600
EOF

sudo systemctl enable fail2ban
sudo systemctl start fail2ban

log_success "Fail2ban configuré"
sudo fail2ban-client status

###############################################################################
# 8. CRÉATION DES RÉPERTOIRES
###############################################################################
echo ""
log_info "Étape 8/10: Création des répertoires"

sudo mkdir -p /var/www
sudo mkdir -p $BACKUP_DIR

# Permissions
sudo chown -R $USER:$USER /var/www
sudo chown -R $USER:$USER $BACKUP_DIR

log_success "Répertoires créés"

###############################################################################
# 9. INSTALLATION CERTBOT
###############################################################################
echo ""
log_info "Étape 9/10: Installation de Certbot"

sudo dnf install -y epel-release
sudo dnf install -y certbot

# Activer le timer de renouvellement
sudo systemctl enable certbot-renew.timer
sudo systemctl start certbot-renew.timer

log_success "Certbot installé"

###############################################################################
# 10. CONFIGURATION FINALE
###############################################################################
echo ""
log_info "Étape 10/10: Configuration finale"

# Configurer la rotation des logs Docker
sudo tee /etc/docker/daemon.json << EOF
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
EOF

# Redémarrer Docker pour appliquer
sudo systemctl restart docker

log_success "Configuration Docker appliquée"

###############################################################################
# RÉCAPITULATIF
###############################################################################
echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                                                               ║"
echo "║   INSTALLATION TERMINÉE !                                     ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

log_success "Le serveur est maintenant prêt pour le déploiement"
echo ""
echo "📋 PROCHAINES ÉTAPES:"
echo ""
echo "1. Cloner le projet dans /var/www:"
echo "   cd /var/www"
echo "   git clone https://github.com/votre-organisation/e-commerce-back.git"
echo "   cd e-commerce-back"
echo ""
echo "2. Configurer votre DNS (enregistrements A):"
echo "   api.votre-domaine.com  ->  IP_DU_SERVEUR"
echo "   minio.votre-domaine.com -> IP_DU_SERVEUR"
echo ""
echo "3. Obtenir les certificats SSL:"
echo "   sudo certbot certonly --standalone -d api.votre-domaine.com"
echo ""
echo "4. Générer les secrets:"
echo "   ./generate-secrets.sh > secrets.txt"
echo ""
echo "5. Configurer .env:"
echo "   cp .env.example .env"
echo "   vi .env  # Copier les secrets générés"
echo ""
echo "6. Configurer Nginx:"
echo "   cp docker/nginx/conf.d/production.conf.example docker/nginx/conf.d/production.conf"
echo "   vi docker/nginx/conf.d/production.conf  # Adapter au domaine"
echo ""
echo "7. Déployer:"
echo "   docker compose build"
echo "   docker compose -f docker-compose.yml -f docker-compose.production.yml up -d"
echo "   make migrate-all"
echo "   make seed-all"
echo "   make minio-workflow"
echo ""
echo "8. Vérifier:"
echo "   curl https://api.votre-domaine.com/health"
echo ""
echo "⚠️  IMPORTANT:"
echo "   - Changez TOUS les secrets par défaut"
echo "   - Configurez les backups automatiques"
echo "   - Testez le renouvellement SSL: sudo certbot renew --dry-run"
echo ""
echo "📚 Documentation:"
echo "   - Guide complet: docs/PRODUCTION_DEPLOYMENT_GUIDE.md"
echo "   - Guide CentOS: docs/PRODUCTION_CENTOS_GUIDE.md"
echo "   - Guide serveur: docs/DEPLOYMENT_SERVER_SETUP.md"
echo ""

log_info "Redémarrez le serveur pour appliquer tous les changements:"
echo "   sudo reboot"
echo ""
