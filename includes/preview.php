<?php
/**
 * « Voir comme un apprenant » : la formatrice voit le site comme le verrait…
 *  - noaccess : un apprenant connecté sans accès à la notion ;
 *  - fresh    : un apprenant qui a l'accès et commence (rien de fait) ;
 *  - user     : un apprenant précis (son accès, son avancement, ses réponses).
 * Lecture seule : toutes les actions (terminer, répondre, demander l'accès…) sont désactivées.
 * Réglage gardé par formatrice (métadonnée if_preview) jusqu'à « Quitter l'aperçu ».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IF_PREVIEW_UID', 999999999 ); // apprenant fictif (aucun compte).

add_action( 'admin_post_if_preview', 'if_preview_handle' );
add_action( 'admin_bar_menu', 'if_preview_admin_bar', 90 );
add_action( 'wp_body_open', 'if_preview_banner' );
add_action( 'wp_footer', 'if_preview_banner' );

/** Réglage d'aperçu de la formatrice connectée, ou null. */
function if_preview_get() {
	static $cache = false;
	if ( false !== $cache ) {
		return $cache;
	}
	$cache = null;
	if ( is_user_logged_in() && current_user_can( if_cap() ) ) {
		$p = get_user_meta( get_current_user_id(), 'if_preview', true );
		if ( is_array( $p ) && in_array( isset( $p['mode'] ) ? $p['mode'] : '', array( 'noaccess', 'fresh', 'user' ), true ) ) {
			if ( 'user' !== $p['mode'] || get_userdata( (int) $p['user'] ) ) {
				$cache = $p;
			}
		}
	}
	return $cache;
}

function if_preview_active() {
	return null !== if_preview_get();
}

/** Utilisateur « vu » sur le site : l'apprenant de l'aperçu, sinon l'utilisateur connecté. */
function if_viewer_id() {
	$p = if_preview_get();
	if ( ! $p ) {
		return get_current_user_id();
	}
	return 'user' === $p['mode'] ? (int) $p['user'] : IF_PREVIEW_UID;
}

/** Accès simulé pour l'apprenant fictif (appelé par if_user_access). */
function if_preview_fake_access( $notion_id ) {
	$p = if_preview_get();
	if ( ! $p || 'fresh' !== $p['mode'] ) {
		return array( 'state' => 'none', 'row' => null );
	}
	$row = (object) array(
		'id'           => 0,
		'user_id'      => IF_PREVIEW_UID,
		'notion_id'    => (int) $notion_id,
		'status'       => 'active',
		'date_start'   => if_today(),
		'date_end'     => gmdate( 'Y-m-d', strtotime( '+1 year', strtotime( if_today() ) ) ),
		'message'      => '',
		'requested_at' => if_now(),
	);
	return array( 'state' => 'active', 'row' => $row );
}

/** Texte « qui » de l'aperçu. */
function if_preview_label( $p = null ) {
	$p = $p ? $p : if_preview_get();
	if ( ! $p ) {
		return '';
	}
	if ( 'user' === $p['mode'] ) {
		$u = get_userdata( (int) $p['user'] );
		return $u ? $u->display_name : '';
	}
	return 'fresh' === $p['mode'] ? __( 'un apprenant qui commence (accès ouvert, rien de fait)', 'catalogue-formations' ) : __( 'un apprenant sans accès', 'catalogue-formations' );
}

/** Pour les actions : vrai (et message) si on est en aperçu. */
function if_preview_blocks_action() {
	return if_preview_active();
}

function if_preview_url( $mode, $user = 0, $redirect = '' ) {
	$args = array( 'action' => 'if_preview', 'mode' => $mode );
	if ( $user ) {
		$args['user'] = (int) $user;
	}
	if ( $redirect ) {
		$args['redirect'] = rawurlencode( $redirect );
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'if_preview' );
}

function if_preview_handle() {
	check_admin_referer( 'if_preview' );
	if ( ! current_user_can( if_cap() ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'off';
	$uid  = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
	if ( in_array( $mode, array( 'noaccess', 'fresh' ), true ) || ( 'user' === $mode && $uid && get_userdata( $uid ) ) ) {
		update_user_meta( get_current_user_id(), 'if_preview', array( 'mode' => $mode, 'user' => $uid ) );
	} else {
		delete_user_meta( get_current_user_id(), 'if_preview' );
	}
	$to = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : '';
	wp_safe_redirect( $to ? $to : home_url( '/' ) );
	exit;
}

/** Page du site où aller pour un apprenant : sa première notion ouverte, sinon le tableau de bord ou l'accueil. */
function if_preview_landing( $user_id = 0, $notion_id = 0 ) {
	if ( $notion_id ) {
		return get_permalink( $notion_id );
	}
	if ( $user_id ) {
		foreach ( if_get_access_rows( array( 'user_id' => $user_id ) ) as $r ) {
			if ( 'active' === if_row_state( $r ) ) {
				return get_permalink( $r->notion_id );
			}
		}
	}
	$dash = (int) get_option( 'if_dashboard_page' );
	return $dash ? get_permalink( $dash ) : home_url( '/' );
}

/* ---------- Barre d'outils WordPress (en haut du site) ---------- */

function if_preview_admin_bar( $bar ) {
	if ( is_admin() || ! current_user_can( if_cap() ) ) {
		return;
	}
	$here = ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
	$here = remove_query_arg( 'if_msg', $here );
	$p    = if_preview_get();
	$bar->add_node(
		array(
			'id'    => 'cf-preview',
			'title' => $p ? '👁 ' . esc_html( sprintf( __( 'Aperçu : %s', 'catalogue-formations' ), if_preview_label( $p ) ) ) : '👁 ' . esc_html__( 'Voir comme un apprenant', 'catalogue-formations' ),
			'href'  => '#',
			'meta'  => array( 'class' => $p ? 'cf-preview-on' : '' ),
		)
	);
	$items = array(
		'noaccess' => __( 'Un apprenant sans accès', 'catalogue-formations' ),
		'fresh'    => __( 'Un apprenant qui commence', 'catalogue-formations' ),
	);
	foreach ( $items as $mode => $label ) {
		$bar->add_node( array( 'parent' => 'cf-preview', 'id' => 'cf-preview-' . $mode, 'title' => esc_html( $label ), 'href' => if_preview_url( $mode, 0, $here ) ) );
	}
	// Apprenants inscrits à la notion affichée (sinon les derniers apprenants).
	$ids = array();
	if ( is_singular( 'if_notion' ) ) {
		foreach ( if_get_access_rows( array( 'notion_id' => get_queried_object_id() ) ) as $r ) {
			$ids[ (int) $r->user_id ] = true;
		}
	}
	if ( ! $ids && function_exists( 'if_learner_ids' ) ) {
		$ids = array_fill_keys( array_slice( if_learner_ids(), 0, 15 ), true );
	}
	foreach ( array_slice( array_keys( $ids ), 0, 15 ) as $uid ) {
		$u = get_userdata( $uid );
		if ( $u ) {
			$bar->add_node( array( 'parent' => 'cf-preview', 'id' => 'cf-preview-u' . $uid, 'title' => '👤 ' . esc_html( $u->display_name ), 'href' => if_preview_url( 'user', $uid, $here ) ) );
		}
	}
	if ( $p ) {
		$bar->add_node( array( 'parent' => 'cf-preview', 'id' => 'cf-preview-off', 'title' => '✖ ' . esc_html__( 'Quitter l\'aperçu', 'catalogue-formations' ), 'href' => if_preview_url( 'off', 0, $here ) ) );
	}
}

/* ---------- Bandeau sur le site pendant l'aperçu ---------- */

/** Page qui contient un shortcode (réglage d'abord, sinon recherche). */
function if_page_with_shortcode( $option, $shortcode ) {
	$id = (int) get_option( $option, 0 );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	$found = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 's' => '[' . $shortcode, 'fields' => 'ids', 'numberposts' => 1 ) );
	return $found ? (int) $found[0] : 0;
}

/** Liens « Catalogue » et « Tableau de bord » dans le bandeau d'aperçu. */
function if_preview_nav_links() {
	$out = '';
	foreach ( array( array( 'if_catalogue_page', 'if_catalogue', __( 'Catalogue', 'catalogue-formations' ) ), array( 'if_dashboard_page', 'if_tableau_de_bord', __( 'Tableau de bord', 'catalogue-formations' ) ) ) as $l ) {
		$id = if_page_with_shortcode( $l[0], $l[1] );
		if ( $id ) {
			$out .= ' <a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $l[2] ) . '</a>';
		}
	}
	return $out;
}

function if_preview_banner() {
	static $done = false;
	if ( $done || ! if_preview_active() ) {
		return;
	}
	$done = true;
	$here = ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
	echo '<div class="cf-preview-banner" role="status">👁 <strong>' . esc_html__( 'Aperçu', 'catalogue-formations' ) . '</strong> — ' . esc_html( sprintf( __( 'vous voyez le site comme %s. Les actions sont désactivées.', 'catalogue-formations' ), if_preview_label() ) ) . if_preview_nav_links() . ' <a href="' . esc_url( if_preview_url( 'off', 0, remove_query_arg( 'if_msg', $here ) ) ) . '">' . esc_html__( 'Quitter l\'aperçu', 'catalogue-formations' ) . '</a></div>';
	echo '<style>.cf-preview-banner{position:sticky;top:var(--wp-admin--admin-bar--height,0);z-index:99999;background:#1d2327;color:#fff;padding:8px 16px;font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;text-align:center}.cf-preview-banner a{color:#72aee6;margin-left:8px}#wpadminbar .cf-preview-on>.ab-item{background:#d63638!important;color:#fff!important}</style>';
	echo '<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".if-item form :is(button,input,textarea,select), .if-request :is(button,textarea), .if-card form :is(button,textarea), .if-complete :is(button)").forEach(function(e){if(e.type!=="hidden"){e.disabled=true;e.title="' . esc_js( __( 'Désactivé pendant l\'aperçu', 'catalogue-formations' ) ) . '";}});});</script>';
}
