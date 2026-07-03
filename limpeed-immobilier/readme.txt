=== Limpeed Immobilier - Gestion Locative ===
Contributors: limpeed
Tags: immobilier, gestion locative, biens, locataires, propriétaires
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.17.0
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

== Tableau de bord frontend (application autonome) ==

Une troisième page, **Tableau de bord** (`[limpeed_dashboard]`), est créée automatiquement à l'activation. C'est le début d'une bascule progressive de l'administration hors de wp-admin vers une interface "application" dédiée :

* La page est entièrement autonome : elle ignore le thème WordPress actif (aucun header/footer/style du thème) via le filtre `template_include`, et affiche son propre habillage (barre latérale, barre du haut, cartes).
* Accès réservé aux agents approuvés (capacité `manage_limpeed_properties`) : un visiteur non connecté est redirigé vers la page de connexion, un compte en attente d'approbation est bloqué avec message explicite.
* Contenu : indicateurs (propriétaires, édifices, biens, locataires), graphique de recouvrement des loyers sur 6 mois (total attendu / payé / impayé), listes des 10 derniers locataires, propriétaires, quittances soldées et quittances en attente.
* La barre latérale contient déjà les entrées Propriétaires / Édifices / Biens / Locataires / Paiements / Bordereaux : elles pointent pour l'instant vers leurs pages wp-admin respectives, en attendant leur migration progressive vers le frontend.

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
* Tableau de bord frontend en application autonome, première étape de la bascule progressive de l'administration hors de wp-admin.

== Installation ==

1. Copier le dossier `limpeed-immobilier` dans `wp-content/plugins/` (le dossier `vendor/` contenant Dompdf est déjà inclus).
2. Activer le plugin depuis l'administration WordPress.
3. Les rôles `limpeed_agent` et `limpeed_admin` ainsi que les tables SQL sont créés automatiquement.
4. Accéder au menu "Limpeed Immobilier" pour gérer propriétaires, biens, locataires, paiements, bordereaux et agents.

== Changelog ==

= 1.17.0 =
* Nouvelle API REST (`limpeed/v1`) sécurisée par les capacités du plugin et le nonce standard de l'API REST WordPress (`X-WP-Nonce`).
* Section Locataires (application frontend) entièrement dynamique : recherche et filtres (bien, statut) en direct sans rechargement de page, ajout/modification dans une fenêtre modale avec la cascade Propriétaire → Édifice → Sous-édifice peuplée en Ajax, suppression avec confirmation, notifications de succès/erreur, indicateurs de chargement (squelettes).
* Nouveau panneau de détail (clic sur le nom d'un locataire) avec trois onglets : Infos, Paiements, Historique du bail.
* Alpine.js est désormais embarqué localement dans le plugin (aucune dépendance à un CDN externe).
* La fiche complète d'un locataire (dossier, calendrier de paiement annuel) reste accessible depuis le panneau de détail pour une consultation approfondie.

= 1.16.0 =
* Paiements : ajout d'un champ de recherche par nom de locataire dans la liste des paiements (admin et frontend).
* Commission : la commission n'est plus saisie manuellement paiement par paiement, elle est calculée automatiquement selon le taux de commission (%) défini sur l'édifice du bien concerné. Chaque édifice peut avoir son propre taux, réglable depuis sa fiche.
* Toutes les sommes affichées dans le plugin (loyers, charges, dépôts, commissions, montants nets, avances, cautions) sont désormais exprimées en FCFA.
* Locataires : à la création d'un nouveau locataire, les mois d'avance configurés dans les réglages sont désormais automatiquement générés comme paiements "payés" dans son calendrier, à partir du mois de début de bail.
* Paiements : le nom de l'agent ayant enregistré ou modifié chaque paiement est désormais affiché dans les listes de paiements (admin et frontend).
* Biens : les types de biens sont remplacés par Studio, 2 pièces, 3 pièces et 4 pièces et plus. Les biens existants utilisant les anciens types (Appartement, Maison, Local commercial) restent valides et continuent de s'afficher correctement.

= 1.15.0 =
* Les sections Édifices, Biens et Locataires sont désormais rangées par propriétaire : chaque propriétaire apparaît comme un groupe distinct (avec un lien direct vers sa fiche), au lieu d'une liste plate mélangeant tout le monde. Les filtres de recherche existants continuent de fonctionner normalement au sein de cette nouvelle présentation.
* Correction : les formulaires de recherche/filtre (Propriétaires, Édifices, Biens, Locataires, Paiements, Bordereaux, Agents, Journal d'activité) perdaient le contexte de la page sur les sites utilisant la structure de permaliens "Simple", ce qui pouvait renvoyer vers une page vide après une recherche.

= 1.14.0 =
* Fiche propriétaire : la section Propriétaires affiche désormais une vue d'ensemble par propriétaire (clic sur "Voir") listant tous ses édifices, les biens de chaque édifice avec leur statut et leur locataire actuel, pour une navigation rangée par propriétaire au lieu de listes séparées.
* Réglages : nouveau champ "Mois de caution par défaut", utilisé pour calculer automatiquement le dépôt de garantie requis et le comparer au dépôt réellement versé (indicateur Suffisant/Insuffisant) sur chaque fiche locataire, à côté du suivi de l'avance de loyer.

= 1.13.0 =
* Correction : les biens n'ayant qu'un identifiant (sans adresse) s'affichaient avec un libellé vide dans les listes déroulantes (sélection du bien loué, filtres, bordereaux...), rendant l'option invisible/vide. Un libellé d'affichage centralisé (identifiant, sinon adresse, sinon repli générique) est désormais utilisé partout où un bien est référencé.

= 1.12.0 =
* Correction : l'ajout groupé de sous-édifices depuis le formulaire Édifice n'exige plus une adresse si un identifiant est renseigné (et inversement) ; même règle appliquée au formulaire Biens autonome.
* Dossier locataire : nouveaux champs (pièce d'identité, date de naissance, profession, personnes à charge, garant) sur la fiche locataire, avec un indicateur de complétude du dossier et la liste des informations manquantes.
* Aide contextuelle en direct : le formulaire d'un bien affiche désormais le loyer moyen constaté pour le type sélectionné, mis à jour instantanément au changement de type.
* Migration de schéma non destructive : nouvelles colonnes ajoutées à `wp_limpeed_tenants` via dbDelta, sans jamais toucher aux locataires existants.

= 1.11.0 =
* Nouvelle en-tête d'application : bandeau bleu pleine largeur avec le logo dans un encart blanc, bouton menu (hamburger) et menu utilisateur déroulant (Réglages, Déconnexion) au survol de l'avatar.
* Tableau de bord : chaque carte d'indicateur affiche désormais une icône dédiée, et une barre de progression colorée illustre le ratio actifs/inactifs ou disponibles/occupés.
* Le menu latéral et l'en-tête restent visibles au défilement de la page (barre du haut et menu "collants").

= 1.10.0 =
* Refonte visuelle de l'application frontend : menu latéral organisé en sections (Gestion, Finances, Administration), bouton de réduction/agrandissement du menu (préférence mémorisée), fine bande dégradée bleu/vert en haut de l'écran, cartes et boutons avec un effet de survol plus soigné.
* Correction : le logo personnalisé était affiché un peu trop petit dans le menu latéral et les pages de connexion/inscription ; sa taille maximale a été augmentée pour mieux correspondre à l'espace disponible.

= 1.9.0 =
* Logo personnalisé : possibilité d'uploader son propre logo (PNG, JPEG, GIF, WEBP — max 2 Mo) depuis Réglages, affiché à la place du logo par défaut sur l'application frontend et les pages de connexion/inscription, avec option de suppression pour revenir au logo par défaut.
* Mode sombre : bouton de bascule clair/sombre dans la barre du haut de l'application frontend, préférence mémorisée par appareil (localStorage) et appliquée sans flash au chargement de la page.

= 1.8.0 =
* Mois d'avance par locataire : nouveau réglage "Mois d'avance par défaut" (1 à 12), utilisé pour calculer automatiquement le montant d'avance attendu et le statut (à jour / en avance / en retard) de chaque locataire, sur la base du dernier mois marqué "payé".
* Calendrier de suivi des paiements sur la fiche de chaque locataire : grille des 12 mois de l'année (navigable), payé/partiel/en retard/à venir/hors bail, avec accès direct à l'enregistrement d'un paiement pour un mois donné.
* Nouvelle identité visuelle : logo Limpeed Immobilier (icône + wordmark) sur l'application frontend et les pages de connexion/inscription, et rafraîchissement complet du design (palette, espacements, ombres, cartes, formulaires) pour un rendu plus épuré.
* Correction : le lien "Accéder au tableau de bord" affiché à un agent déjà connecté pointait encore vers l'ancien tableau de bord wp-admin ; il pointe maintenant vers le tableau de bord frontend.

= 1.7.0 =
* Ajout d'un champ "Identifiant" (ex : A1, RDC Gauche) sur les biens (sous-édifices), affiché comme colonne principale dans la liste des Biens et pris en compte dans la recherche.
* Le formulaire Édifice (admin et frontend) permet désormais d'ajouter directement plusieurs sous-édifices (biens) en une seule fois, avec identifiant, adresse, type, loyer, charges, dépôt et statut, sans quitter la page de l'édifice.
* Migration de schéma non destructive : la colonne `reference` est ajoutée à `wp_limpeed_properties` via dbDelta, sans jamais toucher aux biens existants.

= 1.6.0 =
* Migration complète de l'administration vers l'application frontend : Propriétaires, Édifices, Biens, Locataires, Paiements, Bordereaux, Agents, Journal d'activité et Réglages sont désormais gérables depuis `[limpeed_dashboard]`, sans jamais passer par wp-admin.
* Routeur frontend unique (`?limpeed_view=...`) partagé par toutes les sections, avec navigation latérale filtrée selon les droits de l'agent connecté.
* wp-admin reste entièrement fonctionnel en secours ; rien n'a été supprimé côté administration.
* Téléchargement sécurisé des bordereaux PDF directement depuis le frontend (mêmes vérifications de droits et de nonce que côté admin).

= 1.5.0 =
* Nouvelle page frontend "Tableau de bord" (`[limpeed_dashboard]`), créée automatiquement à l'activation, en application autonome indépendante du thème actif (`template_include`).
* Accès réservé aux agents approuvés ; redirection vers la connexion sinon.
* Indicateurs, graphique de recouvrement des loyers sur 6 mois, listes des 10 derniers locataires/propriétaires/quittances.
* Première étape de la bascule progressive de l'administration hors de wp-admin : la barre latérale contient déjà les entrées pour toutes les sections, pointant vers wp-admin en attendant leur migration.

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
