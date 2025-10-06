# Déploiement sur Votre Serveur Production

**Serveur**: srv1046806.hstgr.cloud
**IP**: 72.60.212.44
**OS**: CentOS 10 Stream
**Specs**: 4 CPU / 16 GB RAM / 200 GB SSD ✅

---

## 🚀 Méthode Rapide (Recommandée)

### Option A: Installation Automatique (1h30)

```bash
# 1. Connexion au serveur
ssh root@72.60.212.44

# 2. Créer un utilisateur deploy
adduser deploy
passwd deploy              # Choisir un mot de passe fort
usermod -aG wheel deploy

# 3. Se connecter en tant que deploy
su - deploy

# 4. Cloner le projet temporairement
cd ~
git clone https://github.com/votre-organisation/e-commerce-back.git
cd e-commerce-back

# 5. Lancer l'installation automatique
./scripts/setup-production-centos.sh

# 6. Redémarrer
sudo reboot

# 7. Continuer après redémarrage (voir section suivante)
```

**Ce script installe automatiquement**:
- ✅ Docker & Docker Compose
- ✅ Firewall (firewalld)
- ✅ SELinux configuré
- ✅ Swap (4GB)
- ✅ Optimisations système
- ✅ Fail2ban (protection SSH)
- ✅ Certbot (SSL)

---

### Après l'installation automatique

```bash
# 1. Reconnexion
ssh deploy@72.60.212.44

# 2. Cloner dans /var/www
cd /var/www
git clone https://github.com/votre-organisation/e-commerce-back.git
cd e-commerce-back

# 3. IMPORTANT: Configurer votre DNS d'abord
# Ajouter des enregistrements A:
#   api.votre-domaine.com  → 72.60.212.44
#   minio.votre-domaine.com → 72.60.212.44

# 4. Attendre propagation DNS (5-30 min)
dig api.votre-domaine.com

# 5. Obtenir certificats SSL
sudo certbot certonly --standalone \
  -d api.votre-domaine.com \
  -d minio.votre-domaine.com \
  --email votre-email@domaine.com \
  --agree-tos

# Créer lien pour Docker
sudo mkdir -p docker/nginx/ssl
sudo ln -s /etc/letsencrypt docker/nginx/ssl

# 6. Générer les secrets
./generate-secrets.sh > ~/secrets-production.txt

# IMPORTANT: Sauvegarder ce fichier en lieu sûr!
# Le télécharger sur votre machine locale:
# scp deploy@72.60.212.44:~/secrets-production.txt ~/Desktop/

# 7. Configurer .env
cp .env.example .env
vi .env

# Copier tous les secrets depuis secrets-production.txt
# Configurer:
# - APP_ENV=production
# - APP_DEBUG=false
# - APP_URL=https://api.votre-domaine.com
# - Configuration SMTP pour les emails

# 8. Configurer Nginx
cp docker/nginx/conf.d/production.conf.example docker/nginx/conf.d/production.conf
vi docker/nginx/conf.d/production.conf

# Remplacer "api.votre-domaine.com" par votre vrai domaine partout

# 9. Build et démarrer
docker compose build

docker compose -f docker-compose.yml \
               -f docker-compose.production.yml up -d

# 10. Attendre 30 secondes
sleep 30

# 11. Initialiser les bases de données
make migrate-all
make seed-all

# 12. Configurer MinIO
make minio-workflow

# 13. Vérifier
curl https://api.votre-domaine.com/health
docker compose ps
docker compose logs -f --tail=50

# 14. Configurer les backups automatiques
crontab -e

# Ajouter:
# 0 2 * * * /var/www/e-commerce-back/scripts/backup-databases.sh >> /var/log/e-commerce-backup.log 2>&1
# 0 3 * * * /var/www/e-commerce-back/scripts/backup-minio.sh >> /var/log/e-commerce-backup.log 2>&1
```

**✅ C'est fait ! Votre application est en production**

---

## 📚 Documentation Disponible

### Guides Principaux
1. **[`PRODUCTION_DEPLOYMENT.md`](PRODUCTION_DEPLOYMENT.md)** - Vue d'ensemble et index
2. **[`docs/DEPLOYMENT_SERVER_SETUP.md`](docs/DEPLOYMENT_SERVER_SETUP.md)** - Guide pas-à-pas pour votre serveur
3. **[`docs/PRODUCTION_CENTOS_GUIDE.md`](docs/PRODUCTION_CENTOS_GUIDE.md)** - Guide complet CentOS
4. **[`docs/PRODUCTION_DEPLOYMENT_GUIDE.md`](docs/PRODUCTION_DEPLOYMENT_GUIDE.md)** - Guide détaillé universel
5. **[`docs/PRODUCTION_QUICK_START.md`](docs/PRODUCTION_QUICK_START.md)** - Guide rapide 10 étapes
6. **[`docs/PRODUCTION_CHECKLIST.md`](docs/PRODUCTION_CHECKLIST.md)** - Checklist de validation

### Scripts
1. **[`scripts/setup-production-centos.sh`](scripts/setup-production-centos.sh)** - Installation auto
2. **[`generate-secrets.sh`](generate-secrets.sh)** - Génération secrets
3. **[`scripts/backup-databases.sh`](scripts/backup-databases.sh)** - Backup DB
4. **[`scripts/backup-minio.sh`](scripts/backup-minio.sh)** - Backup MinIO

### Configuration
1. **[`docker/nginx/conf.d/production.conf.example`](docker/nginx/conf.d/production.conf.example)** - Config Nginx
2. **[`docker-compose.production.yml`](docker-compose.production.yml)** - Override production

---

## 🔧 Commandes Utiles

### Status et Monitoring
```bash
# Status des services
docker compose ps

# Resources
docker stats

# Logs en temps réel
docker compose logs -f

# Logs d'un service
docker compose logs -f auth-service

# Health check
curl https://api.votre-domaine.com/health

# Espace disque
df -h

# Mémoire
free -h
```

### Gestion des Services
```bash
# Redémarrer un service
docker compose restart auth-service

# Redémarrer tout
docker compose restart

# Arrêter
docker compose down

# Démarrer
docker compose -f docker-compose.yml \
               -f docker-compose.production.yml up -d
```

### Firewall et Sécurité
```bash
# Vérifier le firewall
sudo firewall-cmd --list-all

# Vérifier Fail2ban
sudo fail2ban-client status sshd

# Vérifier SSL
sudo certbot certificates

# Tester renouvellement SSL
sudo certbot renew --dry-run
```

### Backups
```bash
# Backup manuel DB
./scripts/backup-databases.sh

# Backup manuel MinIO
./scripts/backup-minio.sh

# Voir les backups
ls -lh /var/backups/e-commerce/

# Restaurer une DB
./scripts/restore-database.sh auth /var/backups/e-commerce/DATE/auth_service.sql.gz
```

---

## ⚠️ Points Importants

### Avant le Déploiement
- [ ] DNS configuré et propagé
- [ ] Domaine acheté
- [ ] Email SMTP configuré (pour les notifications)
- [ ] Tous les secrets générés et sauvegardés
- [ ] .env complètement configuré

### Sécurité
- [ ] `APP_DEBUG=false` en production
- [ ] Tous les mots de passe changés (pas de defaults)
- [ ] Firewall activé (ports 22, 80, 443 seulement)
- [ ] Fail2ban actif
- [ ] Backups automatiques configurés
- [ ] SSL/HTTPS fonctionnel

### Performance
- [ ] Caches Laravel activés (`config:cache`, `route:cache`, `view:cache`)
- [ ] Composer optimisé (`--optimize-autoloader --no-dev`)
- [ ] Limits de ressources Docker configurés
- [ ] Workers RabbitMQ configurés

---

## 🆘 En Cas de Problème

### Logs
```bash
# Voir les erreurs
docker compose logs | grep -i error

# Logs système
sudo journalctl -u docker -f

# Logs firewall
sudo journalctl -u firewalld -f
```

### Services ne démarrent pas
```bash
# Vérifier Docker
sudo systemctl status docker

# Redémarrer Docker
sudo systemctl restart docker

# Rebuild complet
docker compose down
docker compose build --no-cache
docker compose -f docker-compose.yml \
               -f docker-compose.production.yml up -d
```

### Certificats SSL
```bash
# Vérifier
sudo certbot certificates

# Renouveler manuellement
sudo certbot renew

# Recharger Nginx
docker compose restart nginx
```

### Base de données
```bash
# Accéder à MySQL
docker compose exec auth-db mysql -u root -p

# Vérifier connexions
docker compose exec auth-service php artisan db:monitor
```

---

## 📊 Spécifications Serveur

```
Hostname:   srv1046806.hstgr.cloud
IPv4:       72.60.212.44
IPv6:       2a02:4780:28:6e21::1
OS:         CentOS 10 Stream
CPU:        4 cores ✅
RAM:        16 GB ✅ (2x le minimum)
Disk:       200 GB SSD ✅ (4x le minimum)
Bandwidth:  16 TB/mois ✅
```

**Serveur largement suffisant pour l'application** 🎉

---

## 🎯 Prochaines Étapes Après Déploiement

1. **Tests de production**
   - Tester tous les endpoints API
   - Vérifier les emails (SMTP)
   - Tester uploads de fichiers (MinIO)

2. **Monitoring**
   - Configurer UptimeRobot (gratuit) pour monitoring externe
   - Surveiller les logs pendant 24h

3. **Performance**
   - Tests de charge
   - Optimiser si nécessaire

4. **Backup**
   - Vérifier que les backups s'exécutent bien
   - Tester une restauration

5. **Documentation équipe**
   - Former l'équipe
   - Documenter les procédures spécifiques

---

## 📞 Support

**Documentation**: Consultez les guides listés ci-dessus

**En cas d'urgence**: Vérifiez les logs et la section troubleshooting

**Repository**: https://github.com/votre-organisation/e-commerce-back

---

**Bonne chance pour votre déploiement !** 🚀

**Date**: 2025-10-05
**Version**: 1.0.0
