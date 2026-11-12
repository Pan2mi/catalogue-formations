<?php
/**
 * Données personnelles (RGPD).
 *
 * - Compte supprimé → ses accès, son avancement, ses réponses et les fichiers joints sont supprimés.
 * - Outils > Exporter les données personnelles : export des accès, de l'avancement et des réponses.
 * - Outils > Effacer les données personnelles : effacement des mêmes données.
 * - Réglages > Confidentialité : texte proposé pour la politique de confidentialité.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'delete_user', 'if_privacy_delete_user' );
add_action( 'wpmu_delete_user', 'if_privacy_delete_user' );
add_filter( 'wp_privacy_personal_data_exporters', 'if_privacy_register_exporter' );
add_filter( 'wp_privacy_personal_data_erasers', 'if_privacy_register_eraser' );
add_action( 'admin_init', 'if_privacy_policy_text' );

/** Supprime toutes les données de formation d'un utilisateur. Retourne le nombre d'éléments supprimés. */
function if_privacy_erase_user( $user_id ) {
	global $wpdb;
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return 0;
	}
	$subs  = if_table( 'submissions' );
	$count = 0;
	// Fichiers joints aux réponses.
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT file_id FROM $subs WHERE user_id = %d AND file_id IS NOT NULL AND file_id > 0", $user_id ) ) as $file_id ) { // phpcs:ignore
		if ( wp_delete_attachment( (int) $file_id, true ) ) {
			$count++;
		}
	}
	$count += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $user_id, $wpdb->esc_like( '_if_quiz_' ) . '%' ) ); // phpcs:ignore
	foreach ( array( 'submissions', 'progress', 'access' ) as $t ) {
		$count += (int) $wpdb->delete( if_table( $t ), array( 'user_id' => $user_id ) );
	}
	return $count;
}

function if_privacy_delete_user( $user_id ) {
	if_privacy_erase_user( $user_id );
}

/* ---------- Export ---------- */

function if_privacy_register_exporter( $exporters ) {
	$exporters['catalogue-formations'] = array(
		'exporter_friendly_name' => __( 'Catalogue de formations', 'catalogue-formations' ),
		'callback'               => 'if_privacy_exporter',
	);
	return $exporters;
}

function if_privacy_exporter( $email, $page = 1 ) {
	$user = get_user_by( 'email', $email );
	$data = array();
	if ( ! $user ) {
		return array( 'data' => $data, 'done' => true );
	}
	global $wpdb;
	$uid    = (int) $user->ID;
	$titles = array();
	$title  = function ( $notion_id ) use ( &$titles ) {
		if ( ! isset( $titles[ $notion_id ] ) ) {
			$titles[ $notion_id ] = get_the_title( $notion_id ) ? get_the_title( $notion_id ) : '#' . $notion_id;
		}
		return $titles[ $notion_id ];
	};
	$item_title = function ( $notion_id, $key ) {
		foreach ( if_get_items( $notion_id, true ) as $it ) {
			if ( $it['key'] === $key ) {
				return $it['title'];
			}
		}
		return $key;
	};

	foreach ( if_get_access_rows( array( 'user_id' => $uid ) ) as $r ) {
		$data[] = array(
			'group_id'    => 'if-access',
			'group_label' => __( 'Accès aux notions', 'catalogue-formations' ),
			'item_id'     => 'if-access-' . $r->id,
			'data'        => array(
				array( 'name' => __( 'Notion', 'catalogue-formations' ), 'value' => $title( $r->notion_id ) ),
				array( 'name' => __( 'État', 'catalogue-formations' ), 'value' => if_state_label( if_row_state( $r ) ) ),
				array( 'name' => __( 'Du', 'catalogue-formations' ), 'value' => (string) $r->date_start ),
				array( 'name' => __( 'Au', 'catalogue-formations' ), 'value' => (string) $r->date_end ),
				array( 'name' => __( 'Message de la demande', 'catalogue-formations' ), 'value' => (string) $r->message ),
				array( 'name' => __( 'Demandé le', 'catalogue-formations' ), 'value' => (string) $r->requested_at ),
			),
		);
	}

	$t = if_table( 'progress' );
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d", $uid ) ) as $r ) { // phpcs:ignore
		$row = array(
			array( 'name' => __( 'Notion', 'catalogue-formations' ), 'value' => $title( $r->notion_id ) ),
			array( 'name' => __( 'Contenu', 'catalogue-formations' ), 'value' => $item_title( $r->notion_id, $r->item_key ) ),
			array( 'name' => __( 'Date', 'catalogue-formations' ), 'value' => (string) $r->completed_at ),
		);
		if ( null !== $r->score ) {
			$row[] = array( 'name' => __( 'Score', 'catalogue-formations' ), 'value' => $r->score . ' / ' . $r->max_score );
		}
		$data[] = array( 'group_id' => 'if-progress', 'group_label' => __( 'Avancement', 'catalogue-formations' ), 'item_id' => 'if-progress-' . $r->id, 'data' => $row );
	}

	$t = if_table( 'submissions' );
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d", $uid ) ) as $r ) { // phpcs:ignore
		$row = array(
			array( 'name' => __( 'Notion', 'catalogue-formations' ), 'value' => $title( $r->notion_id ) ),
			array( 'name' => __( 'Exercice', 'catalogue-formations' ), 'value' => $item_title( $r->notion_id, $r->exercise_key ) ),
			array( 'name' => __( 'Envoyée le', 'catalogue-formations' ), 'value' => (string) $r->submitted_at ),
			array( 'name' => __( 'Réponse', 'catalogue-formations' ), 'value' => wp_strip_all_tags( (string) $r->answer ) ),
		);
		if ( $r->file_id ) {
			$row[] = array( 'name' => __( 'Fichier joint', 'catalogue-formations' ), 'value' => if_answer_file_name( $r->file_id ) );
		}
		$row[] = array( 'name' => __( 'État', 'catalogue-formations' ), 'value' => if_submission_label( $r ) );
		if ( $r->feedback ) {
			$row[] = array( 'name' => __( 'Correction', 'catalogue-formations' ), 'value' => wp_strip_all_tags( (string) $r->feedback ) );
		}
		$data[] = array( 'group_id' => 'if-submissions', 'group_label' => __( 'Réponses aux exercices', 'catalogue-formations' ), 'item_id' => 'if-sub-' . $r->id, 'data' => $row );
	}
	return array( 'data' => $data, 'done' => true );
}

/* ---------- Effacement ---------- */

function if_privacy_register_eraser( $erasers ) {
	$erasers['catalogue-formations'] = array(
		'eraser_friendly_name' => __( 'Catalogue de formations', 'catalogue-formations' ),
		'callback'             => 'if_privacy_eraser',
	);
	return $erasers;
}

function if_privacy_eraser( $email, $page = 1 ) {
	$user  = get_user_by( 'email', $email );
	$count = $user ? if_privacy_erase_user( $user->ID ) : 0;
	return array(
		'items_removed'  => $count > 0,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}

/* ---------- Texte pour la politique de confidentialité ---------- */

function if_privacy_policy_text() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$text  = '<p class="privacy-policy-tutorial">' . esc_html__( 'Texte proposé par l\'extension Catalogue de formations. Adaptez-le à votre situation.', 'catalogue-formations' ) . '</p>';
	$text .= '<h2>' . esc_html__( 'Formations', 'catalogue-formations' ) . '</h2>';
	$text .= '<p>' . esc_html__( 'Quand vous utilisez les formations du site, nous enregistrons : vos demandes d\'accès aux notions (et le message éventuel), les dates d\'accès accordées, votre avancement (contenus terminés, scores aux activités) et vos réponses aux exercices, avec les fichiers joints et les corrections.', 'catalogue-formations' ) . '</p>';
	$text .= '<p>' . esc_html__( 'Ces données servent uniquement à vous donner accès aux contenus, à suivre votre progression et à corriger vos exercices. Elles sont visibles par vous et par les formateurs du site. Elles sont conservées tant que votre compte existe et supprimées avec lui.', 'catalogue-formations' ) . '</p>';
	$text .= '<p>' . esc_html__( 'Vous pouvez demander à recevoir une copie de ces données ou leur effacement.', 'catalogue-formations' ) . '</p>';
	wp_add_privacy_policy_content( __( 'Catalogue de formations', 'catalogue-formations' ), wp_kses_post( $text ) );
}
