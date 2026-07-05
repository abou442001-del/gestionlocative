/**
 * Regroupement par propriétaire partagé par les composants Alpine.js dont la
 * liste bénéficie d'être organisée par propriétaire plutôt qu'affichée en
 * grille plate (Édifices, Biens, Paiements) — pour "rendre plus facile à
 * comprendre" une liste dont chaque ligne appartient in fine à un propriétaire.
 */
window.LimpeedGrouping = ( function () {
	'use strict';

	/**
	 * @param {Array}  items         Lignes à regrouper (chacune doit porter le champ ownerLabelKey).
	 * @param {string} ownerLabelKey Nom du champ portant le libellé du propriétaire (ex: 'owner_label').
	 * @param {string} secondarySortKey Nom du champ servant à trier les lignes à l'intérieur d'un groupe (ex: 'name').
	 * @return {Array} [{ ownerLabel, items }], trié par nom de propriétaire.
	 */
	function byOwner( items, ownerLabelKey, secondarySortKey ) {
		var sorted = ( items || [] ).slice().sort( function ( a, b ) {
			var la = ( a[ ownerLabelKey ] || '' ).toLowerCase();
			var lb = ( b[ ownerLabelKey ] || '' ).toLowerCase();
			if ( la !== lb ) {
				return la < lb ? -1 : 1;
			}
			var sa = ( a[ secondarySortKey ] || '' ).toString().toLowerCase();
			var sb = ( b[ secondarySortKey ] || '' ).toString().toLowerCase();
			return sa < sb ? -1 : ( sa > sb ? 1 : 0 );
		} );

		var groups = [];
		var currentLabel = null;
		var currentGroup = null;

		sorted.forEach( function ( item ) {
			var label = item[ ownerLabelKey ] || 'Sans propriétaire';
			if ( label !== currentLabel ) {
				currentLabel = label;
				currentGroup = { ownerLabel: label, items: [] };
				groups.push( currentGroup );
			}
			currentGroup.items.push( item );
		} );

		return groups;
	}

	return { byOwner: byOwner };
} )();
