=== Limpeed Immobilier - Gestion Locative ===
Contributors: limpeed
Tags: immobilier, gestion locative, biens, locataires, propriétaires
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin de gestion locative pour l'agence Limpeed Immobilier : biens, propriétaires, locataires, suivi des paiements et bordereaux propriétaires.

== Description ==

Ce plugin permet à plusieurs agents connectés (comptes WordPress) de gérer :

* une base de propriétaires,
* les édifices de chaque propriétaire (un propriétaire peut posséder plusieurs édifices),
* les sous-édifices (biens : appartements, maisons, studios, locaux commerciaux) composant chaque édifice,
* une base de locataires, rattachés en cascade propriétaire → édifice → sous-édifice,
* le suivi des paiements de loyers avec tableau de bord des impayés,
* la génération de bordereaux PDF pour les propriétaires (loyers encaissés − commission agence = net à reverser),
* la gestion des comptes agents et un journal d'activité.

**Les données ne sont jamais perdues lors des mises à jour.** Les tables SQL sont créées et migrées via `dbDelta()` avec un système de versioning de schéma (option `limpeed_db_version`). La désactivation du plugin ne supprime jamais de données ; seule une désinstallation explicitement confirmée dans Réglages peut le faire.

== Rôles et capacités ==

* `limpeed_agent` : gestion des propriétaires, biens, locataires et paiements.
* `limpeed_admin` : toutes les capacités agent + gestion des agents, des bordereaux et consultation du journal d'activité.
* Les administrateurs WordPress natifs reçoivent automatiquement ces capacités.
* Retirer l'accès à un agent ne supprime jamais son compte WordPress ni les données qu'il a créées : seul son rôle Limpeed est retiré.

== Hiérarchie Propriétaire → Édifice → Sous-édifice ==

* Un **propriétaire** peut posséder plusieurs **édifices** (immeubles, résidences...).
* Un **édifice** est composé d'un ou plusieurs **sous-édifices** (ce sont les biens loués à proprement parler : appartements, studios, locaux...).
* Un **locataire** est rattaché à un sous-édifice ; le formulaire d'ajout d'un locataire propose une sélection en cascade : propriétaire → édifice → sous-édifice.
* Le propriétaire d'un bien est toujours déterminé automatiquement à partir de son édifice (jamais saisi manuellement sur le bien), pour garantir la cohérence des données.
* **Migration automatique et non destructive** : les biens créés avant l'introduction des édifices (versions < 1.4.0) sont automatiquement rattachés à un édifice "Bâtiment principal" généré pour chaque propriétaire concerné — aucune donnée existante n'est perdue ni à ressaisir.

== Pages frontend (connexion / inscription) ==

À l'activation, le plugin crée automatiquement deux pages WordPress (si elles n'existent pas déjà) :

* **Connexion Agent** (`[limpeed_login]`) : formulaire de connexion frontend basé sur `wp_signon()`.
* **Inscription Agent** (`[limpeed_register]`) : un candidat crée un compte, mais **aucun accès n'est accordé automatiquement**. Le compte est créé sans aucun rôle Limpeed et apparaît dans Agents > Demandes en attente, où un `limpeed_admin` doit explicitement l'approuver (en choisissant son rôle) ou le rejeter (le compte est alors supprimé, ce qui est sans risque puisqu'il n'a jamais eu la capacité de créer la moindre donnée).
* Ces pages ne sont jamais recréées si elles existent déjà (vous pouvez déplacer leur contenu dans un thème/page existante en réutilisant simplement les shortcodes).
* Un formulaire d'inscription inclut un champ piège à robots (honeypot) pour limiter le spam basique.

== Bordereaux PDF ==

Générés via la librairie [Dompdf](https://github.com/dompdf/dompdf) (incluse dans `vendor/`, installée via Composer). Les PDF sont stockés dans `wp-content/uploads/limpeed-statements/`, un dossier protégé contre l'accès web direct (`.htaccess`) ; ils ne sont téléchargeables que depuis l'administration, après vérification des capacités et d'un nonce.

**Hébergement Nginx :** Nginx ignore les fichiers `.htaccess`. Si votre site tourne sous Nginx, ajoutez ce bloc à la configuration du serveur pour obtenir la même protection :

`
location ^~ /wp-content/uploads/limpeed-statements/ {
    deny all;
    return 404;
}
`

== État d'avancement ==

* Phase 1 : base de données biens/locataires/propriétaires, CRUD complet avec navigation croisée, recherche et filtres.
* Phase 2 : suivi des paiements et tableau de bord financier.
* Phase 3 : bordereaux PDF (Dompdf), historique et téléchargement sécurisé.
* Phase 4 : gestion des comptes agents (création, changement de rôle, révocation d'accès) et journal d'activité.
* Pages frontend de connexion et d'inscription (avec validation administrateur des inscriptions).

== Installation ==

1. Copier le dossier `limpeed-immobilier` dans `wp-content/plugins/` (le dossier `vendor/` contenant Dompdf est déjà inclus).
2. Activer le plugin depuis l'administration WordPress.
3. Les rôles `limpeed_agent` et `limpeed_admin` ainsi que les tables SQL sont créés automatiquement.
4. Accéder au menu "Limpeed Immobilier" pour gérer propriétaires, biens, locataires, paiements, bordereaux et agents.

== Changelog ==

= 1.4.0 =
* Nouvelle table `wp_limpeed_buildings` (Édifices) : un propriétaire peut posséder plusieurs édifices, chacun composé de plusieurs sous-édifices (biens).
* Page admin "Édifices" (CRUD complet), liens rapides depuis la fiche propriétaire ("Voir les édifices" / "Ajouter un édifice") et depuis la fiche édifice ("Voir les sous-édifices" / "Ajouter un sous-édifice").
* Le formulaire Biens sélectionne désormais un édifice (le propriétaire est déterminé automatiquement) au lieu d'un propriétaire direct.
* Le formulaire Locataires propose une sélection en cascade propriétaire → édifice → sous-édifice.
* Migration automatique et non destructive : les biens existants sont rattachés à un édifice "Bâtiment principal" généré pour leur propriétaire.

= 1.3.0 =
* Création automatique de 2 pages frontend à l'activation : Connexion Agent (`[limpeed_login]`) et Inscription Agent (`[limpeed_register]`).
* Inscription publique avec validation administrateur obligatoire : un compte inscrit en frontend n'a aucun rôle Limpeed tant qu'il n'est pas approuvé depuis Agents > Demandes en attente.
* Nouvelle section "Demandes en attente" sur la page Agents (approuver avec choix du rôle, ou rejeter).

= 1.2.0 =
* Phase 3 : table `wp_limpeed_statements`, génération de bordereaux PDF par propriétaire sur une période choisie, historique téléchargeable.
* Phase 4 : table `wp_limpeed_activity_log`, page Agents (création de comptes, changement de rôle, révocation d'accès non destructive), page Journal d'activité, instrumentation du journal sur toutes les actions de création/modification/suppression.

= 1.1.0 =
* Phase 2 : table `wp_limpeed_payments`, page Paiements, indicateurs financiers sur le tableau de bord, navigation croisée vers l'historique de paiement.

= 1.0.0 =
* Phase 1 : structure du plugin, activation/migration de la base de données, rôles et capacités, CRUD Propriétaires/Biens/Locataires avec navigation croisée, recherche et filtres, page de réglages pour la confirmation de suppression des données.
