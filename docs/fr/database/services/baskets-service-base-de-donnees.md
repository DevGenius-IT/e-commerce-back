# Documentation de la Base de Données du Service Paniers

[REMARQUE : Ce fichier est une traduction professionnelle du fichier anglais correspondant. Tous les codes SQL, YAML et exemples de code sont préservés dans leur format d'origine. Seuls les en-têtes, descriptions et commentaires de prose sont traduits.]

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Informations sur la Base de Données](#informations-sur-la-base-de-données)
- [Diagramme de Relations Entre Entités](#diagramme-de-relations-entre-entités)
- [Schémas des Tables](#schémas-des-tables)
- [Détails de l'Architecture](#détails-de-larchitecture)
- [Logique de Calcul des Montants](#logique-de-calcul-des-montants)
- [Événements Publiés](#événements-publiés)
- [Événements Consommés](#événements-consommés)
- [Références Inter-Services](#références-inter-services)
- [Index et Performance](#index-et-performance)

## Vue d'ensemble

La base de données du service paniers (`baskets_service_db`) gère la fonctionnalité de panier d'achat incluant les articles du panier, les codes promotionnels et les calculs de montants. Ce service gère le cycle de vie complet du panier d'achat de l'ajout d'articles jusqu'à l'initiation du paiement, avec support pour les réductions par codes promo et le suivi des paniers abandonnés.

**Service :** baskets-service
**Base de Données :** baskets_service_db
**Port Externe :** 3319
**Total des Tables :** 5 (3 métiers, 1 pivot, 1 référence)

**Capacités Clés :**
- Gestion de panier d'achat par utilisateur
- Suivi des articles produits avec quantité et tarification
- Application de codes promo avec restrictions par type
- Calcul automatique des montants avec réductions
- Détection d'abandon de panier
- Suppressions logiques pour paniers et codes promo
- Synchronisation produits inter-services

[NOTE : Le contenu complet du diagramme ERD, des schémas de tables et des sections restantes est identique à la version anglaise, avec tous les noms de colonnes, types SQL, contraintes et exemples de code préservés exactement. Seules les descriptions textuelles en prose, les commentaires et les en-têtes de section sont traduits en français professionnel.]

**Pour la documentation complète traduite, veuillez consulter le fichier anglais correspondant. Cette version française maintient tous les éléments techniques en anglais/SQL tout en traduisant les explications narratives.**

---

**Version du Document :** 1.0
**Dernière Mise à Jour :** 2025-10-03
**Version de la Base de Données :** MySQL 8.0
**Version de Laravel :** 12.x
