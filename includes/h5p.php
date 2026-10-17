<?php
/**
 * Lien avec l'extension H5P : « Activité H5P suivie ».
 *
 * L'activité compte dans l'avancement quand l'apprenant la termine avec au moins
 * le score demandé (seuil en %, 0 = il suffit de la terminer).
 * Deux sources pour le résultat :
 *  1. H5P lui-même (action h5p_alter_user_result, quand « suivre les résultats » est activé) ;
 *  2. l'écoute des événements xAPI dans la page (filet de sécurité).
 * On garde le meilleur score.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'h5p_alter_user_result', 'if_h5p_from_plugin', 10, 4 );
add_action( 'wp_ajax_if_h5p_result', 'if_h5p_ajax_result' );
add_action( 'rest_api_init', 'if_h5p_rest' );
add_action( 'wp_enqueue_scripts', 'if_h5p_register_script' );

function if_h5p_active() {
	return shortcode_exists( 'h5p' ) || class_exists( 'H5P_Plugin' );
}

/** Activités H5P disponibles : [ id => [ title, type ] ]. */
function if_h5p_contents() {
	global $wpdb;
	if ( ! if_h5p_active() ) {
		return array();
	}
	$c = $wpdb->prefix . 'h5p_contents';
	$l = $wpdb->prefix . 'h5p_libraries';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $c ) ) !== $c ) { // phpcs:ignore
		return array();
	}
	$rows = $wpdb->get_results( "SELECT c.id, c.title, l.title AS type FROM $c c LEFT JOIN $l l ON l.id = c.library_id ORDER BY c.title ASC" ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ (int) $r->id ] = array( 'title' => $r->title, 'type' => (string) $r->type );
	}
	return $out;
}

function if_h5p_rest() {
	register_rest_route(
		'catalogue-formations/v1',
		'/h5p-contents',
		array(
			'methods'             => 'GET',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'callback'            => function () {
				$list = array();
				foreach ( if_h5p_contents() as $id => $c ) {
					$list[] = array( 'id' => $id, 'title' => $c['title'], 'type' => $c['type'] );
				}
				return array( 'active' => if_h5p_active(), 'contents' => $list, 'newUrl' => admin_url( 'admin.php?page=h5p_new' ) );
			},
		)
	);
}

/* ---------- Enregistrement du score ---------- */

/** Ligne d'avancement d'un élément (ou null). */
function if_progress_row( $user_id, $notion_id, $key ) {
	global $wpdb;
	$t = if_table( 'progress' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d AND notion_id = %d AND item_key = %s", $user_id, $notion_id, $key ) ); // phpcs:ignore
}

/** Enregistre un résultat en gardant le meilleur. Retourne true si l'activité est validée. */
function if_record_h5p_score( $user_id, $notion_id, $item, $score, $max ) {
	global $wpdb;
	$score  = max( 0, (int) $score );
	$max    = max( 0, (int) $max );
	$pass   = isset( $item['pass'] ) ? (int) $item['pass'] : 0;
	$ratio  = $max > 0 ? 100 * $score / $max : 100;
	$passed = $ratio >= $pass ? 1 : 0;
	$t      = if_table( 'progress' );
	$row    = if_progress_row( $user_id, $notion_id, $item['key'] );

	if ( ! $row ) {
		$wpdb->insert( $t, array( 'user_id' => $user_id, 'notion_id' => $notion_id, 'item_key' => $item['key'], 'completed_at' => if_now(), 'score' => $score, 'max_score' => $max, 'passed' => $passed ) );
	} else {
		$old = ( (int) $row->max_score > 0 ) ? 100 * (int) $row->score / (int) $row->max_score : ( null === $row->max_score ? 100 : 0 );
		if ( $ratio > $old || ( $passed && ! (int) $row->passed ) ) {
			$wpdb->update( $t, array( 'completed_at' => if_now(), 'score' => $score, 'max_score' => $max, 'passed' => max( $passed, (int) $row->passed ) ), array( 'id' => $row->id ) );
		}
	}
	$row = if_progress_row( $user_id, $notion_id, $item['key'] );
	return $row && (int) $row->passed;
}

/** Toutes les activités H5P suivies qui utilisent ce contenu H5P : [ [notion_id, item], … ]. */
function if_h5p_items_using( $content_id ) {
	global $wpdb;
	$out  = array();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_if_items_inline' AND meta_value LIKE %s", '%' . $wpdb->esc_like( 'h5p' ) . '%' ) ); // phpcs:ignore
	foreach ( $rows as $r ) {
		$items = maybe_unserialize( $r->meta_value );
		foreach ( (array) $items as $it ) {
			if ( isset( $it['type'], $it['content_id'] ) && 'h5p' === $it['type'] && (int) $it['content_id'] === (int) $content_id ) {
				$out[] = array( (int) $r->post_id, $it );
			}
		}
	}
	return $out;
}

/** Source 1 : H5P enregistre un résultat. */
function if_h5p_from_plugin( $data, $result_id, $content_id, $user_id ) {
	if ( ! $user_id || empty( $data['finished'] ) ) {
		return;
	}
	foreach ( if_h5p_items_using( $content_id ) as $pair ) {
		list( $notion_id, $item ) = $pair;
		if ( if_user_can_view( $user_id, $notion_id ) ) {
			if_record_h5p_score( $user_id, $notion_id, $item, isset( $data['score'] ) ? $data['score'] : 0, isset( $data['max_score'] ) ? $data['max_score'] : 0 );
		}
	}
}

/** Source 2 : la page a capté la fin de l'activité (xAPI). */
function if_h5p_ajax_result() {
	$notion_id = isset( $_POST['notion_id'] ) ? absint( $_POST['notion_id'] ) : 0;
	$key       = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	check_ajax_referer( 'if_h5p_' . $notion_id . '_' . $key, 'nonce' );
	$user = get_current_user_id();
	$item = if_find_item( $notion_id, $key, 'h5p' );
	if ( ! $user || ! $item || ! if_user_can_view( $user, $notion_id ) ) {
		wp_send_json_error( array( 'message' => 'noaccess' ), 403 );
	}
	$content = isset( $_POST['content_id'] ) ? absint( $_POST['content_id'] ) : 0;
	if ( $content && (int) $item['content_id'] !== $content ) {
		wp_send_json_error( array( 'message' => 'content' ), 400 );
	}
	$score  = isset( $_POST['score'] ) ? (int) $_POST['score'] : 0;
	$max    = isset( $_POST['max'] ) ? (int) $_POST['max'] : 0;
	$passed = if_record_h5p_score( $user, $notion_id, $item, $score, $max );
	$p      = if_progress( $user, $notion_id );
	wp_send_json_success( array( 'passed' => $passed, 'status' => if_h5p_status_text( $user, $notion_id, $item ), 'progress' => $p ) );
}

/* ---------- Affichage ---------- */

function if_h5p_register_script() {
	wp_register_script( 'cf-h5p-track', IF_URL . 'assets/h5p-track.js', array(), IF_VERSION, true );
	wp_localize_script( 'cf-h5p-track', 'cfH5P', array( 'ajax' => admin_url( 'admin-ajax.php' ) ) );
}

function if_h5p_status_text( $user, $notion_id, $item ) {
	$row  = if_progress_row( $user, $notion_id, $item['key'] );
	$pass = isset( $item['pass'] ) ? (int) $item['pass'] : 0;
	if ( $row && (int) $row->passed ) {
		$score = null !== $row->max_score && (int) $row->max_score > 0 ? sprintf( ' — %d / %d', $row->score, $row->max_score ) : '';
		return '✔ ' . __( 'Activité réussie', 'catalogue-formations' ) . $score;
	}
	if ( $row ) {
		return sprintf( __( 'Meilleur score : %1$d / %2$d. Il faut au moins %3$d %% pour valider. Vous pouvez réessayer.', 'catalogue-formations' ), $row->score, $row->max_score, $pass );
	}
	return $pass ? sprintf( __( 'Terminez l\'activité avec au moins %d %% pour la valider.', 'catalogue-formations' ), $pass ) : __( 'Terminez l\'activité pour la valider.', 'catalogue-formations' );
}

/** Contenu de l'activité H5P (appelé par if_render_item quand l'accès est ouvert). */
function if_render_h5p( $notion_id, $item ) {
	$cid = isset( $item['content_id'] ) ? (int) $item['content_id'] : 0;
	if ( ! if_h5p_active() ) {
		return '<p class="if-empty-item">' . esc_html__( 'L\'extension H5P n\'est pas active.', 'catalogue-formations' ) . '</p>';
	}
	if ( ! $cid ) {
		return '<p class="if-empty-item">' . esc_html__( 'Aucune activité H5P choisie.', 'catalogue-formations' ) . '</p>';
	}
	$user = get_current_user_id();
	$out  = '';
	if ( $user && ! empty( $item['key'] ) ) {
		wp_enqueue_script( 'cf-h5p-track' );
		$passed = ( $row = if_progress_row( $user, $notion_id, $item['key'] ) ) && (int) $row->passed; // phpcs:ignore
		$out   .= '<div class="cf-h5p" data-notion="' . (int) $notion_id . '" data-key="' . esc_attr( $item['key'] ) . '" data-content="' . $cid . '" data-nonce="' . esc_attr( wp_create_nonce( 'if_h5p_' . $notion_id . '_' . $item['key'] ) ) . '">';
		$out   .= do_shortcode( '[h5p id="' . $cid . '"]' );
		$out   .= '<p class="cf-h5p-status' . ( $passed ? ' is-passed' : '' ) . '" aria-live="polite">' . esc_html( if_h5p_status_text( $user, $notion_id, $item ) ) . '</p></div>';
		return $out;
	}
	return do_shortcode( '[h5p id="' . $cid . '"]' );
}
