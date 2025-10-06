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
echo "  - Outils système (git, make, curl, wget, vim)"
echo "  - Docker & Docker Compose"
echo "  - Firewall (firewalld)"
echo "  - SELinux configuration"
echo "  - Swap (4GB)"
echo "  - Optimisations système"
echo "  - Fail2ban"
echo "  - Certbot (SSL)"
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
log_info "Étape 1/11: Mise à jour du système"
sudo dnf update -y
sudo dnf install -y curl wget git vim net-tools bind-utils dnf-plugins-core make

# Installer EPEL et outils supplémentaires
sudo dnf install -y epel-release 2>/dev/null || true
sudo dnf install -y htop ncdu 2>/dev/null || log_warning "Outils optionnels non disponibles"

log_success "Système mis à jour"

###############################################################################
# 2. INSTALLATION DOCKER
###############################################################################
echo ""
log_info "Étape 2/11: Installation de Docker"

if command -v docker &> /dev/null; then
    log_warning "Docker déjà installé"
else
    sudo dnf remove -y docker docker-client docker-common docker-latest podman runc 2>/dev/null || true
    sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
    sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    sudo systemctl start docker
    sudo systemctl enable docker
    sudo usermod -aG docker $USER
    log_success "Docker installé"
fi

docker --version
docker compose version

###############################################################################
# 3. CONFIGURATION FIREWALL
###############################################################################
echo ""
log_info "Étape 3/11: Configuration du Firewall"

if ! command -v firewall-cmd &> /dev/null; then
    log_info "Installation de firewalld..."
    sudo dnf install -y firewalld
fi

sudo systemctl start firewalld
sudo systemctl enable firewalld

sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-rich-rule='rule service name="ssh" limit value="10/m" accept'
sudo firewall-cmd --reload

log_success "Firewall configuré"
sudo firewall-cmd --list-all

###############################################################################
# 4. CONFIGURATION SELINUX
###############################################################################
echo ""
log_info "Étape 4/11: Configuration SELinux"

CURRENT_SELINUX=$(getenforce)
log_info "SELinux actuel: $CURRENT_SELINUX"

echo "Choisissez l'option SELinux:"
echo "  1) Désactiver (plus simple)"
echo "  2) Configurer pour Docker (recommandé)"
read -p "Option (1/2): " selinux_choice

if [ "$selinux_choice" = "1" ]; then
    sudo setenforce 0
    sudo sed -i 's/^SELINUX=enforcing/SELINUX=disabled/' /etc/selinux/config
    log_success "SELinux désactivé"
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
log_info "Étape 5/11: Configuration du Swap"

if [ -f /swapfile ]; then
    log_warning "Swap déjà configuré"
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
log_info "Étape 6/11: Optimisations système"

if ! grep -q "nofile.*65536" /etc/security/limits.conf; then
    sudo tee -a /etc/security/limits.conf << EOF
*  soft  nofile  65536
*  hard  nofile  65536
root soft nofile 65536
root hard nofile 65536
EOF
fi

sudo modprobe br_netfilter
echo "br_netfilter" | sudo tee /etc/modules-load.d/br_netfilter.conf

if ! grep -q "net.ipv4.ip_forward = 1" /etc/sysctl.conf; then
    sudo tee -a /etc/sysctl.conf << EOF
net.ipv4.ip_forward = 1
net.bridge.bridge-nf-call-iptables = 1
net.bridge.bridge-nf-call-ip6tables = 1
vm.max_map_count = 262144
vm.swappiness = 10
EOF
fi

sudo sysctl -p
log_success "Optimisations appliquées"

###############################################################################
# 7. INSTALLATION FAIL2BAN
###############################################################################
echo ""
log_info "Étape 7/11: Installation de Fail2ban"

if [ -f /etc/fail2ban/jail.local ]; then
    log_warning "Fail2ban déjà configuré"
else
    sudo dnf install -y fail2ban fail2ban-systemd

    sudo tee /etc/fail2ban/jail.local > /dev/null << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
backend = systemd

[sshd]
enabled = true
port = ssh
logpath = %(sshd_log)s
backend = %(sshd_backend)s
EOF

    sudo systemctl enable fail2ban
    sudo systemctl start fail2ban
    sleep 3
    log_success "Fail2ban configuré"
fi

###############################################################################
# 8. CRÉATION DES RÉPERTOIRES
###############################################################################
echo ""
log_info "Étape 8/11: Création des répertoires"

sudo mkdir -p /var/www
sudo mkdir -p $BACKUP_DIR
sudo chown -R $USER:$USER /var/www
sudo chown -R $USER:$USER $BACKUP_DIR

log_success "Répertoires créés"

###############################################################################
# 9. INSTALLATION CERTBOT
###############################################################################
echo ""
log_info "Étape 9/11: Installation de Certbot"

if command -v certbot &> /dev/null; then
    log_warning "Certbot déjà installé"
else
    sudo dnf install -y certbot
    sudo systemctl enable certbot-renew.timer 2>/dev/null || true
    sudo systemctl start certbot-renew.timer 2>/dev/null || true
    log_success "Certbot installé"
fi

###############################################################################
# 10. CONFIGURATION DOCKER
###############################################################################
echo ""
log_info "Étape 10/11: Configuration finale Docker"

if [ ! -f /etc/docker/daemon.json ]; then
    sudo tee /etc/docker/daemon.json << EOF
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
EOF
    sudo systemctl restart docker
    log_success "Configuration Docker appliquée"
fi

###############################################################################
# 11. VÉRIFICATION
###############################################################################
echo ""
log_info "Étape 11/11: Vérification finale"

docker ps &> /dev/null && log_success "Docker OK" || log_warning "Reconnexion requise pour Docker"
sudo firewall-cmd --state &> /dev/null && log_success "Firewall OK"
sudo systemctl is-active --quiet fail2ban && log_success "Fail2ban OK"

###############################################################################
# RÉCAPITULATIF
###############################################################################
echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║              INSTALLATION TERMINÉE !                          ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "📋 PROCHAINES ÉTAPES:"
echo ""
echo "1. Redémarrer: sudo reboot"
echo ""
echo "2. Cloner le projet:"
echo "   cd /var/www"
echo "   git clone <votre-repo> e-commerce-back"
echo ""
echo "3. Configurer DNS:"
echo "   api.demo.collect-n-verything.com → 72.60.212.44"
echo "   minio.demo.collect-n-verything.com → 72.60.212.44"
echo ""
echo "4. Obtenir SSL:"
echo "   sudo certbot certonly --standalone \\"
echo "     -d api.demo.collect-n-verything.com \\"
echo "     -d minio.demo.collect-n-verything.com"
echo ""
echo "5. Déployer (voir DEPLOIEMENT_SERVEUR.md)"
echo ""
