<?php
/**
 * Contenu partagé du "Guide de formation" (comment utiliser le plugin au
 * quotidien), inclus à la fois par la section frontend (content-guide.php)
 * et par la page d'administration WordPress (admin/views/guide.php) : le
 * texte n'existe qu'à un seul endroit pour éviter toute divergence entre
 * les deux contextes d'affichage.
 *
 * Tout le CSS est volontairement scopé sous .limpeed-guide-doc pour ne
 * jamais entrer en collision avec les styles de wp-admin ou de l'app.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="limpeed-guide-doc">
<style>
.limpeed-guide-doc {
	--lg-ink: #16241c;
	--lg-ink-soft: #4b5a4f;
	--lg-panel: #ffffff;
	--lg-border: #dde3d9;
	--lg-border-soft: #e8ebe5;
	--lg-primary: #0e7c55;
	--lg-primary-dark: #0a5f41;
	--lg-primary-tint: #e3f5ec;
	--lg-blue: #1d4e89;
	--lg-blue-tint: #e8eef7;
	--lg-orange: #a15a0f;
	--lg-orange-tint: #fdf1e3;
	--lg-danger: #a5322a;
	--lg-danger-tint: #fbe6e4;
	--lg-shadow: 0 1px 2px rgba(16,36,26,.06), 0 8px 24px rgba(16,36,26,.07);
	--lg-font-display: Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif;
	--lg-font-mono: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
	color: var(--lg-ink);
	font-size: 15px;
	line-height: 1.6;
	max-width: 760px;
}
@media (prefers-color-scheme: dark) {
	.limpeed-guide-doc {
		--lg-ink: #e9f0ea; --lg-ink-soft: #a9bcae; --lg-panel: #15221b;
		--lg-border: #23342a; --lg-border-soft: #1c2a22; --lg-primary: #43c38b; --lg-primary-dark: #2f9c6c;
		--lg-primary-tint: #16302379; --lg-blue: #7ea3dd; --lg-blue-tint: #16233579;
		--lg-orange: #e3a35c; --lg-orange-tint: #2c210a; --lg-danger: #e8837c; --lg-danger-tint: #331715;
		--lg-shadow: 0 1px 2px rgba(0,0,0,.3), 0 8px 24px rgba(0,0,0,.35);
	}
}
:root[data-theme="dark"] .limpeed-guide-doc {
	--lg-ink: #e9f0ea; --lg-ink-soft: #a9bcae; --lg-panel: #15221b;
	--lg-border: #23342a; --lg-border-soft: #1c2a22; --lg-primary: #43c38b; --lg-primary-dark: #2f9c6c;
	--lg-primary-tint: #16302379; --lg-blue: #7ea3dd; --lg-blue-tint: #16233579;
	--lg-orange: #e3a35c; --lg-orange-tint: #2c210a; --lg-danger: #e8837c; --lg-danger-tint: #331715;
	--lg-shadow: 0 1px 2px rgba(0,0,0,.3), 0 8px 24px rgba(0,0,0,.35);
}
:root[data-theme="light"] .limpeed-guide-doc {
	--lg-ink: #16241c; --lg-ink-soft: #4b5a4f; --lg-panel: #ffffff;
	--lg-border: #dde3d9; --lg-border-soft: #e8ebe5; --lg-primary: #0e7c55; --lg-primary-dark: #0a5f41;
	--lg-primary-tint: #e3f5ec; --lg-blue: #1d4e89; --lg-blue-tint: #e8eef7;
	--lg-orange: #a15a0f; --lg-orange-tint: #fdf1e3; --lg-danger: #a5322a; --lg-danger-tint: #fbe6e4;
	--lg-shadow: 0 1px 2px rgba(16,36,26,.06), 0 8px 24px rgba(16,36,26,.07);
}
.limpeed-guide-doc h2, .limpeed-guide-doc h3 { font-family: var(--lg-font-display); font-weight: 700; text-wrap: balance; color: var(--lg-ink); }
.limpeed-guide-doc a { color: var(--lg-primary-dark); }
:root[data-theme="dark"] .limpeed-guide-doc a { color: var(--lg-primary); }
.limpeed-guide-doc code {
	font-family: var(--lg-font-mono);
	font-size: .85em;
	background: var(--lg-border-soft);
	border: 1px solid var(--lg-border);
	border-radius: 5px;
	padding: .1em .45em;
	color: var(--lg-ink);
}
.limpeed-guide-doc .lg-intro { color: var(--lg-ink-soft); font-size: 16px; max-width: 66ch; margin: 0 0 20px; }
.limpeed-guide-doc .lg-sommaire {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	padding: 16px;
	background: var(--lg-panel);
	border: 1px solid var(--lg-border);
	border-radius: 10px;
	margin: 0 0 36px;
	box-shadow: var(--lg-shadow);
}
.limpeed-guide-doc .lg-sommaire a {
	font-size: 12.5px;
	font-weight: 600;
	text-decoration: none;
	background: var(--lg-border-soft);
	color: var(--lg-ink);
	padding: 6px 12px;
	border-radius: 999px;
}
.limpeed-guide-doc .lg-sommaire a:hover { background: var(--lg-primary-tint); color: var(--lg-primary-dark); }
.limpeed-guide-doc section.lg-module { margin: 0 0 12px; scroll-margin-top: 20px; }
.limpeed-guide-doc section.lg-module + section.lg-module { margin-top: 40px; padding-top: 34px; border-top: 1px solid var(--lg-border); }
.limpeed-guide-doc .lg-eyebrow {
	display: inline-block;
	font-size: 10.5px;
	letter-spacing: .12em;
	text-transform: uppercase;
	font-weight: 700;
	padding: 3px 9px;
	border-radius: 5px;
	margin-bottom: 10px;
}
.limpeed-guide-doc .lg-eyebrow.gestion { background: var(--lg-primary-tint); color: var(--lg-primary-dark); }
.limpeed-guide-doc .lg-eyebrow.finances { background: var(--lg-blue-tint); color: var(--lg-blue); }
.limpeed-guide-doc .lg-eyebrow.admin { background: var(--lg-orange-tint); color: var(--lg-orange); }
.limpeed-guide-doc section.lg-module h2 { font-size: 22px; margin: 0 0 6px; }
.limpeed-guide-doc section.lg-module .lg-dek { color: var(--lg-ink-soft); font-size: 14.5px; margin: 0 0 18px; max-width: 62ch; }
.limpeed-guide-doc .lg-where {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 13px;
	background: var(--lg-panel);
	border: 1px solid var(--lg-border);
	border-radius: 9px;
	padding: 9px 14px;
	margin: 0 0 20px;
	box-shadow: var(--lg-shadow);
}
.limpeed-guide-doc .lg-where .lg-label { color: var(--lg-ink-soft); flex-shrink: 0; }
.limpeed-guide-doc .lg-where code { background: transparent; border: none; padding: 0; color: var(--lg-primary-dark); font-weight: 600; }
:root[data-theme="dark"] .limpeed-guide-doc .lg-where code { color: var(--lg-primary); }
.limpeed-guide-doc h3.lg-sub { font-size: 15.5px; margin: 22px 0 8px; color: var(--lg-ink); }
.limpeed-guide-doc ol.lg-steps, .limpeed-guide-doc ul.lg-bullets { padding-left: 0; margin: 0 0 16px; list-style: none; counter-reset: step; }
.limpeed-guide-doc ol.lg-steps li {
	counter-increment: step;
	position: relative;
	padding: 6px 0 6px 32px;
	font-size: 14.5px;
}
.limpeed-guide-doc ol.lg-steps li::before {
	content: counter(step);
	position: absolute;
	left: 0;
	top: 5px;
	font-family: var(--lg-font-mono);
	font-size: 11.5px;
	font-weight: 700;
	color: var(--lg-primary-dark);
	background: var(--lg-primary-tint);
	border-radius: 50%;
	width: 21px;
	height: 21px;
	display: flex;
	align-items: center;
	justify-content: center;
}
:root[data-theme="dark"] .limpeed-guide-doc ol.lg-steps li::before { color: var(--lg-primary); }
.limpeed-guide-doc ul.lg-bullets li { position: relative; padding: 4px 0 4px 16px; font-size: 14.5px; }
.limpeed-guide-doc ul.lg-bullets li::before { content: "\2013"; position: absolute; left: 0; color: var(--lg-ink-soft); }
.limpeed-guide-doc .lg-callout { border-radius: 10px; padding: 12px 14px; font-size: 13.5px; margin: 16px 0; border: 1px solid transparent; }
.limpeed-guide-doc .lg-callout .lg-tag { display: block; font-size: 10.5px; text-transform: uppercase; letter-spacing: .1em; font-weight: 700; margin-bottom: 4px; }
.limpeed-guide-doc .lg-callout.lg-tip { background: var(--lg-primary-tint); border-color: var(--lg-primary); }
.limpeed-guide-doc .lg-callout.lg-tip .lg-tag { color: var(--lg-primary-dark); }
:root[data-theme="dark"] .limpeed-guide-doc .lg-callout.lg-tip .lg-tag { color: var(--lg-primary); }
.limpeed-guide-doc .lg-callout.lg-warn { background: var(--lg-danger-tint); border-color: var(--lg-danger); }
.limpeed-guide-doc .lg-callout.lg-warn .lg-tag { color: var(--lg-danger); }
.limpeed-guide-doc .lg-callout.lg-note { background: var(--lg-blue-tint); border-color: var(--lg-blue); }
.limpeed-guide-doc .lg-callout.lg-note .lg-tag { color: var(--lg-blue); }
.limpeed-guide-doc table.lg-field-table { width: 100%; border-collapse: collapse; margin: 14px 0 20px; font-size: 13px; }
.limpeed-guide-doc table.lg-field-table th, .limpeed-guide-doc table.lg-field-table td { text-align: left; padding: 7px 9px; border-bottom: 1px solid var(--lg-border); vertical-align: top; }
.limpeed-guide-doc table.lg-field-table th { color: var(--lg-ink-soft); font-weight: 600; font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; }
.limpeed-guide-doc .lg-mock-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0 20px; }
.limpeed-guide-doc .lg-badge { display: inline-flex; align-items: center; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; letter-spacing: .02em; }
.limpeed-guide-doc .lg-badge.lg-green { background: var(--lg-primary-tint); color: var(--lg-primary-dark); }
:root[data-theme="dark"] .limpeed-guide-doc .lg-badge.lg-green { color: var(--lg-primary); }
.limpeed-guide-doc .lg-badge.lg-orange { background: var(--lg-orange-tint); color: var(--lg-orange); }
.limpeed-guide-doc .lg-badge.lg-red { background: var(--lg-danger-tint); color: var(--lg-danger); }
.limpeed-guide-doc .lg-badge.lg-blue { background: var(--lg-blue-tint); color: var(--lg-blue); }
.limpeed-guide-doc .lg-badge.lg-muted { background: var(--lg-border-soft); color: var(--lg-ink-soft); }
.limpeed-guide-doc .lg-fund-mock { display: inline-flex; flex-direction: column; gap: 2px; color: #fff; border-radius: 9px; padding: 9px 13px; font-size: 12px; min-width: 120px; }
.limpeed-guide-doc .lg-fund-mock b { font-size: 14px; font-family: var(--lg-font-display); }
.limpeed-guide-doc .lg-fund-mock.lg-g { background: var(--lg-primary); }
.limpeed-guide-doc .lg-fund-mock.lg-b { background: var(--lg-blue); }
.limpeed-guide-doc .lg-fund-mock.lg-o { background: #c9821f; }
.limpeed-guide-doc .lg-fund-mock.lg-r { background: var(--lg-danger); }
.limpeed-guide-doc dl.lg-faq dt { font-weight: 700; margin-top: 14px; }
.limpeed-guide-doc dl.lg-faq dd { margin: 4px 0 0; color: var(--lg-ink-soft); }
.limpeed-guide-doc .lg-backtotop { display: inline-block; margin-top: 28px; font-size: 12px; color: var(--lg-ink-soft); text-decoration: none; }
</style>

<p class="lg-intro"><?php esc_html_e( "Ce guide accompagne les agents et administrateurs de l'agence dans la prise en main de l'application de gestion locative : propriétaires, biens, locataires, paiements, bordereaux, trésorerie et comptabilité.", 'limpeed-immobilier' ); ?></p>

<nav class="lg-sommaire" id="lg-top" aria-label="Sommaire du guide">
	<a href="#lg-intro">Introduction</a>
	<a href="#lg-interface">Interface</a>
	<a href="#lg-dashboard">Tableau de bord</a>
	<a href="#lg-owners">Propriétaires</a>
	<a href="#lg-buildings">Édifices</a>
	<a href="#lg-properties">Biens</a>
	<a href="#lg-tenants">Locataires</a>
	<a href="#lg-inspections">États des lieux</a>
	<a href="#lg-mandates">Mandats</a>
	<a href="#lg-documents">Documents</a>
	<a href="#lg-payments">Paiements</a>
	<a href="#lg-statements">Bordereaux</a>
	<a href="#lg-treasury">Trésorerie</a>
	<a href="#lg-accounting">Comptabilité</a>
	<a href="#lg-agents">Agents</a>
	<a href="#lg-activity">Journal d'activité</a>
	<a href="#lg-settings">Réglages</a>
	<a href="#lg-faq">FAQ</a>
</nav>

<section class="lg-module" id="lg-intro">
	<h2><?php esc_html_e( 'Introduction', 'limpeed-immobilier' ); ?></h2>
	<p class="lg-dek"><?php esc_html_e( "Limpeed Immobilier centralise toute l'activité de l'agence : qui possède quoi, qui loue quoi, qui a payé, ce qui reste dû, et ce qui doit être reversé aux propriétaires.", 'limpeed-immobilier' ); ?></p>
	<p><?php esc_html_e( "L'application est organisée en trois grands groupes, visibles dans le menu de gauche :", 'limpeed-immobilier' ); ?></p>
	<ul class="lg-bullets">
		<li><b>Gestion</b> — les fiches de base : propriétaires, édifices, biens, locataires, états des lieux, mandats, documents.</li>
		<li><b>Finances</b> — l'argent qui circule : paiements de loyers, bordereaux aux propriétaires, trésorerie de l'agence, comptabilité.</li>
		<li><b>Administration</b> — la gestion de l'outil lui-même : comptes des agents, journal d'activité, réglages.</li>
	</ul>
	<div class="lg-callout lg-tip">
		<span class="lg-tag"><?php esc_html_e( 'Principe général', 'limpeed-immobilier' ); ?></span>
		<?php esc_html_e( "Une donnée qui a un historique (paiements, documents...) ne peut jamais être supprimée par erreur : l'application bloque la suppression tant que des éléments y sont encore rattachés. C'est volontaire — rien ne se perd.", 'limpeed-immobilier' ); ?>
	</div>
</section>

<section class="lg-module" id="lg-interface">
	<h2><?php esc_html_e( "Prendre en main l'interface", 'limpeed-immobilier' ); ?></h2>
	<p class="lg-dek"><?php esc_html_e( 'Chaque page de l\'application partage la même structure : une barre du haut et un menu latéral.', 'limpeed-immobilier' ); ?></p>

	<h3 class="lg-sub"><?php esc_html_e( 'La barre du haut', 'limpeed-immobilier' ); ?></h3>
	<ul class="lg-bullets">
		<li><b>Le bouton ☰</b> réduit ou agrandit le menu latéral (utile sur petit écran).</li>
		<li><b>La cloche 🔔</b> ouvre le centre de notifications : elle affiche un badge rouge dès qu'il y a des locataires en retard de paiement ou des baux qui arrivent à échéance sous 30 jours.</li>
		<li><b>Le soleil/lune</b> bascule entre thème clair et thème sombre. Le choix est mémorisé sur cet appareil.</li>
		<li><b>Votre nom</b> ouvre le menu du compte : accès aux Réglages (si vous en avez le droit) et à la déconnexion.</li>
	</ul>

	<h3 class="lg-sub"><?php esc_html_e( 'Les listes et fiches', 'limpeed-immobilier' ); ?></h3>
	<p>Chaque section présente ses éléments sous forme de <b>cartes</b> plutôt que de longs tableaux. Cliquer n'importe où sur une carte ouvre le détail ; les liens <b>Modifier</b> et <b>Supprimer</b> en bas de carte restent accessibles séparément.</p>
	<div class="lg-mock-row">
		<span class="lg-badge lg-green">ACTIF</span>
		<span class="lg-badge lg-red">EN RETARD</span>
		<span class="lg-badge lg-orange">PARTIEL</span>
		<span class="lg-badge lg-blue">EN AVANCE</span>
		<span class="lg-badge lg-muted">TERMINÉ</span>
	</div>
	<p style="margin-top:-10px; color:var(--lg-ink-soft); font-size:13px;">Les couleurs des badges suivent toujours la même logique : vert = en règle, rouge = problème/retard, orange = partiel ou à surveiller, bleu = en avance, gris = inactif/terminé.</p>
</section>

<section class="lg-module" id="lg-dashboard">
	<h2><?php esc_html_e( 'Tableau de bord', 'limpeed-immobilier' ); ?></h2>
	<p class="lg-dek"><?php esc_html_e( "Première page après connexion : la photo instantanée de l'activité de l'agence.", 'limpeed-immobilier' ); ?></p>
	<ul class="lg-bullets">
		<li>Nombre de propriétaires, locataires, édifices et biens, avec le taux d'occupation du parc.</li>
		<li>Montant des loyers impayés du mois en cours, et nombre de baux arrivant à échéance sous 30 jours.</li>
		<li>Un graphique de recouvrement mensuel (attendu / payé / impayé) sur plusieurs mois.</li>
		<li>Les derniers locataires et propriétaires ajoutés.</li>
	</ul>
	<div class="lg-callout lg-note">
		<span class="lg-tag"><?php esc_html_e( 'À savoir', 'limpeed-immobilier' ); ?></span>
		<?php esc_html_e( "Les mêmes alertes (impayés, baux expirants) sont aussi accessibles en permanence via la cloche 🔔 de la barre du haut, sans avoir besoin de revenir sur cette page.", 'limpeed-immobilier' ); ?>
	</div>
</section>

<section class="lg-module" id="lg-owners">
	<span class="lg-eyebrow gestion">Gestion</span>
	<h2>1. Propriétaires</h2>
	<p class="lg-dek">La fiche de base de chaque propriétaire pour lequel l'agence gère un ou plusieurs biens.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Propriétaires</code></div>
	<h3 class="lg-sub">Ajouter un propriétaire</h3>
	<ol class="lg-steps">
		<li>Cliquer sur <b>Ajouter un propriétaire</b>.</li>
		<li>Renseigner le nom complet (obligatoire), téléphone, email, adresse.</li>
		<li>Renseigner les coordonnées bancaires si elles doivent apparaître sur les bordereaux.</li>
		<li>Enregistrer.</li>
	</ol>
	<p>La fiche d'un propriétaire (clic sur sa carte) liste ses édifices, ses biens et l'historique des bordereaux déjà générés pour lui.</p>
	<div class="lg-callout lg-warn">
		<span class="lg-tag">Suppression impossible si...</span>
		Un propriétaire ne peut pas être supprimé tant qu'il a encore des édifices ou des biens rattachés.
	</div>
</section>

<section class="lg-module" id="lg-buildings">
	<span class="lg-eyebrow gestion">Gestion</span>
	<h2>2. Édifices</h2>
	<p class="lg-dek">Un édifice regroupe les biens loués d'un même propriétaire. C'est aussi à ce niveau que se règle le taux de commission de l'agence.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Édifices</code></div>
	<h3 class="lg-sub">Ajouter un édifice</h3>
	<ol class="lg-steps">
		<li>Cliquer sur <b>Ajouter un édifice</b>.</li>
		<li>Choisir le propriétaire, nommer l'édifice, renseigner l'adresse.</li>
		<li>Définir le <b>taux de commission</b> (%) que l'agence prélève sur les loyers de cet édifice.</li>
	</ol>
	<div class="lg-callout lg-tip">
		<span class="lg-tag">Pourquoi un taux par édifice ?</span>
		Le taux se règle une fois ici et s'applique ensuite automatiquement à tous les paiements des biens de cet édifice.
	</div>
</section>

<section class="lg-module" id="lg-properties">
	<span class="lg-eyebrow gestion">Gestion</span>
	<h2>3. Biens</h2>
	<p class="lg-dek">Le "sous-édifice" loué concrètement : un appartement, un studio, une maison, un local.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Biens</code></div>
	<h3 class="lg-sub">Ajouter un bien</h3>
	<ol class="lg-steps">
		<li>Choisir l'édifice (le propriétaire se déduit automatiquement).</li>
		<li>Choisir le type (Studio, 2 pièces, 3 pièces, 4 pièces et plus).</li>
		<li>Renseigner le loyer mensuel, les charges, le dépôt de garantie.</li>
	</ol>
	<div class="lg-mock-row">
		<span class="lg-badge lg-green">LOUÉ</span>
		<span class="lg-badge lg-muted">VACANT</span>
		<span class="lg-badge lg-orange">TRAVAUX</span>
	</div>
	<p>Le statut d'un bien se met à jour automatiquement dès qu'un locataire actif lui est rattaché ou qu'il part.</p>
	<div class="lg-callout lg-warn">
		<span class="lg-tag">Suppression impossible si...</span>
		Un bien ne peut pas être supprimé tant qu'un locataire (même passé) lui est encore rattaché.
	</div>
</section>

<section class="lg-module" id="lg-tenants">
	<span class="lg-eyebrow gestion">Gestion</span>
	<h2>4. Locataires</h2>
	<p class="lg-dek">La fiche complète d'un locataire : bail, dossier administratif, historique de paiements et d'avenants.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Locataires</code></div>
	<h3 class="lg-sub">Ajouter un locataire</h3>
	<ol class="lg-steps">
		<li>Cliquer sur <b>Ajouter un locataire</b>.</li>
		<li>Sélectionner en cascade : propriétaire → édifice → bien (seuls les biens vacants apparaissent).</li>
		<li>Renseigner nom, téléphone, dates de bail, montant du loyer, dépôt versé.</li>
		<li>Compléter le dossier si besoin : pièce d'identité, profession, garant, personnes à charge.</li>
	</ol>
	<div class="lg-callout lg-note">
		<span class="lg-tag">Automatique</span>
		À la création du locataire, l'application enregistre automatiquement le ou les paiements d'avance correspondant à la période déjà couverte — inutile de les ressaisir à la main.
	</div>
	<h3 class="lg-sub">La fiche détaillée (clic sur une carte)</h3>
	<ul class="lg-bullets">
		<li><b>Infos</b> — coordonnées, bail, lien vers la génération du contrat de bail en PDF.</li>
		<li><b>Paiements</b> — historique complet des loyers de ce locataire.</li>
		<li><b>Avenants</b> — modifications du bail en cours, avec ajout direct depuis cet onglet.</li>
		<li><b>Historique</b> — toutes les actions effectuées sur cette fiche.</li>
	</ul>
	<div class="lg-callout lg-warn">
		<span class="lg-tag">Suppression impossible si...</span>
		Un locataire ne peut pas être supprimé tant qu'il a des paiements, documents, états des lieux ou avenants rattachés — ce qui concerne presque tous les locataires dès leur création (paiement d'avance automatique).
	</div>
</section>

<section class="lg-module" id="lg-inspections">
	<span class="lg-eyebrow gestion">Gestion</span>
	<h2>5. États des lieux</h2>
	<p class="lg-dek">Constat contradictoire à l'entrée et à la sortie d'un locataire, pièce par pièce.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>États des lieux</code></div>
	<ol class="lg-steps">
		<li>Cliquer sur <b>Ajouter un état des lieux</b>, choisir le locataire concerné et le type (Entrée ou Sortie).</li>
		<li>Détailler l'état de chaque pièce.</li>
		<li>Enregistrer.</li>
	</ol>
	<p>Lorsqu'un locataire a un état des lieux d'entrée et de sortie, l'application peut les comparer pour repérer les différences (utile en cas de litige sur la caution).</p>
</section>

<section class="lg-module" id="lg-mandates">
	<span class="lg-eyebrow gestion">Gestion</span>
	<h2>6. Mandats</h2>
	<p class="lg-dek">Le mandat de gestion confié par un propriétaire à l'agence pour un édifice donné.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Mandats</code></div>
	<div class="lg-mock-row">
		<span class="lg-badge lg-green">ACTIF</span>
		<span class="lg-badge lg-orange">EXPIRÉ</span>
		<span class="lg-badge lg-muted">RÉSILIÉ</span>
	</div>
	<p>Un mandat sans date de fin est considéré à durée indéterminée. Les mandats arrivant à échéance sous 30 jours sont signalés.</p>
</section>

<section class="lg-module" id="lg-documents">
	<span class="lg-eyebrow gestion">Gestion</span>
	<h2>7. Documents</h2>
	<p class="lg-dek">Coffre-fort documentaire de l'agence : pièces d'identité, mandats signés, quittances...</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Documents</code></div>
	<ol class="lg-steps">
		<li>Choisir le type de fiche concernée (Propriétaire, Édifice, Bien ou Locataire).</li>
		<li>Rechercher et sélectionner la fiche précise.</li>
		<li>Donner un titre au document et joindre le fichier (PDF, image ou document Word, 5 Mo maximum).</li>
	</ol>
	<div class="lg-callout lg-note">
		<span class="lg-tag">Confidentialité</span>
		Chaque téléchargement vérifie que vous avez le droit sur le module concerné (un agent chargé uniquement des paiements ne peut pas ouvrir les documents des locataires, par exemple).
	</div>
</section>

<section class="lg-module" id="lg-payments">
	<span class="lg-eyebrow finances">Finances</span>
	<h2>8. Paiements</h2>
	<p class="lg-dek">Le cœur de l'activité quotidienne : enregistrer chaque loyer encaissé.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Paiements</code></div>
	<h3 class="lg-sub">Enregistrer un paiement</h3>
	<ol class="lg-steps">
		<li>Cliquer sur <b>Enregistrer un paiement</b>.</li>
		<li>Choisir le locataire (recherche par nom).</li>
		<li>Choisir la période, le montant, le mode de paiement, la date.</li>
		<li>Enregistrer — la commission de l'agence est calculée automatiquement.</li>
	</ol>
	<div class="lg-mock-row">
		<span class="lg-badge lg-green">PAYÉ</span>
		<span class="lg-badge lg-orange">PARTIEL</span>
		<span class="lg-badge lg-red">EN RETARD</span>
		<span class="lg-badge lg-blue">EN AVANCE</span>
	</div>
	<div class="lg-callout lg-tip">
		<span class="lg-tag">Rappel automatique</span>
		Chaque matin à 7h, un email récapitulatif des loyers encore en attente est envoyé automatiquement à tous les agents et administrateurs.
	</div>
</section>

<section class="lg-module" id="lg-statements">
	<span class="lg-eyebrow finances">Finances</span>
	<h2>9. Bordereaux</h2>
	<p class="lg-dek">Le document que l'agence remet à chaque propriétaire : loyers encaissés, moins la commission, égale le net à lui reverser.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Bordereaux</code></div>
	<ol class="lg-steps">
		<li>Cliquer sur <b>Générer un bordereau</b>.</li>
		<li>Choisir le propriétaire et la période.</li>
		<li>Ajouter, si besoin, une déduction supplémentaire ponctuelle.</li>
		<li>Générer — le PDF est produit immédiatement.</li>
	</ol>
	<div class="lg-callout lg-note">
		<span class="lg-tag">Export</span>
		Le bouton <b>Exporter en CSV</b> télécharge l'historique complet des bordereaux dans un tableur.
	</div>
</section>

<section class="lg-module" id="lg-treasury">
	<span class="lg-eyebrow finances">Finances</span>
	<h2>10. Trésorerie</h2>
	<p class="lg-dek">Deux onglets : une vue d'ensemble automatique, et des caisses tenues au jour le jour.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Trésorerie</code></div>
	<h3 class="lg-sub">Onglet Caisses</h3>
	<p>Chaque caisse est un petit livre de mouvements que l'agent alimente lui-même — sauf trois d'entre elles, calculées automatiquement.</p>
	<div class="lg-mock-row">
		<div class="lg-fund-mock lg-g"><span>Solde</span><b>136 100 FCFA</b></div>
		<div class="lg-fund-mock lg-b"><span>Commission agence ⟳</span><b>15 100 FCFA</b></div>
		<div class="lg-fund-mock lg-o"><span>Caution ⟳</span><b>1 000 FCFA</b></div>
		<div class="lg-fund-mock lg-r"><span>Dépense ⟳</span><b>120 000 FCFA</b></div>
	</div>
	<ol class="lg-steps">
		<li>Cliquer sur une caisse pour ouvrir son détail.</li>
		<li>Pour une caisse manuelle : choisir Entrée ou Sortie, le montant, la date, un libellé optionnel.</li>
		<li>Pour une caisse automatique (icône ⟳) : le détail affiche une explication, sans formulaire.</li>
	</ol>
	<p>Le <b>Solde</b> affiché en tête est la somme de toutes les caisses ; il ne se saisit jamais directement.</p>
</section>

<section class="lg-module" id="lg-accounting">
	<span class="lg-eyebrow finances">Finances</span>
	<h2>11. Comptabilité</h2>
	<p class="lg-dek">Trois onglets pour suivre le résultat financier de l'agence elle-même.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Comptabilité</code></div>
	<ul class="lg-bullets">
		<li><b>Bilan</b> — produits (commissions), charges, résultat net d'une période, avec export PDF.</li>
		<li><b>Grand livre</b> — vue consolidée de tous les encaissements/reversements/charges, exportable en CSV.</li>
		<li><b>Charges</b> — ajout des dépenses de l'agence (catégorie, libellé, montant, édifice concerné).</li>
	</ul>
</section>

<section class="lg-module" id="lg-agents">
	<span class="lg-eyebrow admin">Administration</span>
	<h2>12. Agents</h2>
	<p class="lg-dek">La gestion des comptes utilisateurs de l'application et de leurs droits.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Agents</code> — réservé aux administrateurs</div>
	<p>Un nouveau compte peut être créé directement, ou demandé par la personne elle-même via la page d'inscription ; l'administrateur choisit le rôle au moment d'approuver.</p>
</section>

<section class="lg-module" id="lg-activity">
	<span class="lg-eyebrow admin">Administration</span>
	<h2>13. Journal d'activité</h2>
	<p class="lg-dek">La traçabilité complète : qui a créé, modifié ou supprimé quoi, et quand.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Journal d'activité</code></div>
</section>

<section class="lg-module" id="lg-settings">
	<span class="lg-eyebrow admin">Administration</span>
	<h2>14. Réglages</h2>
	<p class="lg-dek">Logo de l'application (repris sur les bordereaux et contrats PDF) et options de confidentialité des données.</p>
	<div class="lg-where"><span class="lg-label">Où le trouver</span> <code>Réglages</code> — réservé aux administrateurs</div>
</section>

<section class="lg-module" id="lg-faq">
	<h2><?php esc_html_e( 'Questions fréquentes', 'limpeed-immobilier' ); ?></h2>
	<dl class="lg-faq">
		<dt>Je me suis trompé de bien en créant un locataire, comment le supprimer ?</dt>
		<dd>Ouvrez d'abord sa fiche, onglet Paiements, et supprimez le paiement d'avance généré automatiquement. Le locataire pourra alors être supprimé.</dd>

		<dt>Puis-je changer le taux de commission d'un édifice après coup ?</dt>
		<dd>Oui, depuis Édifices. Le nouveau taux ne s'applique qu'aux paiements enregistrés après la modification.</dd>

		<dt>Puis-je encaisser un paiement par mobile money ?</dt>
		<dd>Le mode de paiement "Mobile money" peut être sélectionné, mais il s'agit d'une saisie manuelle après encaissement.</dd>

		<dt>Un locataire ou un propriétaire peut-il se connecter lui-même à l'application ?</dt>
		<dd>Non, l'accès est réservé aux agents et administrateurs de l'agence.</dd>
	</dl>
	<a class="lg-backtotop" href="#lg-top">↑ Retour au sommaire</a>
</section>

</div>
