<?php
/**
 * Accès aux notions.
 *
 * Statuts d'une ligne : pending (demande), active (accès accordé avec dates), refused.
 * Un accès « active » n'ouvre la notion qu'entre date_start et date_end (inclus).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_if_request', 'if_handle_request' );

function if_get_access_rows( $args = array() ) {
	global $wpdb;
	$t     = if_table( 'access' );
	$where = array( '1=1' );
	$vals  = array();
	if ( ! empty( $args['user_id'] ) ) {
		$where[] = 'user_id = %d';
		$vals[]  = (int) $args['user_id'];
	}
	if ( ! empty( $args['notion_id'] ) ) {
		$where[] = 'notion_id = %d';
		$vals[]  = (int) $args['notion_id'];
	}
	if ( ! empty( $args['status'] ) ) {
		$where[] = 'status = %s';
		$vals[]  = $args['status'];
	}
	$sql = "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY requested_at DESC';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $vals ? $wpdb->get_results( $wpdb->prepare( $sql, $vals ) ) : $wpdb->get_results( $sql );
}

function if_get_access( $id ) {
	global $wpdb;
	$t = if_table( 'access' );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) ); // phpcs:ignore
}

/**
 * État d'une ligne d'accès à aujourd'hui.
 * Retourne : pending | refused | active | upcoming | expired
 */
function if_row_state( $row ) {
	if ( 'active' !== $row->status ) {
		return $row->status;
	}
	$today = if_today();
	if ( $row->date_start && $today < $row->date_start ) {
		return 'upcoming';
	}
	if ( $row->date_end && $today > $row->date_end ) {
		return 'expired';
	}
	return 'active';
}

function if_state_label( $state ) {
	$labels = array(
		'none'     => __( 'Non commandée', 'catalogue-formations' ),
		'pending'  => __( 'Demande en attente', 'catalogue-formations' ),
		'refused'  => __( 'Demande refusée', 'catalogue-formations' ),
		'active'   => __( 'Accès ouvert', 'catalogue-formations' ),
		'upcoming' => __( 'Accès à venir', 'catalogue-formations' ),
		'expired'  => __( 'Accès terminé', 'catalogue-formations' ),
	);
	return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
}

/**
 * Meilleur état d'un utilisateur pour une notion (il peut avoir plusieurs périodes).
 * Retourne array( 'state' => ..., 'row' => objet|null ).
 */
function if_user_access( $user_id, $notion_id ) {
	$priority = array( 'active' => 5, 'upcoming' => 4, 'pending' => 3, 'expired' => 2, 'refused' => 1 );
	$best     = array( 'state' => 'none', 'row' => null );
	$best_p   = 0;
	if ( ! $user_id ) {
		return $best;
	}
	foreach ( if_get_access_rows( array( 'user_id' => $user_id, 'notion_id' => $notion_id ) ) as $row ) {
		$s = if_row_state( $row );
		$p = isset( $priority[ $s ] ) ? $priority[ $s ] : 0;
		if ( $p > $best_p ) {
			$best_p = $p;
			$best   = array( 'state' => $s, 'row' => $row );
		}
	}
	return $best;
}

/** L'utilisateur peut-il voir le contenu de la notion aujourd'hui ? */
function if_user_can_view( $user_id, $notion_id ) {
	if ( user_can( $user_id, 'edit_post', $notion_id ) ) {
		return true;
	}
	$a = if_user_access( $user_id, $notion_id );
	return 'active' === $a['state'];
}

/** Crée une demande (si aucune demande / accès en cours). */
function if_request_access( $user_id, $notion_id, $message = '' ) {
	global $wpdb;
	$current = if_user_access( $user_id, $notion_id );
	if ( in_array( $current['state'], array( 'pending', 'active', 'upcoming' ), true ) ) {
		return new WP_Error( 'exists', __( 'Une demande ou un accès existe déjà pour cette notion.', 'catalogue-formations' ) );
	}
	$wpdb->insert(
		if_table( 'access' ),
		array(
			'user_id'      => $user_id,
			'notion_id'    => $notion_id,
			'status'       => 'pending',
			'message'      => $message,
			'requested_at' => if_now(),
			'updated_at'   => if_now(),
		)
	);
	$id = (int) $wpdb->insert_id;
	do_action( 'if_access_requested', $id );
	return $id;
}

/**
 * Accorde (ou modifie) un accès avec des dates.
 * Si $access_id est fourni, la ligne existante est mise à jour (ex. validation d'une demande).
 */
function if_grant_access( $user_id, $notion_id, $date_start, $date_end, $access_id = 0 ) {
	global $wpdb;
	if ( $date_start && $date_end && $date_end < $date_start ) {
		return new WP_Error( 'dates', __( 'La date de fin doit être après la date de début.', 'catalogue-formations' ) );
	}
	$data = array(
		'status'     => 'active',
		'date_start' => $date_start ? $date_start : null,
		'date_end'   => $date_end ? $date_end : null,
		'updated_at' => if_now(),
	);
	if ( $access_id ) {
		$wpdb->update( if_table( 'access' ), $data, array( 'id' => $access_id ) );
	} else {
		$data['user_id']      = $user_id;
		$data['notion_id']    = $notion_id;
		$data['requested_at'] = if_now();
		$wpdb->insert( if_table( 'access' ), $data );
		$access_id = (int) $wpdb->insert_id;
	}
	do_action( 'if_access_granted', $access_id );
	return $access_id;
}

function if_refuse_access( $access_id ) {
	global $wpdb;
	$wpdb->update( if_table( 'access' ), array( 'status' => 'refused', 'updated_at' => if_now() ), array( 'id' => $access_id ) );
	do_action( 'if_access_refused', $access_id );
}

function if_delete_access( $access_id ) {
	global $wpdb;
	$wpdb->delete( if_table( 'access' ), array( 'id' => $access_id ) );
}

/** Formulaire « Commander » envoyé depuis le site. */
function if_handle_request() {
	$notion_id = isset( $_POST['notion_id'] ) ? absint( $_POST['notion_id'] ) : 0;
	$back      = wp_get_referer() ? wp_get_referer() : if_catalogue_url();

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( $back ) );
		exit;
	}
	check_admin_referer( 'if_request_' . $notion_id );

	if ( ! $notion_id || 'if_notion' !== get_post_type( $notion_id ) ) {
		wp_safe_redirect( add_query_arg( 'if_msg', 'error', $back ) );
		exit;
	}
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$res     = if_request_access( get_current_user_id(), $notion_id, $message );
	wp_safe_redirect( add_query_arg( 'if_msg', is_wp_error( $res ) ? 'exists' : 'requested', $back ) );
	exit;
}
