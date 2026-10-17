<?php
/**
 * Création des tables.
 *
 * - if_access      : un accès = un utilisateur + une notion + une période (ou une demande en attente).
 * - if_progress    : éléments terminés (vidéo, PDF) par utilisateur et notion.
 * - if_submissions : réponses aux exercices et corrections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function if_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'if_' . $name;
}

function if_activate() {
	if_create_tables();
	if_register_post_types();
	flush_rewrite_rules();
}

function if_maybe_upgrade_db() {
	if ( get_option( 'if_db_version' ) !== IF_DB_VERSION ) {
		if_create_tables();
	}
}

function if_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$access  = if_table( 'access' );
	$prog    = if_table( 'progress' );
	$subs    = if_table( 'submissions' );

	dbDelta( "CREATE TABLE $access (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		notion_id bigint(20) unsigned NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		date_start date NULL,
		date_end date NULL,
		message text NULL,
		requested_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY notion_id (notion_id),
		KEY status (status)
	) $charset;" );

	dbDelta( "CREATE TABLE $prog (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		notion_id bigint(20) unsigned NOT NULL,
		item_key varchar(60) NOT NULL,
		completed_at datetime NOT NULL,
		score int(11) NULL,
		max_score int(11) NULL,
		passed tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY user_item (user_id,notion_id,item_key)
	) $charset;" );

	dbDelta( "CREATE TABLE $subs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		notion_id bigint(20) unsigned NOT NULL,
		exercise_key varchar(60) NOT NULL,
		answer longtext NULL,
		file_id bigint(20) unsigned NULL,
		status varchar(20) NOT NULL DEFAULT 'submitted',
		feedback longtext NULL,
		submitted_at datetime NOT NULL,
		corrected_at datetime NULL,
		PRIMARY KEY  (id),
		KEY user_notion (user_id,notion_id),
		KEY status (status)
	) $charset;" );

	update_option( 'if_db_version', IF_DB_VERSION );
}
