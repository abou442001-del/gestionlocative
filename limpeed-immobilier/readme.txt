=== Limpeed Immobilier - Gestion Locative ===
Contributors: limpeed
Tags: immobilier, gestion locative, biens, locataires, propriétaires
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin de gestion locative pour l'agence Limpeed Immobilier : biens, propriétaires, locataires, suivi des paiements et bordereaux propriétaires.

== Description ==

Ce plugin permet à plusieurs agents connectés (comptes WordPress) de gérer :

* une base de propriétaires,
* une base de biens (appartements, maisons, studios, locaux commerciaux),
* une base de locataires,
* (Phase 2) le suivi des paiements de loyers,
* (Phase 3) la génération de bordereaux PDF pour les propriétaires,
* (Phase 4) la gestion fine des comptes agents.

**Les données ne sont jamais perdues lors des mises à jour.** Les tables SQL sont créées et migrées via `dbDelta()` avec un système de versioning de schéma (option `limpeed_db_version`). La désactivation du plugin ne supprime jamais de données ; seule une désinstallation explicitement confirmée dans Réglages peut le faire.

== Rôles et capacités ==

* `limpeed_agent` : gestion des propriétaires, biens, locataires et paiements.
* `limpeed_admin` : toutes les capacités agent + gestion des agents et des bordereaux.
* Les administrateurs WordPress natifs reçoivent automatiquement ces capacités.

== État d'avancement ==

* Phase 1 (en cours) : base de données biens/locataires/propriétaires, CRUD complet avec navigation croisée, recherche et filtres.
* Phase 2 : suivi des paiements et tableau de bord financier.
* Phase 3 : bordereaux PDF (Dompdf).
* Phase 4 : gestion fine des comptes agents et journal d'activité.

== Installation ==

1. Copier le dossier `limpeed-immobilier` dans `wp-content/plugins/`.
2. Activer le plugin depuis l'administration WordPress.
3. Les rôles `limpeed_agent` et `limpeed_admin` ainsi que les tables SQL sont créés automatiquement.
4. Accéder au menu "Limpeed Immobilier" pour gérer propriétaires, biens et locataires.

== Changelog ==

= 1.0.0 =
* Phase 1 : structure du plugin, activation/migration de la base de données, rôles et capacités, CRUD Propriétaires/Biens/Locataires avec navigation croisée, recherche et filtres, page de réglages pour la confirmation de suppression des données.
