<?php
/**
 * Listes prêtes à cocher (niveaux, matières) + harmonisation des noms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'if_niveau_pre_add_form', 'if_presets_box' );
add_action( 'if_matiere_pre_add_form', 'if_presets_box' );
add_action( 'admin_post_if_add_presets', 'if_presets_save' );
add_action( 'admin_post_if_harmonize', 'if_harmonize_save' );

/**
 * Listes prêtes.
 * Format : [ section affichée => [ nom de l'élément => [ groupes auxquels il appartient ] ] ]
 */
function if_presets( $tax ) {
	if ( 'if_matiere' === $tax ) {
		$lang = array( 'Langues' );
		$sci  = array( 'Sciences' );
		$shs  = array( 'Sciences humaines et sociales' );
		$num  = array( 'Numérique' );
		$art  = array( 'Arts' );
		$tr   = array( 'Compétences transversales' );
		return array(
			__( 'Matières générales', 'catalogue-formations' ) => array(
				'Français' => array(), 'Mathématiques' => $sci, 'Histoire-Géographie' => $shs, 'EMC' => $shs,
				'Philosophie' => $shs, 'SES' => $shs,
			),
			__( 'Sciences', 'catalogue-formations' ) => array( 'Sciences' => $sci, 'SVT' => $sci, 'Physique-Chimie' => $sci, 'Technologie' => $sci ),
			__( 'Langues', 'catalogue-formations' )  => array( 'Anglais' => $lang, 'Espagnol' => $lang, 'Allemand' => $lang, 'Italien' => $lang, 'FLE (Français langue étrangère)' => $lang ),
			__( 'Numérique', 'catalogue-formations' ) => array( 'SNT' => $num, 'NSI' => $num, 'Informatique' => $num, 'Bureautique' => $num ),
			__( 'Arts et sport', 'catalogue-formations' ) => array( 'Arts plastiques' => $art, 'Éducation musicale' => $art, 'EPS' => array() ),
			__( 'Compétences transversales', 'catalogue-formations' ) => array( 'Méthodologie' => $tr, 'Orientation' => $tr, 'Compétences sociales' => $tr, 'Autonomie' => $tr ),
		);
	}
	return array(
		'Maternelle' => array( 'PS' => array( 'Maternelle', 'Cycle 1' ), 'MS' => array( 'Maternelle', 'Cycle 1' ), 'GS' => array( 'Maternelle', 'Cycle 1' ) ),
		'Primaire'   => array(
			'CP'  => array( 'Primaire', 'Cycle 2' ), 'CE1' => array( 'Primaire', 'Cycle 2' ), 'CE2' => array( 'Primaire', 'Cycle 2' ),
			'CM1' => array( 'Primaire', 'Cycle 3' ), 'CM2' => array( 'Primaire', 'Cycle 3' ),
		),
		'Collège'    => array( '6ème' => array( 'Collège', 'Cycle 3' ), '5ème' => array( 'Collège', 'Cycle 4' ), '4ème' => array( 'Collège', 'Cycle 4' ), '3ème' => array( 'Collège', 'Cycle 4' ) ),
		'Lycée'      => array( '2de' => array( 'Lycée' ), '1ère' => array( 'Lycée' ), 'Terminale' => array( 'Lycée' ) ),
		__( 'Niveaux généraux', 'catalogue-formations' ) => array( 'Débutant' => array(), 'Intermédiaire' => array(), 'Avancé' => array() ),
		__( 'Langues (CECRL)', 'catalogue-formations' )  => array(
			'A1' => array( 'Utilisateur élémentaire' ), 'A2' => array( 'Utilisateur élémentaire' ),
			'B1' => array( 'Utilisateur indépendant' ), 'B2' => array( 'Utilisateur indépendant' ),
			'C1' => array( 'Utilisateur expérimenté' ), 'C2' => array( 'Utilisateur expérimenté' ),
		),
		__( 'Autres publics', 'catalogue-formations' ) => array( 'Adultes' => array(), 'Post-bac' => array() ),
	);
}

/** Tous les éléments prêts à plat : nom => groupes. */
function if_presets_flat( $tax ) {
	$out = array();
	foreach ( if_presets( $tax ) as $items ) {
		$out += $items;
	}
	return $out;
}

/** Écriture officielle d'un niveau reconnu (clé normalisée → nom). */
function if_canonical_names() {
	$map = array();
	foreach ( if_presets_flat( 'if_niveau' ) as $n => $groups ) {
		$map[ if_normalize_level( $n ) ] = $n;
	}
	// Autres écritures fréquentes.
	$map['sixieme']   = '6ème';
	$map['cinquieme'] = '5ème';
	$map['quatrieme'] = '4ème';
	$map['troisieme'] = '3ème';
	$map['seconde']   = '2de';
	$map['premiere']  = '1ère';
	$map['tle']       = 'Terminale';
	return $map;
}

/** Terme existant portant ce nom (en ignorant accents, espaces et variantes). */
function if_find_term_like( $name, $tax ) {
	$key = if_normalize_level( $name );
	foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) as $t ) {
		if ( if_normalize_level( $t->name ) === $key ) {
			return $t;
		}
	}
	return null;
}

function if_presets_box( $tax ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$is_niv = 'if_niveau' === $tax;
	$done   = isset( $_GET['if_presets'] ) ? absint( $_GET['if_presets'] ) : -1; // phpcs:ignore
	$renamed = isset( $_GET['if_renamed'] ) ? absint( $_GET['if_renamed'] ) : -1; // phpcs:ignore
	?>
	<style>
		.if-presets { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:12px 16px; margin-bottom:20px; }
		.if-presets fieldset { margin:10px 0; }
		.if-presets legend { font-weight:600; margin-bottom:4px; }
		.if-presets label { display:inline-block; margin:2px 14px 4px 0; vertical-align:top; }
		.if-presets .if-preset-groups { display:block; font-size:11px; color:#646970; margin-left:22px; }
		.if-presets .if-group-all { font-weight:400; font-size:12px; margin-left:6px; }
		.if-presets-actions { background:#f0f6fc; border:1px solid #c5d9ed; border-radius:4px; padding:8px 12px; margin-top:12px; }
		.if-presets-choice { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:8px 0; }
		.if-presets-example { color:#50575e; font-style:italic; }
		.if-presets summary { cursor:pointer; font-size:1.2em; font-weight:600; padding:4px 0; }
		.if-presets[open] summary { margin-bottom:8px; }
		.if-presets .if-presets-hint { font-weight:400; font-size:12px; color:#646970; }
		.if-presets[open] .if-presets-hint { display:none; }
	</style>
	<details class="if-presets" <?php echo ( $done >= 0 || $renamed >= 0 ) ? 'open' : ''; ?>>
		<summary><?php echo $is_niv ? esc_html__( 'Ajouter des niveaux prêts à l\'emploi', 'catalogue-formations' ) : esc_html__( 'Ajouter des matières prêtes à l\'emploi', 'catalogue-formations' ); ?> <span class="if-presets-hint"><?php esc_html_e( '(cliquer pour ouvrir)', 'catalogue-formations' ); ?></span></summary>
		<p class="description"><?php esc_html_e( 'Cochez ce que vous voulez, puis cliquez sur « Ajouter la sélection ». Chaque élément est rangé automatiquement dans ses groupes. Vous pouvez toujours créer un élément libre plus bas.', 'catalogue-formations' ); ?></p>

		<?php $moved_q = isset( $_GET['if_moved'] ) ? absint( $_GET['if_moved'] ) : 0; // phpcs:ignore ?>
		<?php if ( $done > 0 || ( 0 === $done && 0 === $moved_q ) ) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html( sprintf( _n( '%d élément ajouté.', '%d éléments ajoutés.', $done, 'catalogue-formations' ), $done ) ); ?></p></div>
		<?php endif; ?>
		<?php $moved = isset( $_GET['if_moved'] ) ? absint( $_GET['if_moved'] ) : 0; // phpcs:ignore ?>
		<?php if ( $moved > 0 ) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html( sprintf( _n( '%d lien avec un groupe ajouté.', '%d liens avec des groupes ajoutés.', $moved, 'catalogue-formations' ), $moved ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( $renamed >= 0 ) : ?>
			<div class="notice notice-success inline"><p><?php echo esc_html( sprintf( _n( '%d nom corrigé.', '%d noms corrigés.', $renamed, 'catalogue-formations' ), $renamed ) ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="if_add_presets">
			<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $tax ); ?>">
			<?php wp_nonce_field( 'if_add_presets' ); ?>
			<?php foreach ( if_presets( $tax ) as $section => $items ) : ?>
				<fieldset>
					<legend><?php echo esc_html( $section ); ?>
						<label class="if-group-all"><input type="checkbox" class="if-check-all"> <?php esc_html_e( 'tout', 'catalogue-formations' ); ?></label>
					</legend>
					<?php foreach ( $items as $n => $groups ) : ?>
						<?php $exists = (bool) if_find_term_like( $n, $tax ); ?>
						<label title="<?php echo esc_attr( $groups ? sprintf( __( 'Groupes : %s', 'catalogue-formations' ), implode( ', ', $groups ) ) : __( 'Sans groupe', 'catalogue-formations' ) ); ?>">
							<input type="checkbox" name="presets[]" value="<?php echo esc_attr( $n ); ?>">
							<?php echo esc_html( $n ); ?><?php echo $exists ? ' ✓' : ''; ?>
							<?php if ( $groups ) : ?><span class="if-preset-groups"><?php echo esc_html( implode( ' · ', $groups ) ); ?></span><?php endif; ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
			<p class="description">✓ = <?php esc_html_e( 'déjà présent : il n\'est pas créé en double, mais ses groupes sont complétés.', 'catalogue-formations' ); ?></p>
			<p class="description"><?php esc_html_e( 'En petit sous chaque élément : les groupes dans lesquels il sera rangé (créés automatiquement si besoin).', 'catalogue-formations' ); ?></p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Ajouter la sélection', 'catalogue-formations' ); ?></button></p>
		</form>

		<?php if ( $is_niv ) : ?>
			<hr>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="if_harmonize">
				<?php wp_nonce_field( 'if_harmonize' ); ?>
				<p><button type="submit" class="button"><?php esc_html_e( 'Harmoniser les noms', 'catalogue-formations' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'Corrige l\'écriture des niveaux reconnus : 6ième → 6ème, 2nd → 2de, 1ere → 1ère, seconde → 2de… Les notions restent rattachées.', 'catalogue-formations' ); ?></p>
			</form>
		<?php endif; ?>
	</details>
	<script>
	document.querySelectorAll('.if-presets .if-check-all').forEach(function (all) {
		all.addEventListener('change', function () {
			all.closest('fieldset').querySelectorAll('input[name^="presets"]').forEach(function (c) { c.checked = all.checked; });
		});
	});
	</script>
	<?php
}

function if_presets_save() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_add_presets' );
	$tax = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
	if ( ! in_array( $tax, array( 'if_niveau', 'if_matiere' ), true ) ) {
		wp_die( esc_html__( 'Taxonomie inconnue.', 'catalogue-formations' ) );
	}
	$allowed = if_presets_flat( $tax );
	$posted  = isset( $_POST['presets'] ) && is_array( $_POST['presets'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['presets'] ) ) : array(); // phpcs:ignore
	$gtax    = if_group_tax( $tax );
	$added   = 0;
	$moved   = 0;

	foreach ( $posted as $n ) {
		if ( ! isset( $allowed[ $n ] ) ) {
			continue;
		}
		$existing = if_find_term_like( $n, $tax );
		if ( $existing ) {
			$term_id = (int) $existing->term_id;
		} else {
			$res = wp_insert_term( $n, $tax );
			if ( is_wp_error( $res ) ) {
				continue;
			}
			$term_id = (int) $res['term_id'];
			$added++;
		}
		foreach ( $allowed[ $n ] as $group_name ) {
			$gid = if_get_or_create_group( $group_name, $gtax );
			if ( $gid && if_add_group( $term_id, $gid ) && $existing ) {
				$moved++;
			}
		}
	}
	wp_safe_redirect( add_query_arg( array( 'taxonomy' => $tax, 'post_type' => 'if_notion', 'if_presets' => $added, 'if_moved' => $moved ), admin_url( 'edit-tags.php' ) ) );
	exit;
}

function if_harmonize_save() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_harmonize' );
	$map     = if_canonical_names();
	$renamed = 0;
	foreach ( get_terms( array( 'taxonomy' => 'if_niveau', 'hide_empty' => false ) ) as $t ) {
		$key = if_normalize_level( $t->name );
		if ( isset( $map[ $key ] ) && $map[ $key ] !== $t->name ) {
			$res = wp_update_term( $t->term_id, 'if_niveau', array( 'name' => $map[ $key ], 'slug' => sanitize_title( $map[ $key ] ) ) );
			if ( is_wp_error( $res ) ) {
				// Slug déjà pris : on ne change que le nom.
				$res = wp_update_term( $t->term_id, 'if_niveau', array( 'name' => $map[ $key ] ) );
			}
			if ( ! is_wp_error( $res ) ) {
				$renamed++;
			}
		}
	}
	wp_safe_redirect( add_query_arg( array( 'taxonomy' => 'if_niveau', 'post_type' => 'if_notion', 'if_renamed' => $renamed ), admin_url( 'edit-tags.php' ) ) );
	exit;
}
