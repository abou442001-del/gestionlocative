=== Limpeed Immobilier - Gestion Locative ===
Contributors: limpeed
Tags: immobilier, gestion locative, biens, locataires, propriétaires
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin de gestion locative pour l'agence Limpeed Immobilier : biens, propriétaires, locataires, suivi des paiements et bordereaux propriétaires.

== Description ==

Ce plugin permet à plusieurs agents connectés (comptes WordPress) de gérer :

* une base de propriétaires,
* une base de biens (appartements, maisons, studios, locaux commerciaux),
* une base de locataires,
* le suivi des paiements de loyers avec tableau de bord des impayés,
* la génération de bordereaux PDF pour les propriétaires (loyers encaissés − commission agence = net à reverser),
* la gestion des comptes agents et un journal d'activité.

**Les données ne sont jamais perdues lors des mises à jour.** Les tables SQL sont créées et migrées via `dbDelta()` avec un système de versioning de schéma (option `limpeed_db_version`). La désactivation du plugin ne supprime jamais de données ; seule une désinstallation explicitement confirmée dans Réglages peut le faire.

== Rôles et capacités ==

* `limpeed_agent` : gestion des propriétaires, biens, locataires et paiements.
* `limpeed_admin` : toutes les capacités agent + gestion des agents, des bordereaux et consultation du journal d'activité.
* Les administrateurs WordPress natifs reçoivent automatiquement ces capacités.
* Retirer l'accès à un agent ne supprime jamais son compte WordPress ni les données qu'il a créées : seul son rôle Limpeed est retiré.

== Bordereaux PDF ==

Générés via la librairie [Dompdf](https://github.com/dompdf/dompdf) (incluse dans `vendor/`, installée via Composer). Les PDF sont stockés dans `wp-content/uploads/limpeed-statements/`, un dossier protégé contre l'accès web direct (`.htaccess`) ; ils ne sont téléchargeables que depuis l'administration, après vérification des capacités et d'un nonce.

== État d'avancement ==

* Phase 1 : base de données biens/locataires/propriétaires, CRUD complet avec navigation croisée, recherche et filtres.
* Phase 2 : suivi des paiements et tableau de bord financier.
* Phase 3 : bordereaux PDF (Dompdf), historique et téléchargement sécurisé.
* Phase 4 : gestion des comptes agents (création, changement de rôle, révocation d'accès) et journal d'activité.

== Installation ==

1. Copier le dossier `limpeed-immobilier` dans `wp-content/plugins/` (le dossier `vendor/` contenant Dompdf est déjà inclus).
2. Activer le plugin depuis l'administration WordPress.
3. Les rôles `limpeed_agent` et `limpeed_admin` ainsi que les tables SQL sont créés automatiquement.
4. Accéder au menu "Limpeed Immobilier" pour gérer propriétaires, biens, locataires, paiements, bordereaux et agents.

== Changelog ==

= 1.2.0 =
* Phase 3 : table `wp_limpeed_statements`, génération de bordereaux PDF par propriétaire sur une période choisie, historique téléchargeable.
* Phase 4 : table `wp_limpeed_activity_log`, page Agents (création de comptes, changement de rôle, révocation d'accès non destructive), page Journal d'activité, instrumentation du journal sur toutes les actions de création/modification/suppression.

= 1.1.0 =
* Phase 2 : table `wp_limpeed_payments`, page Paiements, indicateurs financiers sur le tableau de bord, navigation croisée vers l'historique de paiement.

= 1.0.0 =
* Phase 1 : structure du plugin, activation/migration de la base de données, rôles et capacités, CRUD Propriétaires/Biens/Locataires avec navigation croisée, recherche et filtres, page de réglages pour la confirmation de suppression des données.
