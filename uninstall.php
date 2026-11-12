<?php
/**
 * Désinstallation (suppression de l'extension depuis Extensions).
 * Rien n'est supprimé, sauf si c'est demandé dans Formation → Réglages → « À la suppression de l'extension » :
 * - 'data' : données des apprenants (accès, avancement, réponses + fichiers) et réglages ;
 * - 'all'  : la même chose + notions, niveaux, matières et groupes.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cf_mode = get_option( 'if_uninstall', 'keep' );
if ( ! in_array( $cf_mode, array( 'data', 'all' ), true ) ) {
	return;
}

global $wpdb;

// Fichiers joints aux réponses.
$cf_subs = $wpdb->prefix . 'if_submissions';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cf_subs ) ) === $cf_subs ) { // phpcs:ignore
	foreach ( $wpdb->get_col( "SELECT DISTINCT file_id FROM $cf_subs WHERE file_id IS NOT NULL AND file_id > 0" ) as $cf_file ) { // phpcs:ignore
		wp_delete_attachment( (int) $cf_file, true );
	}
}
$cf_up  = wp_upload_dir( null, false );
$cf_dir = trailingslashit( $cf_up['basedir'] ) . 'catalogue-formations-reponses';
if ( is_dir( $cf_dir ) ) {
	foreach ( (array) glob( $cf_dir . '/{,.}*', GLOB_BRACE ) as $cf_f ) {
		if ( is_file( $cf_f ) ) {
			wp_delete_file( $cf_f );
		}
	}
	@rmdir( $cf_dir ); // phpcs:ignore
}

// Tableaux de données.
foreach ( array( 'if_access', 'if_progress', 'if_submissions' ) as $cf_t ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$cf_t}" ); // phpcs:ignore
}

// Réglages.
foreach ( array( 'if_alert_email', 'if_answers_migrated', 'if_catalogue_page', 'if_dashboard_page', 'if_db_version', 'if_filters', 'if_groups_migrated', 'if_legacy_converted', 'if_sections', 'if_uninstall' ) as $cf_opt ) {
	delete_option( $cf_opt );
}

if ( 'all' === $cf_mode ) {
	// Notions (et leurs révisions / métadonnées).
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'if_notion'" ) as $cf_id ) { // phpcs:ignore
		wp_delete_post( (int) $cf_id, true );
	}
	// Niveaux, matières, groupes.
	foreach ( array( 'if_niveau', 'if_matiere', 'if_groupe_niveau', 'if_groupe_matiere' ) as $cf_tax ) {
		$cf_terms = $wpdb->get_col( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s", $cf_tax ) ); // phpcs:ignore
		foreach ( $cf_terms as $cf_term ) {
			$wpdb->delete( $wpdb->termmeta, array( 'term_id' => (int) $cf_term ) );
			$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s", $cf_term, $cf_tax ) ) ) ); // phpcs:ignore
			$wpdb->delete( $wpdb->term_taxonomy, array( 'term_id' => (int) $cf_term, 'taxonomy' => $cf_tax ) );
			$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $cf_term ) );
		}
	}
}
