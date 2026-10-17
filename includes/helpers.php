<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Date du jour (fuseau du site), format Y-m-d. */
function if_today() {
	return current_time( 'Y-m-d' );
}

function if_now() {
	return current_time( 'mysql' );
}

/** Formate une date Y-m-d selon les réglages du site. */
function if_date( $ymd ) {
	if ( empty( $ymd ) ) {
		return '—';
	}
	return date_i18n( get_option( 'date_format' ), strtotime( $ymd ) );
}

/**
 * Contenus d'une notion.
 * Deux sources possibles :
 *  - « dans la page » : blocs WordPress ou widgets Elementor (méta _if_items_inline, dans l'ordre de la page) ;
 *  - « boîte » : l'ancienne boîte Contenus de la notion (méta _if_videos / _if_pdfs / _if_exercises).
 */
function if_inline_items( $notion_id ) {
	$v = get_post_meta( $notion_id, '_if_items_inline', true );
	return is_array( $v ) ? $v : null;
}

function if_uses_inline( $notion_id ) {
	return null !== if_inline_items( $notion_id );
}

function if_items_of_type( $notion_id, $type, $legacy_meta ) {
	$inline = if_inline_items( $notion_id );
	if ( null !== $inline ) {
		return array_values( array_filter( $inline, function ( $it ) use ( $type ) { return $it['type'] === $type; } ) );
	}
	$v = get_post_meta( $notion_id, $legacy_meta, true );
	return is_array( $v ) ? $v : array();
}

function if_get_videos( $notion_id ) {
	return if_items_of_type( $notion_id, 'video', '_if_videos' );
}

function if_get_pdfs( $notion_id ) {
	return if_items_of_type( $notion_id, 'pdf', '_if_pdfs' );
}

function if_get_exercises( $notion_id ) {
	return if_items_of_type( $notion_id, 'exercise', '_if_exercises' );
}

/**
 * Tous les éléments qui comptent dans l'avancement (dans l'ordre de la page si possible).
 * Retourne un tableau de [ 'key' => ..., 'type' => video|pdf|exercise, 'title' => ... ].
 */
function if_get_items( $notion_id ) {
	$inline = if_inline_items( $notion_id );
	if ( null !== $inline ) {
		return array_map( function ( $it ) { return array( 'key' => $it['key'], 'type' => $it['type'], 'title' => $it['title'] ); }, $inline );
	}
	$items = array();
	foreach ( if_get_videos( $notion_id ) as $v ) {
		$items[] = array( 'key' => $v['key'], 'type' => 'video', 'title' => $v['title'] );
	}
	foreach ( if_get_pdfs( $notion_id ) as $p ) {
		$items[] = array( 'key' => $p['key'], 'type' => 'pdf', 'title' => $p['title'] );
	}
	foreach ( if_get_exercises( $notion_id ) as $e ) {
		$items[] = array( 'key' => $e['key'], 'type' => 'exercise', 'title' => $e['title'] );
	}
	return $items;
}

function if_type_label( $type ) {
	$labels = array(
		'video'    => __( 'Vidéo', 'catalogue-formations' ),
		'pdf'      => __( 'PDF', 'catalogue-formations' ),
		'exercise' => __( 'Exercice', 'catalogue-formations' ),
		'h5p'      => __( 'Activité H5P', 'catalogue-formations' ),
	);
	return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
}

/** Génère une clé courte et stable pour un élément. */
function if_new_key( $prefix ) {
	return $prefix . '_' . substr( md5( uniqid( '', true ) ), 0, 8 );
}

/** Termes d'une notion, en texte. */
function if_terms_text( $notion_id, $tax ) {
	$terms = get_the_terms( $notion_id, $tax );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '—';
	}
	return implode( ', ', wp_list_pluck( $terms, 'name' ) );
}

/** E-mail(s) qui reçoivent les alertes. */
function if_admin_emails() {
	$custom = get_option( 'if_alert_email', '' );
	return $custom ? $custom : get_option( 'admin_email' );
}

/** URL de la page « tableau de bord » (réglage), ou l'accueil. */
function if_dashboard_url() {
	$page = (int) get_option( 'if_dashboard_page', 0 );
	return $page ? get_permalink( $page ) : home_url( '/' );
}

function if_catalogue_url() {
	$page = (int) get_option( 'if_catalogue_page', 0 );
	return $page ? get_permalink( $page ) : get_post_type_archive_link( 'if_notion' );
}
