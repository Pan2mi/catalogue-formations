<?php
/**
 * Dupliquer une notion : copie en brouillon avec tous ses contenus (blocs ou Elementor),
 * niveaux, matières, image, réglages (parcours, restrictions…). Les inscrits ne sont pas copiés.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'post_row_actions', 'if_duplicate_row_action', 20, 2 );
add_action( 'admin_post_if_duplicate', 'if_duplicate_handle' );
add_action( 'post_submitbox_misc_actions', 'if_duplicate_classic_link' );
add_action( 'add_meta_boxes_if_notion', 'if_duplicate_metabox' );

function if_duplicate_url( $post_id ) {
	return wp_nonce_url( add_query_arg( array( 'action' => 'if_duplicate', 'post' => (int) $post_id ), admin_url( 'admin-post.php' ) ), 'if_duplicate_' . (int) $post_id );
}

function if_duplicate_row_action( $actions, $post ) {
	if ( 'if_notion' === $post->post_type && current_user_can( 'edit_post', $post->ID ) && current_user_can( 'edit_posts' ) ) {
		$actions['if_duplicate'] = '<a href="' . esc_url( if_duplicate_url( $post->ID ) ) . '">' . esc_html__( 'Dupliquer', 'catalogue-formations' ) . '</a>';
	}
	return $actions;
}

function if_duplicate_classic_link( $post ) {
	if ( 'if_notion' === $post->post_type && 'auto-draft' !== $post->post_status ) {
		echo '<div class="misc-pub-section"><a href="' . esc_url( if_duplicate_url( $post->ID ) ) . '">' . esc_html__( 'Dupliquer cette notion', 'catalogue-formations' ) . '</a></div>';
	}
}

/** Petit encadré dans l'éditeur (colonne de droite). */
function if_duplicate_metabox( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		return;
	}
	add_meta_box( 'if_duplicate_box', __( 'Dupliquer', 'catalogue-formations' ), 'if_duplicate_metabox_render', 'if_notion', 'side', 'low' );
}

function if_duplicate_metabox_render( $post ) {
	echo '<p class="description">' . esc_html__( 'Crée une copie en brouillon avec tous les contenus (sans les inscrits). Enregistrez d\'abord vos modifications.', 'catalogue-formations' ) . '</p>';
	echo '<a class="button" href="' . esc_url( if_duplicate_url( $post->ID ) ) . '">' . esc_html__( 'Dupliquer cette notion', 'catalogue-formations' ) . '</a>';
}

/** Crée la copie. Retourne l'identifiant de la nouvelle notion ou WP_Error. */
function if_duplicate_notion( $post_id ) {
	$src = get_post( $post_id );
	if ( ! $src || 'if_notion' !== $src->post_type ) {
		return new WP_Error( 'notion', __( 'Notion introuvable.', 'catalogue-formations' ) );
	}
	$new_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => 'if_notion',
				'post_status'    => 'draft',
				'post_title'     => sprintf( __( '%s (copie)', 'catalogue-formations' ), $src->post_title ),
				'post_content'   => $src->post_content,
				'post_excerpt'   => $src->post_excerpt,
				'post_author'    => get_current_user_id(),
				'menu_order'     => $src->menu_order,
				'comment_status' => $src->comment_status,
			)
		),
		true
	);
	if ( is_wp_error( $new_id ) ) {
		return $new_id;
	}
	// Niveaux, matières…
	foreach ( get_object_taxonomies( 'if_notion' ) as $tax ) {
		$terms = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			wp_set_object_terms( $new_id, $terms, $tax );
		}
	}
	// Réglages, image, données Elementor…
	$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_trash_meta_status', '_wp_trash_meta_time', '_elementor_css' );
	foreach ( get_post_meta( $post_id ) as $key => $values ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		foreach ( $values as $v ) {
			add_post_meta( $new_id, $key, wp_slash( maybe_unserialize( $v ) ) );
		}
	}
	if ( function_exists( 'if_sync_inline_items' ) ) {
		if_sync_inline_items( $new_id );
	}
	do_action( 'if_notion_duplicated', $new_id, $post_id );
	return $new_id;
}

function if_duplicate_handle() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	check_admin_referer( 'if_duplicate_' . $post_id );
	if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	$new = if_duplicate_notion( $post_id );
	if ( is_wp_error( $new ) ) {
		wp_die( esc_html( $new->get_error_message() ) );
	}
	wp_safe_redirect( add_query_arg( 'if_duplicated', 1, get_edit_post_link( $new, 'url' ) ) );
	exit;
}

add_action( 'admin_notices', 'if_duplicate_notice' );
function if_duplicate_notice() {
	if ( ! empty( $_GET['if_duplicated'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Copie créée (brouillon). Changez le titre, le niveau ou les contenus, puis publiez.', 'catalogue-formations' ) . '</p></div>';
	}
}
