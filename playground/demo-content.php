<?php
/**
 * Contenus de démonstration pour WordPress Playground.
 * Exécuté une fois par le blueprint (playground/blueprint.json).
 */
if ( empty( $cf_demo ) ) { // ne s'exécute que depuis le blueprint Playground.
	exit;
}
require_once '/wordpress/wp-load.php';

if ( ! function_exists( 'if_grant_access' ) ) {
	return;
}
wp_set_current_user( 1 );

/* Niveaux (avec leurs groupes) et matières */
$niv = array();
foreach ( array( 'Primaire', 'Collège' ) as $section ) {
	foreach ( if_presets( 'if_niveau' )[ $section ] as $name => $groups ) {
		$t            = wp_insert_term( $name, 'if_niveau' );
		$niv[ $name ] = is_wp_error( $t ) ? (int) $t->get_error_data() : (int) $t['term_id'];
		foreach ( $groups as $g ) {
			if_add_group( $niv[ $name ], if_get_or_create_group( $g, 'if_groupe_niveau' ) );
		}
	}
}
$mat = array();
foreach ( array( 'Français' => array(), 'Mathématiques' => array( 'Sciences' ), 'Anglais' => array( 'Langues' ) ) as $name => $groups ) {
	$t            = wp_insert_term( $name, 'if_matiere' );
	$mat[ $name ] = is_wp_error( $t ) ? (int) $t->get_error_data() : (int) $t['term_id'];
	foreach ( $groups as $g ) {
		if_add_group( $mat[ $name ], if_get_or_create_group( $g, 'if_groupe_matiere' ) );
	}
}

/* Un petit PDF de démonstration */
$up   = wp_upload_dir();
$path = $up['path'] . '/fiche-fractions.pdf';
$text = 'Fiche resume : une fraction = une partie d un tout. 1/4 = 1 part sur 4.';
$pdf  = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n";
$stream = "BT /F1 16 Tf 60 760 Td ($text) Tj ET";
$pdf   .= '4 0 obj<</Length ' . strlen( $stream ) . ">>stream\n$stream\nendstream endobj\n5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
file_put_contents( $path, $pdf );
$att = wp_insert_attachment( array( 'post_mime_type' => 'application/pdf', 'post_title' => 'Fiche résumé – fractions', 'post_status' => 'inherit' ), $path );

/* Notions (contenus suivis placés dans la page) */
$b = function ( $name, $attrs, $inner = '' ) {
	return get_comment_delimited_block_content( $name, $attrs, $inner );
};
$p = function ( $text ) use ( $b ) {
	return $b( 'core/paragraph', array(), '<p>' . $text . '</p>' );
};

$fractions = wp_insert_post(
	array(
		'post_type'    => 'if_notion',
		'post_status'  => 'publish',
		'post_title'   => 'Les fractions simples',
		'post_excerpt' => 'Comprendre ce qu\'est une fraction et savoir lire 1/2, 1/3, 1/4.',
		'post_content' => implode(
			"\n\n",
			array(
				$p( 'Dans cette notion, tu vas découvrir ce que représente une fraction à l\'aide d\'exemples du quotidien : une pizza, une tablette de chocolat…' ),
				$b( 'catalogue-formations/document', array( 'key' => 'p_demo_fiche', 'title' => 'Fiche résumé', 'attachmentId' => (int) $att, 'fileName' => 'fiche-fractions.pdf' ) ),
				$b( 'core/heading', array(), '<h2 class="wp-block-heading">À toi de jouer</h2>' ),
				$b( 'catalogue-formations/exercice', array( 'key' => 'e_demo_pizza', 'title' => 'Partager une pizza', 'instructions' => '<p>Une pizza est coupée en 4 parts égales. Tu en manges 1. Quelle fraction de la pizza as-tu mangée ? Explique avec tes mots.</p>' ) ),
			)
		),
	)
);
wp_set_object_terms( $fractions, $niv['CM1'], 'if_niveau' );
wp_set_object_terms( $fractions, $mat['Mathématiques'], 'if_matiere' );

$anglais = wp_insert_post(
	array(
		'post_type'    => 'if_notion',
		'post_status'  => 'publish',
		'post_title'   => 'Se présenter en anglais',
		'post_excerpt' => 'Dire son prénom, son âge et ce que l\'on aime.',
		'post_content' => implode(
			"\n\n",
			array(
				$p( 'Hello! Dans cette notion, tu apprends à te présenter : My name is…, I am … years old, I like…' ),
				$b( 'catalogue-formations/exercice', array( 'key' => 'e_demo_hello', 'title' => 'Ma présentation', 'instructions' => '<p>Écris 3 phrases en anglais pour te présenter.</p>' ) ),
			)
		),
	)
);
wp_set_object_terms( $anglais, array( $niv['6ème'], $niv['5ème'] ), 'if_niveau' );
wp_set_object_terms( $anglais, $mat['Anglais'], 'if_matiere' );

/* Pages Catalogue et Tableau de bord */
$cat  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Catalogue', 'post_content' => $b( 'core/shortcode', array(), '[if_catalogue]' ) ) );
$dash = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Mon tableau de bord', 'post_content' => $b( 'core/shortcode', array(), '[if_tableau_de_bord]' ) ) );
update_option( 'if_catalogue_page', $cat );
update_option( 'if_dashboard_page', $dash );

/* Comptes de démonstration */
$apprenant = wp_insert_user( array( 'user_login' => 'apprenant', 'user_pass' => 'apprenant', 'display_name' => 'Léa (apprenante)', 'user_email' => 'apprenant@example.com', 'role' => 'subscriber' ) );
$tom       = wp_insert_user( array( 'user_login' => 'tom', 'user_pass' => 'tom', 'display_name' => 'Tom (apprenant)', 'user_email' => 'tom@example.com', 'role' => 'subscriber' ) );
if ( ! is_wp_error( $apprenant ) ) {
	if_grant_access( $apprenant, $fractions, current_time( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+1 year' ) ) );
}
if ( ! is_wp_error( $tom ) ) {
	if_request_access( $tom, $anglais, 'Je suis disponible le mercredi.' );
}

/* Menu simple avec les deux pages */
$menu = wp_create_nav_menu( 'Formation' );
if ( ! is_wp_error( $menu ) ) {
	wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Catalogue', 'menu-item-object' => 'page', 'menu-item-object-id' => $cat, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Mon tableau de bord', 'menu-item-object' => 'page', 'menu-item-object-id' => $dash, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
}
flush_rewrite_rules();
