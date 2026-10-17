<?php
/**
 * Avancement, envoi des exercices, lecture protégée des PDF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_if_complete', 'if_handle_complete' );
add_action( 'admin_post_if_submit', 'if_handle_submit' );
add_action( 'admin_post_if_pdf', 'if_handle_pdf' );

/** Clés terminées (vidéos / PDF) d'un utilisateur pour une notion. */
function if_completed_keys( $user_id, $notion_id ) {
	global $wpdb;
	$t = if_table( 'progress' );
	return $wpdb->get_col( $wpdb->prepare( "SELECT item_key FROM $t WHERE user_id = %d AND notion_id = %d AND passed = 1", $user_id, $notion_id ) ); // phpcs:ignore
}

/** Dernière réponse par exercice : tableau [exercise_key => objet]. */
function if_user_submissions( $user_id, $notion_id ) {
	global $wpdb;
	$t    = if_table( 'submissions' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d AND notion_id = %d ORDER BY submitted_at ASC, id ASC", $user_id, $notion_id ) ); // phpcs:ignore
	$out  = array();
	foreach ( $rows as $r ) {
		$out[ $r->exercise_key ] = $r; // la plus récente écrase les précédentes.
	}
	return $out;
}

function if_get_submission( $id ) {
	global $wpdb;
	$t = if_table( 'submissions' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore
}

function if_get_submissions( $status = '' ) {
	global $wpdb;
	$t = if_table( 'submissions' );
	if ( $status ) {
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s ORDER BY submitted_at DESC", $status ) ); // phpcs:ignore
	}
	return $wpdb->get_results( "SELECT * FROM $t ORDER BY submitted_at DESC" ); // phpcs:ignore
}

/** Un élément est-il terminé ? (exercice = réponse envoyée). */
function if_item_done( $item, $completed, $subs ) {
	if ( 'exercise' === $item['type'] ) {
		return isset( $subs[ $item['key'] ] );
	}
	return in_array( $item['key'], $completed, true );
}

/**
 * Avancement d'un utilisateur sur une notion.
 * Retourne array( done, total, percent ).
 */
function if_progress( $user_id, $notion_id ) {
	$items     = if_get_items( $notion_id );
	$completed = if_completed_keys( $user_id, $notion_id );
	$subs      = if_user_submissions( $user_id, $notion_id );
	$done      = 0;
	foreach ( $items as $it ) {
		if ( if_item_done( $it, $completed, $subs ) ) {
			$done++;
		}
	}
	$total = count( $items );
	return array(
		'done'    => $done,
		'total'   => $total,
		'percent' => $total ? (int) round( 100 * $done / $total ) : 0,
	);
}

function if_find_item( $notion_id, $key, $type ) {
	$legacy = array( 'video' => '_if_videos', 'pdf' => '_if_pdfs', 'exercise' => '_if_exercises', 'h5p' => '_if_none' );
	foreach ( if_items_of_type( $notion_id, $type, isset( $legacy[ $type ] ) ? $legacy[ $type ] : '_if_none' ) as $it ) {
		if ( $it['key'] === $key ) {
			return $it;
		}
	}
	return null;
}

/** Bouton « J'ai terminé » (vidéo / PDF). */
function if_handle_complete() {
	$notion_id = isset( $_POST['notion_id'] ) ? absint( $_POST['notion_id'] ) : 0;
	$key       = isset( $_POST['item_key'] ) ? sanitize_key( wp_unslash( $_POST['item_key'] ) ) : '';
	$undo      = ! empty( $_POST['undo'] );
	check_admin_referer( 'if_complete_' . $notion_id . '_' . $key );

	$user = get_current_user_id();
	$back = get_permalink( $notion_id );
	if ( ! $user || ! if_user_can_view( $user, $notion_id ) ) {
		wp_safe_redirect( add_query_arg( 'if_msg', 'noaccess', $back ) );
		exit;
	}
	if ( ! if_find_item( $notion_id, $key, 'video' ) && ! if_find_item( $notion_id, $key, 'pdf' ) ) {
		wp_safe_redirect( $back );
		exit;
	}

	global $wpdb;
	$t = if_table( 'progress' );
	if ( $undo ) {
		$wpdb->delete( $t, array( 'user_id' => $user, 'notion_id' => $notion_id, 'item_key' => $key ) );
	} else {
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $t (user_id, notion_id, item_key, completed_at) VALUES (%d, %d, %s, %s)", $user, $notion_id, $key, if_now() ) ); // phpcs:ignore
	}
	wp_safe_redirect( $back . '#' . $key );
	exit;
}

/** Envoi d'une réponse à un exercice. */
function if_handle_submit() {
	$notion_id = isset( $_POST['notion_id'] ) ? absint( $_POST['notion_id'] ) : 0;
	$key       = isset( $_POST['exercise_key'] ) ? sanitize_key( wp_unslash( $_POST['exercise_key'] ) ) : '';
	check_admin_referer( 'if_submit_' . $notion_id . '_' . $key );

	$user = get_current_user_id();
	$back = get_permalink( $notion_id );
	if ( ! $user || ! if_user_can_view( $user, $notion_id ) || ! if_find_item( $notion_id, $key, 'exercise' ) ) {
		wp_safe_redirect( add_query_arg( 'if_msg', 'noaccess', $back ) );
		exit;
	}

	$answer  = isset( $_POST['answer'] ) ? wp_kses_post( wp_unslash( $_POST['answer'] ) ) : '';
	$file_id = 0;

	if ( ! empty( $_FILES['answer_file']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$allowed = array(
			'pdf'  => 'application/pdf',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'odt'  => 'application/vnd.oasis.opendocument.text',
			'jpg|jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'txt'  => 'text/plain',
		);
		$res = media_handle_upload( 'answer_file', 0, array(), array( 'test_form' => false, 'mimes' => $allowed ) );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( 'if_msg', 'upload', $back ) . '#' . $key );
			exit;
		}
		$file_id = (int) $res;
	}

	if ( '' === trim( wp_strip_all_tags( $answer ) ) && ! $file_id ) {
		wp_safe_redirect( add_query_arg( 'if_msg', 'empty', $back ) . '#' . $key );
		exit;
	}

	global $wpdb;
	$wpdb->insert(
		if_table( 'submissions' ),
		array(
			'user_id'      => $user,
			'notion_id'    => $notion_id,
			'exercise_key' => $key,
			'answer'       => $answer,
			'file_id'      => $file_id ? $file_id : null,
			'status'       => 'submitted',
			'submitted_at' => if_now(),
		)
	);
	do_action( 'if_exercise_submitted', (int) $wpdb->insert_id );

	wp_safe_redirect( add_query_arg( 'if_msg', 'submitted', $back ) . '#' . $key );
	exit;
}

/** Correction par l'admin. */
function if_correct_submission( $id, $feedback ) {
	global $wpdb;
	$wpdb->update(
		if_table( 'submissions' ),
		array(
			'status'       => 'corrected',
			'feedback'     => $feedback,
			'corrected_at' => if_now(),
		),
		array( 'id' => $id )
	);
	do_action( 'if_exercise_corrected', $id );
}

/** URL protégée pour ouvrir un PDF. */
function if_pdf_url( $notion_id, $key ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'    => 'if_pdf',
				'notion_id' => $notion_id,
				'key'       => $key,
			),
			admin_url( 'admin-post.php' )
		),
		'if_pdf_' . $notion_id . '_' . $key
	);
}

/** Envoie le PDF seulement si l'accès est ouvert. */
function if_handle_pdf() {
	$notion_id = isset( $_GET['notion_id'] ) ? absint( $_GET['notion_id'] ) : 0;
	$key       = isset( $_GET['key'] ) ? sanitize_key( wp_unslash( $_GET['key'] ) ) : '';
	check_admin_referer( 'if_pdf_' . $notion_id . '_' . $key );

	$user = get_current_user_id();
	$pdf  = if_find_item( $notion_id, $key, 'pdf' );
	if ( ! $user || ! $pdf || ! if_user_can_view( $user, $notion_id ) ) {
		wp_die( esc_html__( 'Vous n\'avez pas accès à ce document.', 'catalogue-formations' ), '', array( 'response' => 403 ) );
	}
	$path = get_attached_file( (int) $pdf['attachment_id'] );
	if ( ! $path || ! file_exists( $path ) ) {
		wp_die( esc_html__( 'Fichier introuvable.', 'catalogue-formations' ), '', array( 'response' => 404 ) );
	}
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . rawurlencode( basename( $path ) ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
