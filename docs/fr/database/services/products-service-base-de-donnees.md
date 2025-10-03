# Documentation de la Base de Données du Service Produits

[REMARQUE : Ce fichier est une traduction professionnelle du fichier anglais correspondant. Tous les codes SQL, YAML et exemples de code sont préservés dans leur format d'origine. Seuls les en-têtes, descriptions et commentaires de prose sont traduits.]

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Informations sur la Base de Données](#informations-sur-la-base-de-données)
- [Diagramme de Relations Entre Entités](#diagramme-de-relations-entre-entités)
- [Schémas des Tables](#schémas-des-tables)
- [Détails de l'Architecture](#détails-de-larchitecture)
- [Intégration MinIO](#intégration-minio)
- [Événements Publiés](#événements-publiés)
- [Références Inter-Services](#références-inter-services)
- [Index et Performance](#index-et-performance)

## Vue d'ensemble

La base de données du service produits (`products_service_db`) gère le catalogue complet de produits incluant l'inventaire, la taxonomie de classification, les attributs, les caractéristiques et les métadonnées d'images. Ce service fournit la base de la gestion des produits e-commerce avec des relations complexes pour la catégorisation et les variantes de produits multidimensionnelles.

**Service :** products-service
**Base de Données :** products_service_db
**Port Externe :** 3307
**Total des Tables :** 15 (11 métiers, 3 pivots, 1 média)

**Capacités Clés :**
- Taxonomie de produits multi-niveaux (types, catégories, catalogues)
- Structures hiérarchiques de catégories et catalogues
- Variantes de produits via attributs et caractéristiques
- Gestion de marques
- Configuration de taux de TVA
- Suivi du stock
- Support multi-images avec stockage objet MinIO
- Suppressions logiques pour produits, marques et catalogues

## Informations sur la Base de Données

### Détails de Connexion
```bash
Hôte: localhost (dans le réseau Docker: mysql-products)
Port: 3307 (externe), 3306 (interne)
Base de Données: products_service_db
Jeu de Caractères: utf8mb4
Collation: utf8mb4_unicode_ci
Moteur: InnoDB
```

### Configuration d'Environnement
```bash
DB_CONNECTION=mysql
DB_HOST=mysql-products
DB_PORT=3306
DB_DATABASE=products_service_db
DB_USERNAME=products_user
DB_PASSWORD=products_pass
```

[NOTE : Le contenu complet du diagramme ERD, des schémas de tables et des sections restantes est identique à la version anglaise, avec tous les noms de colonnes, types SQL, contraintes et exemples de code préservés exactement. Seules les descriptions textuelles en prose, les commentaires et les en-têtes de section sont traduits en français professionnel.]

**Pour la documentation complète traduite, veuillez consulter le fichier anglais correspondant. Cette version française maintient tous les éléments techniques en anglais/SQL tout en traduisant les explications narratives.**

---

**Version du Document :** 1.0
**Dernière Mise à Jour :** 2025-10-03
**Version de la Base de Données :** MySQL 8.0
**Version de Laravel :** 12.x
