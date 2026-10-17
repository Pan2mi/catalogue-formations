<?php
/**
 * Contenus placés librement dans la page : blocs WordPress et widgets Elementor.
 *
 * À chaque enregistrement, on relit la page et on note la liste des éléments suivis
 * (vidéo, document, exercice) dans la méta _if_items_inline, dans l'ordre de la page.
 * Le reste de l'extension (avancement, PDF protégés, corrections) lit cette liste.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'save_post_if_notion', 'if_sync_inline_items', 30 );
add_action( 'elementor/document/after_save', 'if_sync_inline_from_elementor', 10, 1 );
add_action( 'init', 'if_register_blocks' );
add_filter( 'block_categories_all', 'if_block_category', 10, 2 );
add_action( 'init', 'if_elementor_support', 20 );
add_action( 'elementor/elements/categories_registered', 'if_elementor_category' );
add_action( 'elementor/widgets/register', 'if_elementor_widgets' );
add_action( 'admin_init', 'if_convert_legacy_notions' );

/* ---------- Lecture de la page ---------- */

/** Éléments trouvés dans les blocs (récursif : colonnes, groupes…). */
function if_items_from_blocks( $blocks, &$out ) {
	$map = array(
		'catalogue-formations/video'    => 'video',
		'catalogue-formations/document' => 'pdf',
		'catalogue-formations/exercice' => 'exercise',
		'catalogue-formations/h5p'      => 'h5p',
	);
	foreach ( $blocks as $b ) {
		if ( isset( $map[ $b['blockName'] ] ) && ! empty( $b['attrs']['key'] ) ) {
			$out[] = if_item_from_attrs( $map[ $b['blockName'] ], if_attrs_with_inner( $map[ $b['blockName'] ], $b ) );
			continue; // pas de bloc suivi dans un bloc suivi.
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			if_items_from_blocks( $b['innerBlocks'], $out );
		}
	}
}

/** Complète les attributs d'un bloc suivi avec ce que contiennent ses blocs intérieurs. */
function if_attrs_with_inner( $type, $b ) {
	$a     = $b['attrs'];
	$inner = isset( $b['innerBlocks'] ) ? $b['innerBlocks'] : array();
	if ( ! $inner ) {
		return $a;
	}
	$find = function ( $blocks, $names ) use ( &$find ) {
		foreach ( $blocks as $ib ) {
			if ( in_array( $ib['blockName'], $names, true ) ) {
				return $ib;
			}
			if ( ! empty( $ib['innerBlocks'] ) ) {
				$r = $find( $ib['innerBlocks'], $names );
				if ( $r ) {
					return $r;
				}
			}
		}
		return null;
	};
	if ( 'video' === $type ) {
		$v = $find( $inner, array( 'core/video', 'core/embed' ) );
		if ( $v ) {
			if ( 'core/embed' === $v['blockName'] ) {
				$a['url'] = isset( $v['attrs']['url'] ) ? $v['attrs']['url'] : '';
			} else {
				$a['url'] = ! empty( $v['attrs']['src'] ) ? $v['attrs']['src'] : ( ! empty( $v['attrs']['id'] ) ? wp_get_attachment_url( (int) $v['attrs']['id'] ) : '' );
				if ( ! $a['url'] && preg_match( '/src="([^"]+)"/', (string) $v['innerHTML'], $m ) ) {
					$a['url'] = html_entity_decode( $m[1] );
				}
			}
		}
	} elseif ( 'pdf' === $type ) {
		$f = $find( $inner, array( 'core/file' ) );
		if ( $f && ! empty( $f['attrs']['id'] ) ) {
			$a['attachmentId'] = (int) $f['attrs']['id'];
		}
	} elseif ( 'exercise' === $type ) {
		$html = '';
		foreach ( $inner as $ib ) {
			$html .= render_block( $ib );
		}
		$a['instructions'] = $html;
	}
	return $a;
}

function if_item_from_attrs( $type, $a ) {
	$item = array(
		'key'   => sanitize_key( $a['key'] ),
		'type'  => $type,
		'title' => isset( $a['title'] ) ? sanitize_text_field( $a['title'] ) : '',
	);
	if ( '' === $item['title'] ) {
		$item['title'] = if_type_label( $type );
	}
	if ( 'video' === $type ) {
		$item['url'] = isset( $a['url'] ) ? esc_url_raw( $a['url'] ) : '';
	} elseif ( 'pdf' === $type ) {
		$item['attachment_id'] = isset( $a['attachmentId'] ) ? absint( $a['attachmentId'] ) : 0;
	} elseif ( 'h5p' === $type ) {
		$item['content_id'] = isset( $a['contentId'] ) ? absint( $a['contentId'] ) : 0;
		$item['pass']       = isset( $a['passScore'] ) ? min( 100, max( 0, (int) $a['passScore'] ) ) : 0;
	} else {
		$item['instructions'] = isset( $a['instructions'] ) ? wp_kses_post( $a['instructions'] ) : '';
	}
	return $item;
}

/** Éléments trouvés dans les données Elementor. */
function if_items_from_elementor( $elements, &$out ) {
	$map = array(
		'cf_video'    => 'video',
		'cf_document' => 'pdf',
		'cf_exercice' => 'exercise',
		'cf_h5p'      => 'h5p',
	);
	foreach ( (array) $elements as $el ) {
		if ( ! is_array( $el ) ) {
			continue;
		}
		if ( isset( $el['elType'], $el['widgetType'] ) && 'widget' === $el['elType'] && isset( $map[ $el['widgetType'] ] ) ) {
			$out[] = if_item_from_elementor_settings( $map[ $el['widgetType'] ], $el['id'], isset( $el['settings'] ) ? $el['settings'] : array() );
		}
		if ( ! empty( $el['elements'] ) ) {
			if_items_from_elementor( $el['elements'], $out );
		}
	}
}

function if_item_from_elementor_settings( $type, $id, $s ) {
	$attrs = array(
		'key'   => 'el_' . $id,
		'title' => isset( $s['title'] ) ? $s['title'] : '',
	);
	if ( 'video' === $type ) {
		$source       = isset( $s['source'] ) ? $s['source'] : 'url';
		$attrs['url'] = 'media' === $source ? ( isset( $s['video_file']['url'] ) ? $s['video_file']['url'] : '' ) : ( isset( $s['video_url'] ) ? $s['video_url'] : '' );
	} elseif ( 'pdf' === $type ) {
		$attrs['attachmentId'] = isset( $s['file']['id'] ) ? $s['file']['id'] : 0;
	} elseif ( 'h5p' === $type ) {
		$attrs['contentId'] = isset( $s['content_id'] ) ? $s['content_id'] : 0;
		$attrs['passScore'] = isset( $s['pass'] ) ? $s['pass'] : 0;
	} else {
		$attrs['instructions'] = isset( $s['instructions'] ) ? $s['instructions'] : '';
	}
	return if_item_from_attrs( $type, $attrs );
}

/** Relit la notion et met à jour la liste des éléments suivis. */
function if_sync_inline_items( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || 'if_notion' !== get_post_type( $post_id ) ) {
		return;
	}
	$items = array();
	$found = false;

	if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
		$data = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $data ) ? json_decode( $data, true ) : $data;
		if ( is_array( $data ) ) {
			if_items_from_elementor( $data, $items );
			$found = (bool) $items;
		}
	} else {
		$content = get_post_field( 'post_content', $post_id );
		if ( has_blocks( $content ) ) {
			if_items_from_blocks( parse_blocks( $content ), $items );
			$found = (bool) $items;
		}
	}

	if ( $found ) {
		// Clés uniques (un élément copié-collé garde sinon la même clé).
		$seen = array();
		foreach ( $items as &$it ) {
			while ( isset( $seen[ $it['key'] ] ) ) {
				$it['key'] .= 'x';
			}
			$seen[ $it['key'] ] = true;
		}
		unset( $it );
		update_post_meta( $post_id, '_if_items_inline', $items );
	} else {
		delete_post_meta( $post_id, '_if_items_inline' );
	}
}

function if_sync_inline_from_elementor( $document ) {
	if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
		if_sync_inline_items( $document->get_main_id() );
	}
}

/** Notion en cours (page, aperçu de l'éditeur, rendu Elementor). */
function if_current_notion_id() {
	$id = get_the_ID();
	if ( ! $id && isset( $_GET['post_id'] ) ) { // phpcs:ignore -- aperçu de l'éditeur de blocs.
		$id = absint( $_GET['post_id'] ); // phpcs:ignore
	}
	return (int) $id;
}

/* ---------- Blocs WordPress ---------- */

function if_block_category( $cats, $context ) {
	array_unshift( $cats, array( 'slug' => 'catalogue-formations', 'title' => __( 'Formation', 'catalogue-formations' ), 'icon' => null ) );
	return $cats;
}

function if_register_blocks() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	wp_register_script(
		'cf-blocks',
		IF_URL . 'assets/blocks.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-data', 'wp-editor', 'wp-plugins', 'wp-hooks', 'wp-compose', 'wp-api-fetch' ),
		IF_VERSION,
		true
	);
	wp_register_style( 'cf-blocks-editor', IF_URL . 'assets/blocks-editor.css', array(), IF_VERSION );

	$supports = array(
		'html'                 => false,
		'anchor'               => true,
		'align'                => array( 'left', 'center', 'right', 'wide', 'full' ),
		'spacing'              => array( 'margin' => true, 'padding' => true ),
		'color'                => array( 'background' => true, 'text' => true, 'gradients' => true, 'link' => true ),
		'typography'           => array( 'fontSize' => true, 'lineHeight' => true ),
		'__experimentalBorder' => array( 'color' => true, 'radius' => true, 'style' => true, 'width' => true ),
		'shadow'               => true,
	);
	$common   = array(
		'api_version'   => 3,
		'editor_script' => 'cf-blocks',
		'editor_style'  => 'cf-blocks-editor',
		'style'         => 'if-front',
		'category'      => 'catalogue-formations',
		'supports'      => $supports,
	);
	$base_attrs = array(
		'key'       => array( 'type' => 'string', 'default' => '' ),
		'title'     => array( 'type' => 'string', 'default' => '' ),
		'showTitle' => array( 'type' => 'boolean', 'default' => true ),
	);
	$render = function ( $type ) {
		return function ( $a, $content, $block ) use ( $type ) {
			$item               = if_item_from_attrs( $type, if_attrs_with_inner( $type, $block->parsed_block ) );
			$item['key']        = isset( $a['key'] ) ? $a['key'] : '';
			$item['show_title'] = ! isset( $a['showTitle'] ) || $a['showTitle'];
			if ( isset( $a['title'] ) && '' !== $a['title'] ) {
				$item['title'] = $a['title'];
			}
			$wrapper = get_block_wrapper_attributes( array( 'class' => 'if-item if-item--' . $type, 'id' => $item['key'] ) );
			return if_render_item( if_current_notion_id(), $type, $item, $content, $wrapper );
		};
	};
	register_block_type(
		'catalogue-formations/video',
		$common + array(
			'attributes'      => $base_attrs + array( 'url' => array( 'type' => 'string', 'default' => '' ) ),
			'render_callback' => $render( 'video' ),
		)
	);
	register_block_type(
		'catalogue-formations/document',
		$common + array(
			'attributes'      => $base_attrs + array(
				'attachmentId' => array( 'type' => 'number', 'default' => 0 ),
				'fileName'     => array( 'type' => 'string', 'default' => '' ),
			),
			'render_callback' => $render( 'pdf' ),
		)
	);
	register_block_type(
		'catalogue-formations/exercice',
		$common + array(
			'attributes'      => $base_attrs + array( 'instructions' => array( 'type' => 'string', 'default' => '' ) ),
			'render_callback' => $render( 'exercise' ),
		)
	);
	register_block_type(
		'catalogue-formations/h5p',
		$common + array(
			'attributes'      => $base_attrs + array(
				'contentId' => array( 'type' => 'number', 'default' => 0 ),
				'passScore' => array( 'type' => 'number', 'default' => 0 ),
			),
			'render_callback' => $render( 'h5p' ),
		)
	);
	// Le style front est aussi utile dans l'éditeur (aperçu fidèle).
	if ( ! wp_style_is( 'if-front', 'registered' ) ) {
		wp_register_style( 'if-front', IF_URL . 'assets/front.css', array(), IF_VERSION );
	}
}

/* ---------- Elementor ---------- */

/** Permet d'ouvrir les notions avec Elementor. */
function if_elementor_support() {
	add_post_type_support( 'if_notion', 'elementor' );
}

function if_elementor_category( $manager ) {
	$manager->add_category( 'catalogue-formations', array( 'title' => __( 'Formation', 'catalogue-formations' ), 'icon' => 'eicon-graduation-cap' ) );
	if_elementor_category_first( $manager );
}

function if_elementor_widgets( $widgets_manager ) {
	require_once IF_DIR . 'includes/elementor-widgets.php';
	$widgets_manager->register( new \CF_Elementor_Video() );
	$widgets_manager->register( new \CF_Elementor_Document() );
	$widgets_manager->register( new \CF_Elementor_Exercice() );
	if ( if_h5p_active() ) {
		$widgets_manager->register( new \CF_Elementor_H5P() );
	}
}

/** Place la catégorie « Formation » en tête du panneau Elementor. */
function if_elementor_category_first( $manager ) {
	try {
		$ref = new \ReflectionProperty( $manager, 'categories' );
		$ref->setAccessible( true );
		$cats = $ref->getValue( $manager );
		if ( is_array( $cats ) && isset( $cats['catalogue-formations'] ) ) {
			$ref->setValue( $manager, array( 'catalogue-formations' => $cats['catalogue-formations'] ) + $cats );
		}
	} catch ( \Throwable $e ) { // phpcs:ignore
		// Sans gravité : la catégorie reste à sa place par défaut.
	}
}

/* ---------- Conversion des anciennes notions (boîte) en blocs ---------- */

/**
 * Une seule fois : les vidéos / PDF / exercices de l'ancienne boîte deviennent des blocs
 * ajoutés à la fin de la page. Les clés sont conservées, donc l'avancement aussi.
 */
function if_convert_legacy_notions() {
	if ( get_option( 'if_legacy_converted' ) || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$ids = get_posts( array( 'post_type' => 'if_notion', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		if_convert_legacy_notion( $id );
	}
	update_option( 'if_legacy_converted', 1 );
}

function if_convert_legacy_notion( $id ) {
	if ( if_uses_inline( $id ) || 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) ) {
		return false; // Elementor : l'ancien affichage reste en place.
	}
	$blocks = '';
	$add    = function ( $name, $attrs ) use ( &$blocks ) {
		$blocks .= "\n\n" . get_comment_delimited_block_content( $name, $attrs, '' );
	};
	$v = get_post_meta( $id, '_if_videos', true );
	$p = get_post_meta( $id, '_if_pdfs', true );
	$e = get_post_meta( $id, '_if_exercises', true );
	foreach ( is_array( $v ) ? $v : array() as $it ) {
		$add( 'catalogue-formations/video', array( 'key' => $it['key'], 'title' => $it['title'], 'url' => $it['url'] ) );
	}
	foreach ( is_array( $p ) ? $p : array() as $it ) {
		$add( 'catalogue-formations/document', array( 'key' => $it['key'], 'title' => $it['title'], 'attachmentId' => (int) $it['attachment_id'], 'fileName' => basename( (string) get_attached_file( (int) $it['attachment_id'] ) ) ) );
	}
	foreach ( is_array( $e ) ? $e : array() as $it ) {
		$add( 'catalogue-formations/exercice', array( 'key' => $it['key'], 'title' => $it['title'], 'instructions' => $it['instructions'] ) );
	}
	if ( '' === $blocks ) {
		return false;
	}
	$content = (string) get_post_field( 'post_content', $id );
	if ( '' !== trim( $content ) && ! has_blocks( $content ) ) {
		$content = get_comment_delimited_block_content( 'core/freeform', array(), $content ); // ancien texte gardé tel quel.
	}
	wp_update_post( array( 'ID' => $id, 'post_content' => $content . $blocks ) );
	if_sync_inline_items( $id );
	return true;
}

/** Style de l'encadré « Ajouter un contenu suivi » (hors zone d'édition). */
add_action( 'enqueue_block_editor_assets', 'if_editor_ui_style' );
function if_editor_ui_style() {
	wp_enqueue_style( 'cf-editor-ui', IF_URL . 'assets/editor-ui.css', array(), IF_VERSION );
}
