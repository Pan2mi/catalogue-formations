<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'if_register_post_types' );

function if_register_post_types() {
	register_post_type(
		'if_notion',
		array(
			'labels'        => array(
				'name'          => __( 'Notions', 'catalogue-formations' ),
				'singular_name' => __( 'Notion', 'catalogue-formations' ),
				'add_new'       => __( 'Ajouter une notion', 'catalogue-formations' ),
				'add_new_item'  => __( 'Ajouter une notion', 'catalogue-formations' ),
				'edit_item'     => __( 'Modifier la notion', 'catalogue-formations' ),
				'all_items'     => __( 'Toutes les notions', 'catalogue-formations' ),
				'menu_name'     => __( 'Formation', 'catalogue-formations' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'rewrite'       => array( 'slug' => 'notions' ),
			'menu_icon'     => 'dashicons-welcome-learn-more',
			'menu_position' => 25,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'show_in_rest'  => true,
		)
	);

	register_taxonomy(
		'if_niveau',
		'if_notion',
		array(
			'labels'            => array(
				'name'          => __( 'Niveaux', 'catalogue-formations' ),
				'singular_name' => __( 'Niveau', 'catalogue-formations' ),
				'add_new_item'  => __( 'Ajouter un niveau', 'catalogue-formations' ),
				'name_field_description'   => __( 'Le nom tel qu\'il apparaîtra dans le catalogue.', 'catalogue-formations' ),
				'slug_field_description'   => __( 'Facultatif : laissé vide, il est rempli automatiquement.', 'catalogue-formations' ),
				'desc_field_description'   => __( 'Facultatif.', 'catalogue-formations' ),
			),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'niveau' ),
		)
	);

	register_taxonomy(
		'if_matiere',
		'if_notion',
		array(
			'labels'            => array(
				'name'          => __( 'Matières', 'catalogue-formations' ),
				'singular_name' => __( 'Matière', 'catalogue-formations' ),
				'add_new_item'  => __( 'Ajouter une matière', 'catalogue-formations' ),
				'name_field_description'   => __( 'Le nom tel qu\'il apparaîtra dans le catalogue.', 'catalogue-formations' ),
				'slug_field_description'   => __( 'Facultatif : laissé vide, il est rempli automatiquement.', 'catalogue-formations' ),
				'desc_field_description'   => __( 'Facultatif.', 'catalogue-formations' ),
			),
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'matiere' ),
		)
	);
}
