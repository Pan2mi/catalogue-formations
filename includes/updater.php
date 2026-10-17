<?php
/**
 * Mises à jour depuis GitHub.
 *
 * Quand une nouvelle « release » est publiée sur GitHub (ex. v2.1.0) avec le fichier
 * catalogue-formations.zip, WordPress affiche « Mise à jour disponible » dans Extensions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'IF_GITHUB_REPO' ) ) {
	define( 'IF_GITHUB_REPO', 'Pan2mi/catalogue-formations' );
}

add_filter( 'pre_set_site_transient_update_plugins', 'if_updater_check' );
add_filter( 'plugins_api', 'if_updater_info', 10, 3 );
add_filter( 'upgrader_source_selection', 'if_updater_fix_folder', 10, 4 );
add_action( 'upgrader_process_complete', 'if_updater_clear', 10, 0 );

function if_updater_basename() {
	return plugin_basename( IF_FILE );
}

/** Dernière release GitHub (mise en cache 6 h). */
function if_updater_release( $force = false ) {
	$cache = get_site_transient( 'if_gh_release' );
	if ( ! $force && false !== $cache ) {
		return $cache ? $cache : null;
	}
	$res = wp_remote_get(
		'https://api.github.com/repos/' . IF_GITHUB_REPO . '/releases/latest',
		array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'catalogue-formations' ) )
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		set_site_transient( 'if_gh_release', 0, HOUR_IN_SECONDS );
		return null;
	}
	$r       = json_decode( wp_remote_retrieve_body( $res ), true );
	$package = '';
	foreach ( isset( $r['assets'] ) ? $r['assets'] : array() as $a ) {
		if ( 'catalogue-formations.zip' === $a['name'] ) {
			$package = $a['browser_download_url'];
		}
	}
	if ( ! $package && ! empty( $r['zipball_url'] ) ) {
		$package = $r['zipball_url'];
	}
	$data = array(
		'version' => ltrim( isset( $r['tag_name'] ) ? $r['tag_name'] : '', 'vV' ),
		'package' => $package,
		'notes'   => isset( $r['body'] ) ? $r['body'] : '',
		'url'     => isset( $r['html_url'] ) ? $r['html_url'] : 'https://github.com/' . IF_GITHUB_REPO,
		'date'    => isset( $r['published_at'] ) ? $r['published_at'] : '',
	);
	set_site_transient( 'if_gh_release', $data, 6 * HOUR_IN_SECONDS );
	return $data;
}

function if_updater_check( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}
	$rel = if_updater_release();
	if ( $rel && $rel['version'] && $rel['package'] && version_compare( $rel['version'], IF_VERSION, '>' ) ) {
		$transient->response[ if_updater_basename() ] = (object) array(
			'slug'        => 'catalogue-formations',
			'plugin'      => if_updater_basename(),
			'new_version' => $rel['version'],
			'package'     => $rel['package'],
			'url'         => $rel['url'],
		);
	}
	return $transient;
}

/** Fenêtre « Voir les détails ». */
function if_updater_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'catalogue-formations' !== $args->slug ) {
		return $result;
	}
	$rel = if_updater_release();
	if ( ! $rel ) {
		return $result;
	}
	return (object) array(
		'name'          => 'Catalogue de formations',
		'slug'          => 'catalogue-formations',
		'version'       => $rel['version'],
		'author'        => 'Hope Sphère',
		'homepage'      => 'https://github.com/' . IF_GITHUB_REPO,
		'download_link' => $rel['package'],
		'last_updated'  => $rel['date'],
		'sections'      => array( 'changelog' => wpautop( esc_html( $rel['notes'] ) ) ),
	);
}

/** Si GitHub fournit une archive au nom du dépôt, on renomme le dossier. */
function if_updater_fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	if ( empty( $hook_extra['plugin'] ) || if_updater_basename() !== $hook_extra['plugin'] ) {
		return $source;
	}
	$wanted = trailingslashit( $remote_source ) . 'catalogue-formations/';
	if ( trailingslashit( $source ) === $wanted ) {
		return $source;
	}
	global $wp_filesystem;
	if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
		return $wanted;
	}
	return $source;
}

function if_updater_clear() {
	delete_site_transient( 'if_gh_release' );
}
