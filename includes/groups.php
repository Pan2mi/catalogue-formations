<?php
/**
 * Groupes de niveaux et groupes de matières.
 *
 * Un niveau (ou une matière) peut appartenir à plusieurs groupes.
 * Exemple : 6ème ∈ Collège + Cycle 3.
 * Le lien est stocké sur le niveau / la matière (méta « if_groups » = liste d'ids de groupes).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'if_register_group_taxonomies', 11 );
add_action( 'admin_init', 'if_maybe_migrate_parents' );

foreach ( array( 'if_niveau', 'if_matiere' ) as $if_tax ) {
	add_action( $if_tax . '_add_form_fields', 'if_groups_field_add' );
	add_action( $if_tax . '_edit_form_fields', 'if_groups_field_edit' );
	add_action( 'created_' . $if_tax, 'if_groups_field_save' );
	add_action( 'edited_' . $if_tax, 'if_groups_field_save' );
	add_filter( 'manage_edit-' . $if_tax . '_columns', 'if_groups_column' );
	add_filter( 'manage_' . $if_tax . '_custom_column', 'if_groups_column_value', 10, 3 );
}
foreach ( array( 'if_groupe_niveau', 'if_groupe_matiere' ) as $if_tax ) {
	add_action( $if_tax . '_add_form_fields', 'if_members_field_add' );
	add_action( $if_tax . '_edit_form_fields', 'if_members_field_edit' );
	add_action( 'created_' . $if_tax, 'if_members_field_save' );
	add_action( 'edited_' . $if_tax, 'if_members_field_save' );
	add_filter( 'manage_edit-' . $if_tax . '_columns', 'if_members_column' );
	add_filter( 'manage_' . $if_tax . '_custom_column', 'if_members_column_value', 10, 3 );
	add_action( 'pre_delete_term', 'if_group_before_delete', 10, 2 );
}
unset( $if_tax );
add_action( 'admin_head-edit-tags.php', 'if_hide_parent_field' );
add_action( 'admin_head-term.php', 'if_hide_parent_field' );

function if_register_group_taxonomies() {
	$common = array(
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_admin_column'  => false,
		'show_in_quick_edit' => false,
		'meta_box_cb'        => false,
		'hierarchical'       => false,
		'rewrite'            => false,
		'show_in_rest'       => false,
	);
	register_taxonomy(
		'if_groupe_niveau',
		'if_notion',
		$common + array(
			'labels' => array(
				'name'          => __( 'Groupes de niveaux', 'catalogue-formations' ),
				'singular_name' => __( 'Groupe de niveaux', 'catalogue-formations' ),
				'add_new_item'  => __( 'Ajouter un groupe de niveaux', 'catalogue-formations' ),
				'edit_item'     => __( 'Modifier le groupe', 'catalogue-formations' ),
				'menu_name'     => __( 'Groupes de niveaux', 'catalogue-formations' ),
				'name_field_description' => __( 'Exemples : Collège, Cycle 3, Lycée.', 'catalogue-formations' ),
				'slug_field_description' => __( 'Facultatif : laissé vide, il est rempli automatiquement.', 'catalogue-formations' ),
				'desc_field_description' => __( 'Facultatif.', 'catalogue-formations' ),
			),
		)
	);
	register_taxonomy(
		'if_groupe_matiere',
		'if_notion',
		$common + array(
			'labels' => array(
				'name'          => __( 'Groupes de matières', 'catalogue-formations' ),
				'singular_name' => __( 'Groupe de matières', 'catalogue-formations' ),
				'add_new_item'  => __( 'Ajouter un groupe de matières', 'catalogue-formations' ),
				'edit_item'     => __( 'Modifier le groupe', 'catalogue-formations' ),
				'menu_name'     => __( 'Groupes de matières', 'catalogue-formations' ),
				'name_field_description' => __( 'Exemples : Langues, Sciences, Numérique.', 'catalogue-formations' ),
				'slug_field_description' => __( 'Facultatif : laissé vide, il est rempli automatiquement.', 'catalogue-formations' ),
				'desc_field_description' => __( 'Facultatif.', 'catalogue-formations' ),
			),
		)
	);
}

/* ---------- Correspondances ---------- */

/** Taxonomie des groupes pour une taxonomie d'éléments (et inversement). */
function if_group_tax( $tax ) {
	return 'if_matiere' === $tax ? 'if_groupe_matiere' : 'if_groupe_niveau';
}
function if_member_tax( $group_tax ) {
	return 'if_groupe_matiere' === $group_tax ? 'if_matiere' : 'if_niveau';
}

/** Ids des groupes d'un niveau / d'une matière. */
function if_groups_of( $term_id ) {
	$g = get_term_meta( $term_id, 'if_groups', true );
	return is_array( $g ) ? array_values( array_unique( array_map( 'intval', $g ) ) ) : array();
}

function if_set_groups( $term_id, $group_ids ) {
	$group_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $group_ids ) ) ) );
	if ( $group_ids ) {
		update_term_meta( $term_id, 'if_groups', $group_ids );
	} else {
		delete_term_meta( $term_id, 'if_groups' );
	}
}

function if_add_group( $term_id, $group_id ) {
	$g = if_groups_of( $term_id );
	if ( ! in_array( (int) $group_id, $g, true ) ) {
		$g[] = (int) $group_id;
		if_set_groups( $term_id, $g );
		return true;
	}
	return false;
}

/** Ids des éléments (niveaux / matières) d'un groupe. */
function if_members_of( $group_id, $group_tax ) {
	$out = array();
	foreach ( get_terms( array( 'taxonomy' => if_member_tax( $group_tax ), 'hide_empty' => false ) ) as $t ) {
		if ( in_array( (int) $group_id, if_groups_of( $t->term_id ), true ) ) {
			$out[] = (int) $t->term_id;
		}
	}
	return $out;
}

/** Trouve ou crée un groupe par son nom. */
function if_get_or_create_group( $name, $group_tax ) {
	$existing = if_find_term_like( $name, $group_tax );
	if ( $existing ) {
		return (int) $existing->term_id;
	}
	$res = wp_insert_term( $name, $group_tax );
	return is_wp_error( $res ) ? 0 : (int) $res['term_id'];
}

/* ---------- Champ « Groupes » sur un niveau / une matière ---------- */

/** Groupes proposés par défaut (tirés des listes prêtes), dans l'ordre. */
function if_suggested_groups( $tax ) {
	$names = array();
	foreach ( if_presets_flat( $tax ) as $groups ) {
		foreach ( $groups as $g ) {
			$names[ $g ] = true;
		}
	}
	$names = array_keys( $names );
	usort(
		$names,
		function ( $a, $b ) {
			$ra = if_auto_rank( $a ) ?: 10000;
			$rb = if_auto_rank( $b ) ?: 10000;
			return $ra === $rb ? strnatcasecmp( $a, $b ) : $ra - $rb;
		}
	);
	return $names;
}

function if_groups_checkboxes( $tax, $selected ) {
	$gtax   = if_group_tax( $tax );
	$groups = get_terms( array( 'taxonomy' => $gtax, 'hide_empty' => false ) );
	$groups = is_wp_error( $groups ) ? array() : $groups;
	usort( $groups, 'if_compare_terms' );

	// Groupes proposés pas encore créés.
	$existing_keys = array();
	foreach ( $groups as $g ) {
		$existing_keys[ if_normalize_level( $g->name ) ] = true;
	}
	$suggested = array_filter( if_suggested_groups( $tax ), function ( $n ) use ( $existing_keys ) { return ! isset( $existing_keys[ if_normalize_level( $n ) ] ); } );

	// Une seule liste, dans l'ordre : groupes existants + proposés.
	$items = array();
	foreach ( $groups as $g ) {
		$items[] = array( 'rank' => if_term_rank( $g ), 'name' => $g->name, 'id' => (int) $g->term_id );
	}
	foreach ( $suggested as $n ) {
		$r       = if_auto_rank( $n );
		$items[] = array( 'rank' => $r ? $r : 10000, 'name' => $n, 'id' => 0 );
	}
	usort( $items, function ( $a, $b ) { return $a['rank'] === $b['rank'] ? strnatcasecmp( $a['name'], $b['name'] ) : $a['rank'] - $b['rank']; } );

	echo '<input type="hidden" name="if_groups_sent" value="1">';
	echo '<div class="if-checklist">';
	foreach ( $items as $it ) {
		$key = if_normalize_level( $it['name'] );
		if ( $it['id'] ) {
			echo '<label><input type="checkbox" name="if_groups[]" data-key="' . esc_attr( $key ) . '" value="' . (int) $it['id'] . '" ' . checked( in_array( $it['id'], $selected, true ), true, false ) . '> ' . esc_html( $it['name'] ) . '</label>';
		} else {
			echo '<label class="if-suggested"><input type="checkbox" name="if_groups_new[]" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( $it['name'] ) . '"> ' . esc_html( $it['name'] ) . ' <em>' . esc_html__( '(proposé)', 'catalogue-formations' ) . '</em></label>';
		}
	}
	echo '</div>';
	echo '<p class="if-new-group"><label>' . esc_html__( '+ Nouveau groupe :', 'catalogue-formations' ) . ' <input type="text" name="if_groups_custom" class="if-groups-custom" placeholder="' . esc_attr( 'if_niveau' === $tax ? __( 'ex. Lycée pro', 'catalogue-formations' ) : __( 'ex. Expression', 'catalogue-formations' ) ) . '"></label> <span class="description">' . esc_html__( 'Plusieurs ? Séparez par des virgules.', 'catalogue-formations' ) . '</span></p>';
	if ( $suggested ) {
		echo '<p class="description">' . esc_html__( '« proposé » = groupe pas encore créé : il le sera si vous le cochez.', 'catalogue-formations' ) . '</p>';
	}

	// Pré-cocher les groupes d'un niveau connu pendant la saisie du nom (ex. 6ème → Collège + Cycle 3).
	$map  = array();
	$flat = if_presets_flat( $tax );
	foreach ( $flat as $name => $gs ) {
		$map[ if_normalize_level( $name ) ] = array_map( 'if_normalize_level', $gs );
	}
	if ( 'if_niveau' === $tax ) {
		// Autres écritures (seconde, sixième, première…).
		foreach ( if_canonical_names() as $alias => $canon ) {
			if ( isset( $flat[ $canon ] ) && ! isset( $map[ $alias ] ) ) {
				$map[ $alias ] = array_map( 'if_normalize_level', $flat[ $canon ] );
			}
		}
	}
	static $printed = false;
	if ( ! $printed ) {
		$printed = true;
		?>
		<script>
		(function () {
			var map = <?php echo wp_json_encode( $map ); ?>;
			function norm(s) {
				s = (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');
				s = s.replace(/^(\d)(eme|ieme|em|e)$/, '$1e').replace(/^1(ere|re|er)$/, '1re').replace(/^2(nde|nd|de)$/, '2de');
				return s;
			}
			var name = document.getElementById('tag-name');
			if (!name) { return; }
			var touched = false, auto = [];
			document.addEventListener('change', function (e) { if (e.target.matches('.if-checklist input')) { touched = true; } });
			name.addEventListener('input', function () {
				if (touched) { return; }
				var box = name.closest('form').querySelector('.if-checklist');
				if (!box) { return; }
				auto.forEach(function (c) { c.checked = false; });
				auto = [];
				(map[norm(name.value)] || []).forEach(function (k) {
					var c = box.querySelector('input[data-key="' + k + '"]');
					if (c && !c.checked) { c.checked = true; auto.push(c); }
				});
			});
			// Après un ajout (AJAX), WordPress vide le nom : on repart de zéro.
			if (window.jQuery) {
				jQuery(document).ajaxSuccess(function (ev, xhr, opts) {
					if (opts && typeof opts.data === 'string' && opts.data.indexOf('action=add-tag') !== -1) {
						touched = false; auto = [];
						var box = document.querySelector('#addtag .if-checklist');
						if (!box) { return; }
						// Les groupes cochés « proposés » ou tapés existent maintenant : on les garde dans la liste.
						var params = new URLSearchParams(opts.data);
						var typed = (params.get('if_groups_custom') || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean);
						params.getAll('if_groups_new[]').forEach(function (n) {
							var c = box.querySelector('input[name="if_groups_new[]"][value="' + n.replace(/"/g, '') + '"]');
							if (c) { var em = c.parentNode.querySelector('em'); if (em) { em.remove(); } }
						});
						typed.forEach(function (n) {
							if (box.querySelector('input[data-key="' + norm(n) + '"]')) { return; }
							var l = document.createElement('label');
							var i = document.createElement('input');
							i.type = 'checkbox'; i.name = 'if_groups_new[]'; i.value = n; i.setAttribute('data-key', norm(n));
							l.appendChild(i); l.appendChild(document.createTextNode(' ' + n));
							box.appendChild(l);
						});
						box.querySelectorAll('input').forEach(function (c) { c.checked = false; });
					}
				});
			}
		})();
		</script>
		<?php
	}
}

function if_groups_field_add( $tax ) {
	?>
	<div class="form-field">
		<label><?php esc_html_e( 'Groupes (facultatif)', 'catalogue-formations' ); ?></label>
		<?php if_groups_checkboxes( $tax, array() ); ?>
		<p><?php echo 'if_niveau' === $tax ? esc_html__( 'Cochez un ou plusieurs groupes. Exemple : 6ème → Collège et Cycle 3.', 'catalogue-formations' ) : esc_html__( 'Cochez un ou plusieurs groupes. Exemple : Anglais → Langues.', 'catalogue-formations' ); ?></p>
	</div>
	<?php
}

function if_groups_field_edit( $term ) {
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'Groupes', 'catalogue-formations' ); ?></th>
		<td><?php if_groups_checkboxes( $term->taxonomy, if_groups_of( $term->term_id ) ); ?>
		<p class="description"><?php esc_html_e( 'Un élément peut être dans plusieurs groupes.', 'catalogue-formations' ); ?></p></td>
	</tr>
	<?php
}

function if_groups_field_save( $term_id ) {
	if ( empty( $_POST['if_groups_sent'] ) || ! current_user_can( 'manage_categories' ) ) { // phpcs:ignore -- nonce vérifié par WordPress sur ce formulaire.
		return;
	}
	$ids = isset( $_POST['if_groups'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['if_groups'] ) ) : array(); // phpcs:ignore
	$term = get_term( $term_id );
	if ( $term && ! is_wp_error( $term ) ) {
		$names = ! empty( $_POST['if_groups_new'] ) ? (array) wp_unslash( $_POST['if_groups_new'] ) : array(); // phpcs:ignore
		if ( ! empty( $_POST['if_groups_custom'] ) ) { // phpcs:ignore
			$names = array_merge( $names, explode( ',', (string) wp_unslash( $_POST['if_groups_custom'] ) ) ); // phpcs:ignore
		}
		foreach ( $names as $n ) {
			$n = trim( sanitize_text_field( $n ) );
			if ( '' === $n ) {
				continue;
			}
			$gid = if_get_or_create_group( $n, if_group_tax( $term->taxonomy ) );
			if ( $gid ) {
				$ids[] = $gid;
			}
		}
	}
	if_set_groups( $term_id, $ids );
}

function if_groups_column( $cols ) {
	unset( $cols['description'] );
	$cols['if_groups'] = __( 'Groupes', 'catalogue-formations' );
	return $cols;
}

function if_groups_column_value( $out, $col, $term_id ) {
	if ( 'if_groups' !== $col ) {
		return $out;
	}
	$names = array();
	foreach ( if_groups_of( $term_id ) as $gid ) {
		$g = get_term( $gid );
		if ( $g && ! is_wp_error( $g ) ) {
			$names[] = $g->name;
		}
	}
	return $names ? esc_html( implode( ', ', $names ) ) : '—';
}

/* ---------- Champ « Contient » sur un groupe ---------- */

function if_members_checkboxes( $group_tax, $group_id ) {
	$tax     = if_member_tax( $group_tax );
	$members = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $members ) || ! $members ) {
		echo '<p class="description">' . esc_html__( 'Aucun élément pour le moment.', 'catalogue-formations' ) . '</p>';
		return;
	}
	usort( $members, 'if_compare_terms' );
	echo '<input type="hidden" name="if_members_sent" value="1">';
	echo '<div class="if-checklist">';
	foreach ( $members as $m ) {
		$on = $group_id && in_array( (int) $group_id, if_groups_of( $m->term_id ), true );
		echo '<label><input type="checkbox" name="if_members[]" value="' . (int) $m->term_id . '" ' . checked( $on, true, false ) . '> ' . esc_html( $m->name ) . '</label>';
	}
	echo '</div>';
}

function if_members_field_add( $group_tax ) {
	?>
	<div class="form-field">
		<label><?php echo 'if_groupe_niveau' === $group_tax ? esc_html__( 'Niveaux de ce groupe', 'catalogue-formations' ) : esc_html__( 'Matières de ce groupe', 'catalogue-formations' ); ?></label>
		<?php if_members_checkboxes( $group_tax, 0 ); ?>
	</div>
	<?php
}

function if_members_field_edit( $term ) {
	?>
	<tr class="form-field">
		<th scope="row"><?php echo 'if_groupe_niveau' === $term->taxonomy ? esc_html__( 'Niveaux de ce groupe', 'catalogue-formations' ) : esc_html__( 'Matières de ce groupe', 'catalogue-formations' ); ?></th>
		<td><?php if_members_checkboxes( $term->taxonomy, $term->term_id ); ?></td>
	</tr>
	<?php
}

function if_members_field_save( $group_id ) {
	if ( empty( $_POST['if_members_sent'] ) || ! current_user_can( 'manage_categories' ) ) { // phpcs:ignore
		return;
	}
	$term = get_term( $group_id );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}
	$wanted = isset( $_POST['if_members'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['if_members'] ) ) : array(); // phpcs:ignore
	foreach ( get_terms( array( 'taxonomy' => if_member_tax( $term->taxonomy ), 'hide_empty' => false ) ) as $m ) {
		$g = if_groups_of( $m->term_id );
		$in = in_array( (int) $group_id, $g, true );
		if ( in_array( (int) $m->term_id, $wanted, true ) && ! $in ) {
			$g[] = (int) $group_id;
			if_set_groups( $m->term_id, $g );
		} elseif ( ! in_array( (int) $m->term_id, $wanted, true ) && $in ) {
			if_set_groups( $m->term_id, array_diff( $g, array( (int) $group_id ) ) );
		}
	}
}

function if_members_column( $cols ) {
	unset( $cols['description'], $cols['posts'] );
	$cols['if_members'] = __( 'Contient', 'catalogue-formations' );
	return $cols;
}

function if_members_column_value( $out, $col, $term_id ) {
	if ( 'if_members' !== $col ) {
		return $out;
	}
	$term  = get_term( $term_id );
	$names = array();
	foreach ( if_members_of( $term_id, $term->taxonomy ) as $mid ) {
		$names[] = get_term( $mid )->name;
	}
	return $names ? esc_html( implode( ', ', $names ) ) : '—';
}

/** Quand un groupe est supprimé, on le retire des éléments. */
function if_group_before_delete( $term_id, $taxonomy ) {
	if ( ! in_array( $taxonomy, array( 'if_groupe_niveau', 'if_groupe_matiere' ), true ) ) {
		return;
	}
	foreach ( if_members_of( $term_id, $taxonomy ) as $mid ) {
		if_set_groups( $mid, array_diff( if_groups_of( $mid ), array( (int) $term_id ) ) );
	}
}

/** Masque le champ « parent » de WordPress (remplacé par les groupes) + style des cases. */
function if_hide_parent_field() {
	$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore
	if ( ! in_array( $tax, array( 'if_niveau', 'if_matiere', 'if_groupe_niveau', 'if_groupe_matiere' ), true ) ) {
		return;
	}
	echo '<style>.term-parent-wrap{display:none!important}.if-checklist{display:flex;flex-wrap:wrap;gap:4px 16px;max-height:220px;overflow:auto;padding:6px 8px;border:1px solid #dcdcde;border-radius:4px;background:#fff}.if-checklist label{display:inline-flex!important;align-items:center;gap:4px;font-weight:400!important;margin:0!important}.form-field .if-checklist input[type=checkbox]{width:auto}.if-checklist .if-suggested em{color:#646970;font-size:11px}.if-new-group{margin:6px 0 0}.if-new-group label{display:block;font-weight:600}.if-new-group input{width:100%!important;max-width:420px;display:block;margin-top:4px}</style>';
}

/* ---------- Conversion des anciens « parents » en groupes (une seule fois) ---------- */

function if_maybe_migrate_parents() {
	if ( get_option( 'if_groups_migrated' ) ) {
		return;
	}
	foreach ( array( 'if_niveau', 'if_matiere' ) as $tax ) {
		$gtax  = if_group_tax( $tax );
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		$parents = array();
		foreach ( $terms as $t ) {
			if ( $t->parent ) {
				$parent = get_term( $t->parent, $tax );
				if ( $parent && ! is_wp_error( $parent ) ) {
					$gid = if_get_or_create_group( $parent->name, $gtax );
					if ( $gid ) {
						if_add_group( $t->term_id, $gid );
					}
					$parents[ $parent->term_id ] = $parent;
				}
				wp_update_term( $t->term_id, $tax, array( 'parent' => 0 ) );
			}
		}
		// Un ancien parent sans notion directe n'est plus utile comme élément : on le supprime.
		foreach ( $parents as $p ) {
			$fresh = get_term( $p->term_id, $tax );
			if ( $fresh && ! is_wp_error( $fresh ) && 0 === (int) $fresh->count ) {
				wp_delete_term( $p->term_id, $tax );
			}
		}
	}
	update_option( 'if_groups_migrated', 1 );
}
