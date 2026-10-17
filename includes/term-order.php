<?php
/**
 * Ordre des niveaux et des matières.
 *
 * 1. Automatique : les niveaux connus (CP… Terminale, cycles, A1… C2, Débutant…) sont rangés tout seuls.
 * 2. Manuel : bloc « Ordre d'affichage » sous la liste (glisser-déposer ou flèches ▲▼).
 *    Un ordre manuel enregistré remplace l'ordre automatique.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IF_ORDER_TAXES', array( 'if_niveau', 'if_matiere', 'if_groupe_niveau', 'if_groupe_matiere' ) );

foreach ( IF_ORDER_TAXES as $if_tax ) {
	add_action( 'after-' . $if_tax . '-table', 'if_order_panel' );
}
unset( $if_tax );
add_action( 'admin_post_if_save_order', 'if_order_save' );
add_action( 'admin_enqueue_scripts', 'if_order_assets' );

/** Texte simplifié pour reconnaître un niveau : minuscules, sans accents ni espaces. */
function if_normalize_level( $name ) {
	$s = strtolower( remove_accents( wp_strip_all_tags( $name ) ) );
	$s = preg_replace( '/[^a-z0-9]/', '', $s );
	// 6eme, 6ieme, 6e, 6em → 6e ; 1ere, 1re → 1re ; 2nde, 2nd, 2de → 2de
	$s = preg_replace( '/^(\d)(eme|ieme|em|e)$/', '$1e', $s );
	$s = preg_replace( '/^1(ere|re|er)$/', '1re', $s );
	$s = preg_replace( '/^2(nde|nd|de)$/', '2de', $s );
	return $s;
}

/** Rang automatique d'un niveau connu (0 = inconnu). */
function if_auto_rank( $name ) {
	static $ranks = null;
	if ( null === $ranks ) {
		$ranks = array(
			// Grands groupes et cycles.
			'maternelle' => 5, 'cycle1' => 6, 'primaire' => 8, 'elementaire' => 9, 'cycle2' => 9,
			'cycle3' => 39, 'college' => 55, 'cycle4' => 65, 'lycee' => 95,
			// Classes.
			'ps' => 1, 'petitesection' => 1, 'ms' => 2, 'moyennesection' => 2, 'gs' => 3, 'grandesection' => 3,
			'cp' => 10, 'ce1' => 20, 'ce2' => 30, 'cm1' => 40, 'cm2' => 50,
			'6e' => 60, 'sixieme' => 60, '5e' => 70, 'cinquieme' => 70, '4e' => 80, 'quatrieme' => 80, '3e' => 90, 'troisieme' => 90,
			'2de' => 100, 'seconde' => 100, '1re' => 110, 'premiere' => 110, 'terminale' => 120, 'tle' => 120,
			// Après le lycée / adultes.
			'superieur' => 130, 'postbac' => 130, 'adulte' => 140, 'adultes' => 140,
			// Niveaux généraux.
			'debutant' => 200, 'debutants' => 200, 'intermediaire' => 210, 'avance' => 220, 'avances' => 220, 'expert' => 230,
			// Langues (CECRL).
			'utilisateurelementaire' => 299, 'utilisateurindependant' => 319, 'utilisateurexperimente' => 339,
			'a1' => 300, 'a2' => 310, 'b1' => 320, 'b2' => 330, 'c1' => 340, 'c2' => 350,
		);
	}
	$key = if_normalize_level( $name );
	return isset( $ranks[ $key ] ) ? $ranks[ $key ] : 0;
}

/** Clé de tri : ordre manuel s'il existe, sinon rang automatique, sinon à la fin. */
function if_term_rank( $term ) {
	$manual = (int) get_term_meta( $term->term_id, 'if_order', true );
	if ( $manual > 0 ) {
		return $manual;
	}
	$auto = if_auto_rank( $term->name );
	return $auto ? $auto : 10000;
}

function if_compare_terms( $a, $b ) {
	$ra = if_term_rank( $a );
	$rb = if_term_rank( $b );
	return $ra === $rb ? strnatcasecmp( $a->name, $b->name ) : $ra - $rb;
}

/** Termes non vides, triés (liste à plat). */
function if_get_sorted_terms( $tax ) {
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	usort( $terms, 'if_compare_terms' );
	return $terms;
}

/* ---------- Bloc « Ordre d'affichage » ---------- */

function if_order_assets( $hook ) {
	if ( 'edit-tags.php' !== $hook || empty( $_GET['taxonomy'] ) || ! in_array( $_GET['taxonomy'], IF_ORDER_TAXES, true ) ) { // phpcs:ignore
		return;
	}
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script( 'if-order', IF_URL . 'assets/order.js', array( 'jquery', 'jquery-ui-sortable' ), IF_VERSION, true );
}

function if_order_render_list( $by_parent, $parent ) {
	if ( empty( $by_parent[ $parent ] ) ) {
		return;
	}
	$list = $by_parent[ $parent ];
	usort( $list, 'if_compare_terms' );
	echo '<ul class="if-order-list">';
	foreach ( $list as $t ) {
		echo '<li data-id="' . (int) $t->term_id . '"><div class="if-order-row">';
		echo '<span class="if-order-handle" aria-hidden="true">⠿</span>';
		echo '<span class="if-order-name">' . esc_html( $t->name ) . '</span>';
		echo '<button type="button" class="button button-small if-up" aria-label="' . esc_attr( sprintf( __( 'Monter %s', 'catalogue-formations' ), $t->name ) ) . '">▲</button>';
		echo '<button type="button" class="button button-small if-down" aria-label="' . esc_attr( sprintf( __( 'Descendre %s', 'catalogue-formations' ), $t->name ) ) . '">▼</button>';
		echo '</div>';
		if_order_render_list( $by_parent, (int) $t->term_id );
		echo '</li>';
	}
	echo '</ul>';
}

function if_order_panel( $taxonomy ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$all = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
	if ( is_wp_error( $all ) || ! $all ) {
		return;
	}
	$by_parent = array();
	foreach ( $all as $t ) {
		$by_parent[ (int) $t->parent ][] = $t;
	}
	$labels = array( 'if_niveau' => __( 'niveaux', 'catalogue-formations' ), 'if_matiere' => __( 'matières', 'catalogue-formations' ), 'if_groupe_niveau' => __( 'groupes de niveaux', 'catalogue-formations' ), 'if_groupe_matiere' => __( 'groupes de matières', 'catalogue-formations' ) );
	$label  = $labels[ $taxonomy ];
	?>
	<style>
		.if-order-box { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:12px 16px; margin-top:20px; max-width:520px; }
		.if-order-list { margin:6px 0 6px 0; padding:0; list-style:none; }
		.if-order-list .if-order-list { margin-left:28px; }
		.if-order-row { display:flex; align-items:center; gap:8px; background:#f6f7f7; border:1px solid #dcdcde; border-radius:4px; padding:6px 8px; margin:4px 0; }
		.if-order-handle { cursor:grab; font-size:18px; color:#787c82; }
		.if-order-name { flex:1; font-weight:600; }
		.if-order-placeholder { height:36px; border:2px dashed #2271b1; border-radius:4px; margin:4px 0; }
	</style>
	<div class="if-order-box">
		<h2><?php echo esc_html( sprintf( __( 'Ordre d\'affichage des %s', 'catalogue-formations' ), $label ) ); ?></h2>
		<p class="description"><?php esc_html_e( 'Rangés automatiquement quand c\'est possible (CP… Terminale, Primaire, Collège, cycles, A1…). Pour changer : glissez avec ⠿ ou utilisez ▲▼, puis enregistrez.', 'catalogue-formations' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="if-order-form">
			<input type="hidden" name="action" value="if_save_order">
			<input type="hidden" name="taxonomy" value="<?php echo esc_attr( $taxonomy ); ?>">
			<input type="hidden" name="order" id="if-order-data" value="">
			<?php wp_nonce_field( 'if_save_order' ); ?>
			<?php if_order_render_list( $by_parent, 0 ); ?>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer l\'ordre', 'catalogue-formations' ); ?></button>
				<button type="submit" name="reset" value="1" class="button"><?php esc_html_e( 'Revenir à l\'ordre automatique', 'catalogue-formations' ); ?></button>
			</p>
		</form>
		<?php if ( ! empty( $_GET['if_order'] ) ) : // phpcs:ignore ?>
			<p class="notice notice-success inline" style="padding:6px 10px"><?php esc_html_e( 'Ordre enregistré.', 'catalogue-formations' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function if_order_save() {
	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_save_order' );
	$tax = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
	if ( ! in_array( $tax, IF_ORDER_TAXES, true ) ) {
		wp_die( esc_html__( 'Taxonomie inconnue.', 'catalogue-formations' ) );
	}

	if ( ! empty( $_POST['reset'] ) ) {
		foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) ) as $id ) {
			delete_term_meta( $id, 'if_order' );
		}
	} else {
		// Format : "12,15,3" (ordre des ids, tous niveaux confondus) → 10, 20, 30…
		$ids = isset( $_POST['order'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['order'] ) ) ) ) ) : array();
		$pos = 0;
		foreach ( $ids as $id ) {
			$term = get_term( $id, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$pos += 10;
				update_term_meta( $id, 'if_order', $pos );
			}
		}
	}
	wp_safe_redirect( add_query_arg( array( 'taxonomy' => $tax, 'post_type' => 'if_notion', 'if_order' => 1 ), admin_url( 'edit-tags.php' ) ) );
	exit;
}
