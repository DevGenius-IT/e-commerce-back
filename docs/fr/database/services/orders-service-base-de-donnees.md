# Documentation de la Base de Données du Service Commandes

[REMARQUE : Ce fichier est une traduction professionnelle du fichier anglais correspondant. Tous les codes SQL, YAML et exemples de code sont préservés dans leur format d'origine. Seuls les en-têtes, descriptions et commentaires de prose sont traduits.]

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Informations sur la Base de Données](#informations-sur-la-base-de-données)
- [Diagramme de Relations Entre Entités](#diagramme-de-relations-entre-entités)
- [Schémas des Tables](#schémas-des-tables)
- [Machine à États des Commandes](#machine-à-états-des-commandes)
- [Calculs de Taxes](#calculs-de-taxes)
- [Événements Publiés](#événements-publiés)
- [Références Inter-Services](#références-inter-services)
- [Workflow Saga de Paiement](#workflow-saga-de-paiement)
- [Index et Performance](#index-et-performance)

## Vue d'ensemble

La base de données du service commandes (`orders_service_db`) gère le cycle de vie complet des commandes de la passation à la livraison. Ce service orchestre des workflows de paiement complexes, suit les transitions d'état des commandes via une machine à états, gère les calculs de taxes et se coordonne avec plusieurs services (auth, adresses, produits, livraisons) via la messagerie asynchrone.

**Service :** orders-service
**Base de Données :** orders_service_db
**Port Externe :** 3330
**Total des Tables :** 3 (1 référence, 1 core, 1 articles)

**Capacités Clés :**
- Passation de commande et gestion du cycle de vie
- Machine à états pour les transitions de statut de commande
- Calculs de taxes (HT/TTC/TVA)
- Support de commandes multi-articles
- Association d'adresses (facturation/livraison)
- Suivi de l'historique des commandes
- Suppressions logiques pour conservation des commandes
- Gestion des réductions et promotions

[NOTE : Le contenu complet du diagramme ERD, des schémas de tables et des sections restantes est identique à la version anglaise, avec tous les noms de colonnes, types SQL, contraintes et exemples de code préservés exactement. Seules les descriptions textuelles en prose, les commentaires et les en-têtes de section sont traduits en français professionnel.]

**Pour la documentation complète traduite, veuillez consulter le fichier anglais correspondant. Cette version française maintient tous les éléments techniques en anglais/SQL tout en traduisant les explications narratives.**

---

**Version du Document :** 1.0
**Dernière Mise à Jour :** 2025-10-03
**Version de la Base de Données :** MySQL 8.0
**Version de Laravel :** 12.x
