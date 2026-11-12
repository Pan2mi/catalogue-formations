<?php
/**
 * Fichiers joints aux réponses des apprenants : protégés.
 *
 * - Rangés dans uploads/catalogue-formations-reponses/ (accès direct interdit sur Apache,
 *   nom de fichier aléatoire impossible à deviner sur les autres serveurs).
 * - Ouverts seulement par l'apprenant concerné et les formateurs (adresse avec vérification).
 * - Absents de la médiathèque.
 * - Les fichiers déjà envoyés sont déplacés une fois dans le dossier protégé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_if_answer_file', 'if_answer_file_serve' );
add_filter( 'ajax_query_attachments_args', 'if_answer_hide_from_library' );
add_action( 'pre_get_posts', 'if_answer_hide_from_list' );
add_action( 'admin_init', 'if_answer_migrate' );

define( 'IF_ANSWERS_DIR', 'catalogue-formations-reponses' );

/** Dossier protégé (créé si besoin). */
function if_answers_path( $basedir = '' ) {
	if ( ! $basedir ) {
		$up      = wp_upload_dir( null, false );
		$basedir = $up['basedir'];
	}
	$dir = trailingslashit( $basedir ) . IF_ANSWERS_DIR;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "# Catalogue de formations : fichiers des apprenants, accès direct interdit.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore
	}
	return $dir;
}

/** Pendant l'envoi d'une réponse : fichiers rangés dans le dossier protégé. */
function if_answers_upload_dir( $d ) {
	if_answers_path( $d['basedir'] ); // sans rappeler wp_upload_dir() (sinon boucle sans fin).
	$d['subdir'] = '/' . IF_ANSWERS_DIR;
	$d['path']   = $d['basedir'] . $d['subdir'];
	$d['url']    = $d['baseurl'] . $d['subdir'];
	return $d;
}

/**
 * Enregistre le fichier envoyé ($_FILES[ $field ]) comme fichier de réponse protégé.
 * Retourne l'identifiant du fichier ou WP_Error.
 */
function if_answer_store_upload( $field, $mimes ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	// Nom aléatoire : l'adresse du fichier ne peut pas être devinée.
	$_FILES[ $field ]['name'] = wp_generate_password( 16, false ) . '-' . sanitize_file_name( wp_unslash( $_FILES[ $field ]['name'] ) ); // phpcs:ignore
	add_filter( 'upload_dir', 'if_answers_upload_dir' );
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' ); // pas de miniatures publiques.
	$res = media_handle_upload( $field, 0, array( 'post_status' => 'private' ), array( 'test_form' => false, 'mimes' => $mimes ) );
	remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	remove_filter( 'upload_dir', 'if_answers_upload_dir' );
	if ( ! is_wp_error( $res ) ) {
		update_post_meta( $res, '_if_answer', 1 );
	}
	return $res;
}

/** Lien vers le fichier d'une réponse (vérifié à l'ouverture). */
function if_answer_file_url( $sub ) {
	return wp_nonce_url( add_query_arg( array( 'action' => 'if_answer_file', 'id' => (int) $sub->id ), admin_url( 'admin-post.php' ) ), 'if_answer_' . (int) $sub->id );
}

/** Nom lisible (sans le préfixe aléatoire). */
function if_answer_file_name( $file_id ) {
	return preg_replace( '/^[A-Za-z0-9]{16}-/', '', basename( (string) get_attached_file( (int) $file_id ) ) );
}

function if_answer_file_link( $sub ) {
	return '<p>📎 <a href="' . esc_url( if_answer_file_url( $sub ) ) . '" target="_blank" rel="noopener">' . esc_html( if_answer_file_name( $sub->file_id ) ) . '</a></p>';
}

function if_answer_file_serve() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( 'if_answer_' . $id );
	$sub  = $id ? if_get_submission( $id ) : null;
	$user = get_current_user_id();
	if ( ! $sub || ! $sub->file_id || ! $user || ( (int) $sub->user_id !== $user && ! user_can( $user, 'edit_post', (int) $sub->notion_id ) && ! current_user_can( if_cap() ) ) ) {
		wp_die( esc_html__( 'Vous n\'avez pas accès à ce fichier.', 'catalogue-formations' ), '', array( 'response' => 403 ) );
	}
	$path = get_attached_file( (int) $sub->file_id );
	if ( ! $path || ! file_exists( $path ) ) {
		wp_die( esc_html__( 'Fichier introuvable.', 'catalogue-formations' ), '', array( 'response' => 404 ) );
	}
	$type   = wp_check_filetype( $path );
	$mime   = $type['type'] ? $type['type'] : 'application/octet-stream';
	$inline = in_array( $mime, array( 'application/pdf', 'image/jpeg', 'image/png', 'text/plain' ), true );
	nocache_headers();
	header( 'Content-Type: ' . $mime );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . rawurlencode( if_answer_file_name( $sub->file_id ) ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/* ---------- Hors de la médiathèque ---------- */

function if_answer_meta_exclusion() {
	return array(
		'relation' => 'OR',
		array( 'key' => '_if_answer', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_if_answer', 'value' => '1', 'compare' => '!=' ),
	);
}

function if_answer_hide_from_library( $q ) {
	$q['meta_query'] = isset( $q['meta_query'] ) ? array( 'relation' => 'AND', $q['meta_query'], if_answer_meta_exclusion() ) : array( if_answer_meta_exclusion() ); // phpcs:ignore
	return $q;
}

function if_answer_hide_from_list( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'attachment' !== $q->get( 'post_type' ) ) {
		return;
	}
	$mq   = (array) $q->get( 'meta_query' );
	$mq[] = if_answer_meta_exclusion();
	$q->set( 'meta_query', $mq );
}

/* ---------- Déplacement des fichiers déjà envoyés (une fois) ---------- */

function if_answer_migrate() {
	if ( get_option( 'if_answers_migrated' ) ) {
		return;
	}
	global $wpdb;
	$t   = if_table( 'submissions' );
	$ids = $wpdb->get_col( "SELECT DISTINCT file_id FROM $t WHERE file_id IS NOT NULL AND file_id > 0" ); // phpcs:ignore
	$dir = if_answers_path();
	foreach ( $ids as $file_id ) {
		$file_id = (int) $file_id;
		$path    = get_attached_file( $file_id );
		if ( ! $path || ! file_exists( $path ) || 0 === strpos( wp_normalize_path( $path ), wp_normalize_path( $dir ) ) ) {
			update_post_meta( $file_id, '_if_answer', 1 );
			continue;
		}
		// Miniatures publiques supprimées.
		$meta = wp_get_attachment_metadata( $file_id );
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				$thumb = path_join( dirname( $path ), $size['file'] );
				if ( file_exists( $thumb ) ) {
					wp_delete_file( $thumb );
				}
			}
			$meta['sizes'] = array();
		}
		$new = $dir . '/' . wp_generate_password( 16, false ) . '-' . basename( $path );
		if ( @rename( $path, $new ) ) { // phpcs:ignore
			update_attached_file( $file_id, $new );
			if ( is_array( $meta ) ) {
				$meta['file'] = IF_ANSWERS_DIR . '/' . basename( $new );
				wp_update_attachment_metadata( $file_id, $meta );
			}
		}
		update_post_meta( $file_id, '_if_answer', 1 );
		wp_update_post( array( 'ID' => $file_id, 'post_status' => 'private' ) );
	}
	update_option( 'if_answers_migrated', 1 );
}
