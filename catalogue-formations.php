<?php
/**
 * Plugin Name:       Catalogue de formations
 * Description:       Plateforme de formation : catalogue de notions (niveaux / matières), demandes d'accès validées par l'administrateur avec dates, suivi de l'avancement, exercices à corriger et alertes e-mail.
 * Version:           2.1.0
 * Update URI:        https://github.com/Pan2mi/catalogue-formations
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Hope Sphère
 * Text Domain:       catalogue-formations
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IF_VERSION', '2.1.0' );
define( 'IF_DB_VERSION', '2' );
define( 'IF_FILE', __FILE__ );
define( 'IF_DIR', plugin_dir_path( __FILE__ ) );
define( 'IF_URL', plugin_dir_url( __FILE__ ) );

require_once IF_DIR . 'includes/install.php';
require_once IF_DIR . 'includes/helpers.php';
require_once IF_DIR . 'includes/post-types.php';
require_once IF_DIR . 'includes/term-order.php';
require_once IF_DIR . 'includes/presets.php';
require_once IF_DIR . 'includes/groups.php';
require_once IF_DIR . 'includes/inline.php';
require_once IF_DIR . 'includes/h5p.php';
require_once IF_DIR . 'includes/updater.php';
require_once IF_DIR . 'includes/metabox.php';
require_once IF_DIR . 'includes/access.php';
require_once IF_DIR . 'includes/progress.php';
require_once IF_DIR . 'includes/emails.php';
require_once IF_DIR . 'includes/frontend.php';
require_once IF_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, 'if_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', 'if_maybe_upgrade_db' );
