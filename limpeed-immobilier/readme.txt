=== Limpeed Immobilier - Gestion Locative ===
Contributors: limpeed
Tags: immobilier, gestion locative, biens, locataires, propriétaires
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.49.0
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

= 1.49.0 =
* Les agents (rôle "Agent Limpeed") peuvent désormais générer et consulter les Bordereaux, sans avoir besoin du rôle Administrateur Limpeed. Trésorerie et Comptabilité (charges, caisses, grand livre), qui partageaient auparavant la même permission que les Bordereaux, restent réservées aux administrateurs Limpeed via une nouvelle permission dédiée ("manage_limpeed_treasury").

= 1.48.0 =
* Modernisation visuelle du design (tableau de bord et composants partagés dans toute l'application) : coins plus arrondis, ombres douces superposées, icônes des cartes KPI en dégradé au lieu d'aplats, bandeaux pleine largeur remplacés par des pastilles discrètes, en-têtes de listes ("Derniers locataires"...) avec puce colorée au lieu d'un bandeau plein, graphique de recouvrement avec barres en dégradé et surbrillance au survol, légère animation d'apparition des cartes au chargement. Effet purement visuel (CSS) : aucun changement de données ni de comportement.

= 1.47.0 =
* Ajout d'une case "Nouveau locataire (première location)" au formulaire d'ajout/modification d'un locataire (formulaire classique et modale). Quand elle est cochée, le montant des honoraires d'agence (loyer × nombre de mois défini dans Réglages → "Mois d'honoraires agence", 1 par défaut) est calculé et affiché sur la fiche du locataire, aux côtés de l'avance et de la caution. Ce montant reste purement informatif : il n'est jamais ajouté automatiquement à une caisse, à enregistrer manuellement comme les autres honoraires d'agence.

= 1.46.0 =
* La liste des Bordereaux est elle aussi regroupée par propriétaire (même principe que Édifices/Biens/Paiements ajouté en 1.45.0), pour une lecture plus immédiate quand plusieurs propriétaires sont mélangés dans la liste.

= 1.45.0 =
* Les listes Édifices, Biens et Paiements sont désormais regroupées par propriétaire (un intitulé de section par propriétaire, puis ses cartes en dessous), pour rendre ces listes plus faciles à comprendre d'un coup d'œil. La recherche et les filtres existants continuent de fonctionner normalement à l'intérieur de cet affichage groupé.

= 1.44.0 =
* Nouvelle police Poppins dans toute l'application (titres et texte courant), à la place d'Inter/Manrope — police auto-hébergée (aucun appel à Google Fonts), 5 graisses vendorisées (400/500/600/700/800), y compris sur les pages de connexion/inscription.
* Les intitulés de la barre de navigation latérale (Propriétaires, Édifices, Biens...) passent en noir au lieu de gris, pour plus de lisibilité — l'élément actif et le survol conservent leur couleur d'accent verte.

= 1.43.0 =
* Nouvelle section "Travaux" : un agent peut demander la réalisation de travaux sur un édifice (montant estimé + motif). La demande reste "En attente" jusqu'à ce qu'un administrateur l'approuve ou la refuse (avec motif de refus optionnel). Une demande approuvée crée automatiquement la charge correspondante dans Comptabilité → Charges (catégorie Réparation), pour que le montant validé apparaisse dans les comptes sans ressaisie manuelle.

= 1.42.0 =
* Nouvel export global des données (section Réglages) : télécharge une archive ZIP contenant un fichier CSV par type de donnée (propriétaires, édifices, biens, locataires, paiements, bordereaux, mandats, avenants, états des lieux, documents, charges, caisses et journal d'activité), avec les libellés déjà traduits (statuts, types, noms des propriétaires/biens/locataires) plutôt que les identifiants bruts — pour donner à l'agence une vue d'ensemble exploitable de toutes ses données (sauvegarde, transmission à un comptable, analyse dans un tableur).

= 1.41.0 =
* Sécurité, suite à un audit ciblé sur les besoins d'une agence immobilière manipulant des données de locataires/propriétaires :
  * Protection contre les tentatives de connexion par force brute : après 5 échecs consécutifs (identifiant + IP), la connexion est bloquée 15 minutes — s'applique à la fois au formulaire de connexion du plugin et à wp-login.php natif.
  * Les coordonnées bancaires des propriétaires (RIB/IBAN/mobile money) sont désormais chiffrées en base de données ; les valeurs déjà enregistrées sont automatiquement chiffrées à la mise à jour, sans aucune perte de donnée.
  * Les fichiers déposés dans le module Documents (pièces d'identité, contrats...) et les bordereaux PDF générés sont désormais enregistrés sous un nom de fichier aléatoire et non prévisible, pour rester protégés même sur un serveur où la protection .htaccess du dossier de stockage ne s'appliquerait pas (elle ne fonctionne que sous Apache/LiteSpeed). Un message d'alerte s'affiche dans Réglages si le serveur détecté n'est pas de ce type, avec la configuration équivalente à demander à l'hébergeur.

= 1.40.0 =
* Dans la fiche locataire (panneau de détail), l'onglet "Paiements" affiche désormais les 12 mois de l'année (avec navigation « / » entre années) au lieu de la seule liste des paiements déjà enregistrés : chaque mois indique s'il est payé (avec le montant), en retard, à venir, ou hors période de bail — pour permettre de suivre un locataire au fil du temps, pas seulement de consulter son historique.

= 1.39.0 =
* Refonte visuelle complète du tableau de bord : les quatre listes "Derniers locataires", "Derniers propriétaires", "Quittances soldées" et "Quittances en attente de paiement" passent d'un tableau HTML dense à des mini-cartes (avatar, libellé, montant), dans le même esprit visuel que le reste de l'application. Les deux rangées d'indicateurs (Portefeuille, Ce mois-ci) et les listes récentes reçoivent chacune un intitulé de zone, pour mieux structurer la page.
* Dans la fiche propriétaire, la liste des biens de chaque édifice passe elle aussi du tableau brut à la grille de cartes standard (même modèle que la section Biens), pour une présentation cohérente. Les raccourcis "Ajouter un édifice"/"Ajouter un sous-édifice" existants, qui permettent déjà de créer un édifice ou un bien directement depuis la fiche propriétaire, sont conservés à l'identique.

= 1.38.0 =
* Nouveau "Guide de formation" intégré au plugin, expliquant section par section comment utiliser l'application au quotidien (propriétaires, biens, locataires, paiements, bordereaux, trésorerie, comptabilité...).
* Accessible à la fois depuis l'application frontend (nouvelle entrée de menu "Guide de formation") et depuis l'administration WordPress (nouveau sous-menu du plugin "Limpeed Immobilier" → "Guide de formation").
* Un lien "Ouvrir le guide de formation" a été ajouté sur le tableau de bord (frontend et wp-admin) pour le rendre facilement accessible aux nouveaux agents.

= 1.37.0 =
* Sécurité et intégrité des données, suite à un audit complet du plugin :
  * La suppression d'un locataire est désormais bloquée tant que des paiements, documents, états des lieux ou avenants lui sont encore rattachés (même principe que pour Propriétaires/Édifices/Biens) — évite de perdre l'historique comptable d'un locataire par erreur.
  * Un agent n'ayant que le droit "Paiements" ne peut plus consulter/ajouter/supprimer les documents des modules Propriétaires/Édifices/Biens/Locataires : chaque action sur `/documents` vérifie désormais la capacité propre au type de fiche concerné.
  * Index ajoutés sur `tenants.lease_end` et `payments.payment_date` pour accélérer les listes de baux expirants et de retards.
* Rappel automatique quotidien (email, 7h) récapitulant les locataires en retard de paiement de la période en cours, envoyé à tous les agents/administrateurs. (Un envoi par SMS — Orange Money, MTN, Wave... — nécessiterait la clé API d'un fournisseur tiers à fournir séparément.)
* Export CSV de l'historique des bordereaux (Bordereaux) et du grand livre (Comptabilité), en complément du PDF existant — utile pour un comptable externe ou un tableur.
* Centre de notifications dans la barre du haut (icône cloche, badge de comptage) : les alertes "locataires en retard" et "baux expirant sous 30 jours", jusqu'ici visibles uniquement sur le tableau de bord, sont maintenant accessibles en permanence depuis n'importe quelle page.

= 1.36.1 =
* Trois caisses passent en calcul automatique plutôt qu'en saisie manuelle, puisque leur donnée existe déjà ailleurs dans le plugin : Commission agence (commissions déjà prélevées sur les paiements), Dépense (charges déjà enregistrées dans Comptabilité) et Caution (dépôts de garantie des locataires actuellement actifs).
* Ces trois caisses affichent une icône de synchronisation (au lieu de la flèche) sur leur tuile, et leur panneau de détail affiche une explication au lieu du formulaire de saisie manuelle. Toute tentative d'y enregistrer un mouvement manuel (y compris directement via l'API REST) est rejetée avec un message explicite.
* Les 8 autres caisses (Tva sur commission, Caution CIE/SODECI, Honoraire agence, Timbres fiscaux, Droit d'enregistrement, Frais de dossiers, Frais d'assurance, Autres fonds) restent des livres de caisse manuels.

= 1.36.0 =
* Nouvel onglet "Caisses" dans la section Trésorerie, reprenant le principe de l'ancien logiciel du client : 11 caisses fixes (Commission agence, Caution, Tva sur commission, Dépense, Caution CIE/SODECI, Honoraire agence, Timbres fiscaux (Légalisation bail), Droit d'enregistrement, Frais de dossiers, Frais d'assurance, Autres fonds), chacune tenue manuellement par l'agent comme un petit livre de mouvements (entrées/sorties d'argent), avec un solde calculé automatiquement. Le "Solde" affiché en tête est la somme de toutes les caisses.
* Cliquer sur une caisse ouvre son historique de mouvements et un formulaire pour enregistrer une nouvelle entrée ou sortie (montant, sens, date, libellé) ; chaque mouvement peut être supprimé individuellement.
* L'onglet "Vue d'ensemble" de la Trésorerie (solde net calculé, graphique de flux, derniers décaissements) reste inchangé, désormais sous son propre onglet à côté de "Caisses".
* Nouvelle table `wp_limpeed_fund_transactions` et nouveaux endpoints REST `/funds/balances`, `/funds/transactions`.

= 1.35.0 =
* Chaque section de gestion a maintenant une identité visuelle propre plutôt qu'un modèle de carte générique unique : couleur d'accent en haut de carte (fixe par section, ou dynamique selon le statut/type de la ligne pour les entités qui en ont un), avatar rond (initiales pour les personnes — propriétaires, locataires, agents ; icône pour les objets — biens, édifices, mandats, documents, charges) et, pour les cartes où un montant est l'information la plus importante (Paiements, Bordereaux, Charges), une mise en avant typographique du montant ("hero").
* Biens : accent selon statut (loué/vacant/travaux), avatar maison verte, sous-titre = édifice.
* Édifices : accent bleu fixe, avatar immeuble bleu, sous-titre = propriétaire.
* Mandats : accent selon statut, avatar document bleu, sous-titre = propriétaire.
* États des lieux : accent et avatar caméra selon le type (entrée = vert, sortie = orange), sous-titre = bien.
* Paiements : accent selon statut, avatar vert, montant payé en gros caractères.
* Documents : accent vert fixe, avatar dossier/fichier, sous-titre = nom du fichier.
* Charges (Comptabilité) : accent rouge fixe, avatar rouge, montant en gros caractères.
* Propriétaires, Bordereaux et Agents avaient déjà reçu ce traitement dans une itération précédente de la même version de travail.

= 1.34.0 =
* Refonte visuelle de toutes les listes de l'application : les tableaux denses (Propriétaires, Locataires, Biens, Édifices, Mandats, États des lieux, Paiements, Bordereaux, Agents, Documents, Charges) sont remplacés par des grilles de cartes cliquables, dans le même esprit que les cartes KPI du tableau de bord.
* Chaque carte affiche le nom/titre, un badge de statut et les informations clés (avec icône) ; cliquer n'importe où sur la carte ouvre le panneau de détail (ou déclenche l'action principale : téléchargement pour un bordereau ou un document), les actions secondaires (Modifier/Supprimer) restant accessibles en pied de carte sans déclencher le clic principal.
* Nouveau module CSS réutilisable `.limpeed-entity-grid`/`.limpeed-entity-card` (mode sombre inclus) et gestionnaire de clic générique dans `app.js` pour les listes rendues côté serveur (Propriétaires, Bordereaux, Agents).
* Le grand livre de la Comptabilité et le journal d'activité restent des tableaux : ce sont des relevés chronologiques en lecture seule, sans fiche de détail à afficher au clic.

= 1.33.0 =
* Bordereau ("Décompte propriétaire") aligné plus fidèlement sur le modèle fourni par le client :
  * Nouvelles colonnes dans le détail bien par bien : "Libellé facture" (ex. "Facture du loyer de juillet 2026"), "Caution" (dépôt de garantie configuré sur le bien) et "Total arriérés" (créance cumulée du locataire depuis le début du bail, distincte du "Restant" propre à la période du bordereau).
  * Formule d'acquit ajoutée avant la signature : "Le client reconnaît avoir reçu un versement des loyers indiqués ci-dessus la somme de [montant en toutes lettres] francs CFA et donne ainsi décharge."
  * Mise en page basculée en format paysage pour accueillir les colonnes supplémentaires sans les compresser.
  * Nouvelle méthode utilitaire `Limpeed_Statements::amount_to_french_words()` (conversion d'un montant en toutes lettres françaises).

= 1.32.0 =
* Onglet Bilan de la section Comptabilité : bouton "Télécharger le PDF « Résultats financiers du mois »" reproduisant le rapport imprimé de l'ancien logiciel de l'agence.
  * Logo de l'agence en en-tête (même mécanisme que le bordereau), charges et produits détaillés ligne par ligne côte à côte (un honoraire par paiement encaissé, une ligne par charge), synthèse du bénéfice réalisé (ou déficit) en bas de page.
  * Généré à la volée à chaque téléchargement (comme les contrats de bail/mandats/états des lieux), pas de stockage d'historique.
  * Nouvelle méthode `Limpeed_Accounting::get_monthly_results()`, nouveau contrôleur frontend `Limpeed_Frontend_Accounting`, nouveau type d'objet `financial_report` dans le journal d'activité.

= 1.31.0 =
* Refonte du PDF du bordereau ("Décompte propriétaire") pour reprendre le format utilisé par l'ancien logiciel de l'agence :
  * Logo de l'agence inséré en en-tête (logo personnalisé téléversé dans Réglages, ou repli textuel "Limpeed Immobilier" si aucun logo n'est défini).
  * Détail bien par bien avec locataire, loyer attendu, charges, montant payé et restant dû, plutôt qu'un simple total encaissé/commission.
  * Nouvelle section "À déduire" : honoraires de l'agence (taux effectif constaté) + une déduction ponctuelle optionnelle (ex. facture d'électricité avancée pour le compte du propriétaire), puis "Net à payer".
  * Bloc de signature ("Fait le [date]" + nom du propriétaire) en bas de document.
  * Nouvelles colonnes `other_deduction_label`/`other_deduction_amount` sur `wp_limpeed_statements` ; nouvelles méthodes `Limpeed_Branding::get_logo_path()/get_logo_data_uri()` pour l'insertion du logo dans les PDF (Dompdf n'autorisant pas les images distantes, le logo est encodé en data URI).

= 1.30.0 =
* Harmonisation visuelle : chaque section de gestion (Propriétaires, Édifices, Biens, Locataires, Documents, Mandats, États des lieux, Paiements, Bordereaux, Agents) affiche désormais une rangée de cartes de synthèse en haut de page, dans le même style que le tableau de bord (icône colorée, chiffre clé, barre d'accent en bas de carte).
* Nouveaux indicateurs par section : ratio loués/vacants et loyer moyen (Biens), commission moyenne (Édifices), impayés et baux à échéance (Locataires), espace de stockage utilisé (Documents), mandats actifs/expirant (Mandats), entrées/sorties (États des lieux), encaissé/commissions du mois (Paiements), total reversé/commissions (Bordereaux), demandes en attente (Agents).
* Nouvelles méthodes utilitaires purement calculées (aucune nouvelle table) : `Limpeed_Buildings::get_average_commission_rate()`, `Limpeed_Properties::get_average_rent()`, `Limpeed_Documents::count_all()/get_total_size()/count_added_this_month()`, `Limpeed_Agents::count_created_this_month()`.

= 1.29.0 =
* Nouveau module "Comptabilité" (menu Finances) : comptabilité de base pour l'agence, avec trois onglets.
  * Bilan : produits (commissions prélevées), charges de l'agence et résultat net pour une période choisie (sélecteur mois).
  * Grand livre : vue consolidée et paginée de tous les mouvements financiers (encaissements de loyers, reversements aux propriétaires, charges), filtrable par type et par période.
  * Charges : gestion complète (ajout/modification/suppression) des dépenses de l'agence, avec catégorie, montant et rattachement optionnel à un édifice.
  * Nouvelle table `wp_limpeed_expenses`, nouvelle classe `Limpeed_Accounting` (grand livre et bilan calculés à partir des paiements, bordereaux et charges déjà existants, aucune duplication de données), nouveaux endpoints REST `/expenses`, `/expenses/{id}`, `/accounting/ledger`, `/accounting/summary`.

= 1.28.0 =
* Nouveau module "Documents" (menu Gestion) : gestion documentaire centralisée pour tout type de pièce administrative (bail, quittance, pièce d'identité, etc.).
  * Sélecteur d'entité (propriétaire, édifice, bien ou locataire) avec recherche, réutilisant les listes existantes ; documents rattachés par un couple entity_type/entity_id.
  * Téléversement (PDF, images, Word) jusqu'à 5 Mo, stockage hors accès web direct (dossier protégé par .htaccess, comme les bordereaux), téléchargement sécurisé par capacité + nonce.
  * Nouvelle table `wp_limpeed_documents`, nouveaux endpoints REST `GET/POST /documents`, `DELETE /documents/{id}`.

= 1.27.0 =
* Nouveau module "Trésorerie" (menu Finances), en lecture seule :
  * Solde de trésorerie de l'agence (encaissé net non encore reversé aux propriétaires), total encaissé, total des commissions et total reversé depuis toujours.
  * Graphique de flux de trésorerie sur 6 mois passés + 3 mois de prévision (basée sur les locataires actifs actuels), avec distinction visuelle réel/prévisionnel.
  * Liste des derniers reversements (décaissements) aux propriétaires, avec lien vers les bordereaux complets.
  * Nouvelle classe `Limpeed_Treasury`, entièrement calculée à partir des paiements et bordereaux déjà existants (aucune nouvelle table).

= 1.26.0 =
* Nouveau module "États des lieux" (menu Gestion) :
  * Fiches d'entrée et de sortie par locataire, détail pièce par pièce (nom, état bon/moyen/mauvais, notes) avec répétiteur dynamique dans le formulaire.
  * Comparaison automatique entrée/sortie pièce par pièce dans le panneau de détail, avec mise en évidence des pièces dont l'état a changé et des pièces manquantes d'un côté ou de l'autre.
  * Génération à la volée du PDF de l'état des lieux (même principe que les contrats de bail/mandats : régénéré à chaque téléchargement, non stocké).
  * Nouvelle table `wp_limpeed_inspections`, nouveaux endpoints REST `/inspections`, `/inspections/{id}`, `/inspections/{id}/history`, `/tenants/{id}/inspection-comparison`.

= 1.25.0 =
* Nouveau module "Contrats & Mandats" :
  * Nouvelle section "Mandats" (menu Gestion) : mandats de gestion locative par édifice (période, taux de commission propre au mandat, statut actif/expiré/résilié dérivé automatiquement de la date de fin), avec recherche, filtres et historique.
  * Génération à la volée (PDF, non stocké) du contrat de bail à partir de la fiche locataire, et du mandat de gestion à partir de sa fiche — boutons "Générer le contrat de bail (PDF)" / "Télécharger le mandat (PDF)".
  * Nouvel onglet "Avenants" sur la fiche locataire : journal des avenants au bail (révision de loyer, prolongation) avec mise à jour automatique du loyer/de la date de fin du bail lorsque l'avenant les modifie.
  * Nouvelles tables `wp_limpeed_mandates` et `wp_limpeed_lease_amendments`, nouveaux endpoints REST `/mandates`, `/mandates/{id}`, `/mandates/{id}/history`, `/tenants/{id}/amendments`.

= 1.24.0 =
* Tableau de bord : les 4 listes ("Derniers locataires", "Derniers propriétaires", "Quittances soldées", "Quittances en attente de paiement") sont désormais des widgets Ajax avec recherche et pagination réelles ("Afficher X / Rechercher", boutons Premier/Précédent/numéros de page/Suivant/Dernier), au lieu d'un instantané statique limité aux 10 derniers éléments.
* Nouveaux endpoints REST : `GET /owners` étendu (recherche + pagination, en plus de son usage existant pour les listes déroulantes en cascade), `GET /tenants` accepte un paramètre `with_balance` (solde du mois en cours), nouvel endpoint `GET /dashboard/unpaid-tenants` (liste paginée des locataires actifs sans paiement sur la période).
* Cartes KPI et en-têtes de listes du tableau de bord : bandeaux de couleur pleine (au lieu de teintes pastel) pour un rendu plus dense et affirmé.
* Densité générale resserrée sur le tableau de bord (espacements réduits, plus de cartes visibles à l'écran).

= 1.23.0 =
* Refonte visuelle de l'application frontend (identité propre, sans changement de structure/menus/contenu) :
  * Nouvelle couleur de marque : vert émeraude dérivé du logo Limpeed (boutons, liens, sidebar active, focus des champs), le bleu devient une teinte secondaire/informative (utilisée uniquement pour des variantes décoratives : une carte KPI, une catégorie du graphique, le statut "en avance").
  * Typographie : Manrope (titres, chiffres clés) + Inter (texte courant, tableaux), auto-hébergées localement (pas d'appel à Google Fonts).
  * En-tête de l'application : couleur bleue plate remplacée par un fond neutre anthracite, avec bordure basse discrète à la place de l'ombre marquée.
  * Grille d'espacement 4px/8px introduite (variables CSS `--space-1` à `--space-8`) et appliquée aux zones structurantes (contenu principal, cartes, panneaux, modales, menu latéral).
  * Bouton secondaire ("Annuler") : style contour/discret au lieu d'un remplissage gris plein.

= 1.22.1 =
* Correction : dans les fenêtres modales d'ajout/modification (Locataires, Édifices, Biens, Paiements), lorsque le formulaire était plus long que la fenêtre (ex. le formulaire complet "Ajouter un locataire" avec la section "Informations complémentaires"), son contenu débordait visuellement hors de la modale au lieu de défiler à l'intérieur, laissant apparaître le tableau de la page en arrière-plan. Le formulaire défile désormais correctement dans sa propre modale, quelle que soit la hauteur de l'écran.

= 1.22.0 =
* Rafraîchissement visuel de l'application frontend : barre supérieure plus sobre (couleur moins saturée, ombre allégée remplacée par une fine bordure), listes déroulantes (filtres et formulaires) désormais personnalisées avec une flèche dédiée au lieu du rendu natif du navigateur, et bandeaux de titre des listes du tableau de bord ("Liste des...") remplacés par un style plus discret (fond teinté + liseré de couleur) au lieu d'un aplat de couleur plein.

= 1.21.1 =
* Correction : sur les écrans étroits (mobile), les tableaux des sections de l'application frontend (Locataires, Édifices, Biens, Paiements, Propriétaires, Agents, Journal d'activité, Bordereaux, Tableau de bord) forçaient la page entière à dépasser la largeur de l'écran. Cela provoquait un rendu incorrect des fenêtres modales sur mobile (contenu de la page visible par-dessus la modale, champs coupés sur les bords). Chaque tableau défile désormais horizontalement dans son propre conteneur, sans affecter la largeur de la page.

= 1.21.0 =
* Tableau de bord (application frontend) : nouvelles cartes KPI dynamiques — taux d'occupation, loyers impayés du mois en cours et baux arrivant à échéance dans les 30 jours — rafraîchies automatiquement en Ajax (au chargement puis toutes les 60 secondes) sans recharger la page. Chaque carte se déplie au clic pour afficher le détail (liste des locataires concernés).
* Nouvel indicateur "Baux arrivant à échéance" côté locataires (`Limpeed_Tenants::get_expiring_leases()`), et nouvel endpoint REST `GET /dashboard/kpis`.

= 1.20.0 =
* Section Paiements (application frontend) rendue dynamique : recherche par nom de locataire, filtres (bien, statut, mois concerné) en direct, modale d'enregistrement rapide avec sélection du locataire peuplée en Ajax (le bien reste déterminé automatiquement), suppression Ajax avec confirmation, panneau de détail à onglets (Infos, Historique). La navigation croisée existante depuis une fiche locataire ou un bien ("Voir l'historique des paiements") continue de préfiltrer la liste.
* L'API REST `limpeed/v1` gère désormais aussi les paiements (liste paginée/recherche, création, modification, suppression, historique).

= 1.19.0 =
* Section Biens / sous-édifices (application frontend) rendue dynamique : recherche et filtres (propriétaire, édifice, statut) en direct, modale d'ajout/modification avec cascade Propriétaire → Édifice peuplée en Ajax, suppression Ajax avec confirmation (bloquée avec message clair si un locataire est encore rattaché), panneau de détail à onglets (Infos, Paiements, Historique). L'aide contextuelle sur le loyer moyen par type de bien reste disponible directement dans la modale.
* L'API REST `limpeed/v1` gère désormais aussi les biens (liste paginée/recherche, création, modification, suppression, historique, paiements).
* Correction : les formulaires de modification d'un bien (admin et frontend) rejetaient à tort les biens ayant conservé un ancien type (Appartement, Maison, Local commercial) lors de leur enregistrement sans changement de type.

= 1.18.0 =
* Section Édifices (application frontend) rendue dynamique sur le même modèle que les Locataires : recherche et filtre par propriétaire en direct, modale d'ajout/modification, suppression Ajax avec confirmation (bloquée avec message clair si des sous-édifices y sont encore rattachés), panneau de détail à onglets (Infos, Biens, Historique).
* L'API REST `limpeed/v1` gère désormais aussi les édifices (liste paginée/recherche, création, modification, suppression, historique), en plus des locataires.
* Le client REST JavaScript commun aux sections dynamiques est désormais mutualisé (`limpeed-rest-client.js`) au lieu d'être dupliqué par section.

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
