<?php
/**
 * Alertes e-mail.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'if_access_requested', 'if_mail_request' );
add_action( 'if_access_granted', 'if_mail_granted' );
add_action( 'if_access_refused', 'if_mail_refused' );
add_action( 'if_exercise_submitted', 'if_mail_submitted' );
add_action( 'if_exercise_corrected', 'if_mail_corrected' );

function if_mail( $to, $subject, $body ) {
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	wp_mail( $to, '[' . $site . '] ' . $subject, $body );
}

function if_mail_request( $access_id ) {
	$a = if_get_access( $access_id );
	if ( ! $a ) {
		return;
	}
	$u    = get_userdata( $a->user_id );
	$body = sprintf(
		"Nouvelle demande d'accès.\n\nApprenant : %s (%s)\nNotion : %s\n%s\nPour valider et choisir les dates :\n%s",
		$u ? $u->display_name : '?',
		$u ? $u->user_email : '?',
		get_the_title( $a->notion_id ),
		$a->message ? "Message : {$a->message}\n" : '',
		if_admin_url( 'if-access' )
	);
	if_mail( if_admin_emails(), 'Nouvelle demande : ' . get_the_title( $a->notion_id ), $body );
}

function if_mail_granted( $access_id ) {
	$a = if_get_access( $access_id );
	$u = $a ? get_userdata( $a->user_id ) : null;
	if ( ! $u ) {
		return;
	}
	$body = sprintf(
		"Bonjour %s,\n\nVotre accès à la notion « %s » est accordé.\nDu %s au %s.\n\nAccéder à la notion : %s\nMon tableau de bord : %s",
		$u->display_name,
		get_the_title( $a->notion_id ),
		if_date( $a->date_start ),
		if_date( $a->date_end ),
		get_permalink( $a->notion_id ),
		if_dashboard_url()
	);
	if_mail( $u->user_email, 'Accès accordé : ' . get_the_title( $a->notion_id ), $body );
}

function if_mail_refused( $access_id ) {
	$a = if_get_access( $access_id );
	$u = $a ? get_userdata( $a->user_id ) : null;
	if ( ! $u ) {
		return;
	}
	$body = sprintf(
		"Bonjour %s,\n\nVotre demande d'accès à la notion « %s » n'a pas été acceptée pour le moment.\nN'hésitez pas à nous contacter pour en savoir plus.",
		$u->display_name,
		get_the_title( $a->notion_id )
	);
	if_mail( $u->user_email, 'Demande d\'accès : ' . get_the_title( $a->notion_id ), $body );
}

function if_mail_submitted( $sub_id ) {
	$s = if_get_submission( $sub_id );
	if ( ! $s ) {
		return;
	}
	$u  = get_userdata( $s->user_id );
	$ex = if_find_item( $s->notion_id, $s->exercise_key, 'exercise' );
	$body = sprintf(
		"Un exercice est à corriger.\n\nApprenant : %s\nNotion : %s\nExercice : %s\n\nCorriger : %s",
		$u ? $u->display_name : '?',
		get_the_title( $s->notion_id ),
		$ex ? $ex['title'] : $s->exercise_key,
		if_admin_url( 'if-corrections', array( 'submission' => $sub_id ) )
	);
	if_mail( if_admin_emails(), 'Exercice à corriger — ' . ( $u ? $u->display_name : '' ), $body );
}

function if_mail_corrected( $sub_id ) {
	$s = if_get_submission( $sub_id );
	$u = $s ? get_userdata( $s->user_id ) : null;
	if ( ! $u ) {
		return;
	}
	$ex   = if_find_item( $s->notion_id, $s->exercise_key, 'exercise' );
	$body = sprintf(
		"Bonjour %s,\n\nVotre exercice « %s » (notion « %s ») a été corrigé.\n\nVoir la correction : %s",
		$u->display_name,
		$ex ? $ex['title'] : '',
		get_the_title( $s->notion_id ),
		get_permalink( $s->notion_id ) . '#' . $s->exercise_key
	);
	if_mail( $u->user_email, 'Exercice corrigé', $body );
}
