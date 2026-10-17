<?php
/**
 * Widgets Elementor : Vidéo suivie, Document suivi, Exercice.
 * Chargé seulement quand Elementor est actif.
 * Onglet « Avancé » d'Elementor (marges, fond, bordure, animation, responsive, classes CSS) : automatique.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

abstract class CF_Elementor_Base extends Widget_Base {
	public function get_categories() {
		return array( 'catalogue-formations' );
	}

	public function get_keywords() {
		return array( 'formation', 'notion', 'catalogue', 'suivi' );
	}

	/** Clé stable : l'identifiant Elementor de l'élément (change à la duplication). */
	protected function item_key() {
		return 'el_' . $this->get_id();
	}

	protected function title_controls() {
		$this->add_control(
			'title',
			array(
				'label'       => __( 'Titre (aussi utilisé dans le suivi)', 'catalogue-formations' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);
		$this->add_control(
			'show_title',
			array(
				'label'        => __( 'Afficher le titre', 'catalogue-formations' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);
	}

	/** Onglet « Style » commun : alignement, titre, bouton. */
	protected function style_controls() {
		$this->start_controls_section( 'style_box', array( 'label' => __( 'Disposition', 'catalogue-formations' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignement', 'catalogue-formations' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array( 'title' => __( 'Gauche', 'catalogue-formations' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => __( 'Centre', 'catalogue-formations' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => __( 'Droite', 'catalogue-formations' ), 'icon' => 'eicon-text-align-right' ),
				),
				'selectors' => array( '{{WRAPPER}} .if-item' => 'text-align: {{VALUE}};' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'style_title', array( 'label' => __( 'Titre', 'catalogue-formations' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'title_color', array( 'label' => __( 'Couleur', 'catalogue-formations' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .if-item__title' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'title_typo', 'selector' => '{{WRAPPER}} .if-item__title' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_button', array( 'label' => __( 'Boutons', 'catalogue-formations' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'btn_bg', array( 'label' => __( 'Fond', 'catalogue-formations' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .if-btn:not(.if-btn--ghost)' => 'background-color: {{VALUE}}; border-color: {{VALUE}};', '{{WRAPPER}} .if-btn--ghost' => 'color: {{VALUE}} !important; border-color: {{VALUE}};' ) ) );
		$this->add_control( 'btn_color', array( 'label' => __( 'Texte', 'catalogue-formations' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .if-btn:not(.if-btn--ghost)' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'btn_radius', array( 'label' => __( 'Arrondi', 'catalogue-formations' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .if-btn' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'btn_typo', 'selector' => '{{WRAPPER}} .if-btn' ) );
		$this->end_controls_section();
	}

	protected function base_item( $s ) {
		return array(
			'key'        => $this->item_key(),
			'title'      => isset( $s['title'] ) ? $s['title'] : '',
			'show_title' => ! isset( $s['show_title'] ) || 'yes' === $s['show_title'],
		);
	}
}

class CF_Elementor_Video extends CF_Elementor_Base {
	public function get_name() {
		return 'cf_video';
	}
	public function get_title() {
		return __( 'Vidéo suivie', 'catalogue-formations' );
	}
	public function get_icon() {
		return 'eicon-youtube';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Vidéo', 'catalogue-formations' ) ) );
		$this->title_controls();
		$this->add_control(
			'source',
			array(
				'label'   => __( 'Source', 'catalogue-formations' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'url',
				'options' => array(
					'url'   => __( 'Lien YouTube / Vimeo', 'catalogue-formations' ),
					'media' => __( 'Fichier de la médiathèque', 'catalogue-formations' ),
				),
			)
		);
		$this->add_control( 'video_url', array( 'label' => __( 'Lien de la vidéo', 'catalogue-formations' ), 'type' => Controls_Manager::TEXT, 'placeholder' => 'https://www.youtube.com/watch?v=…', 'label_block' => true, 'condition' => array( 'source' => 'url' ) ) );
		$this->add_control( 'video_file', array( 'label' => __( 'Fichier vidéo', 'catalogue-formations' ), 'type' => Controls_Manager::MEDIA, 'media_types' => array( 'video' ), 'condition' => array( 'source' => 'media' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'options', array( 'label' => __( 'Options de lecture', 'catalogue-formations' ) ) );
		foreach ( array( 'autoplay' => __( 'Lecture automatique', 'catalogue-formations' ), 'mute' => __( 'Son coupé', 'catalogue-formations' ), 'loop' => __( 'En boucle', 'catalogue-formations' ) ) as $k => $l ) {
			$this->add_control( $k, array( 'label' => $l, 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes' ) );
		}
		$this->add_control( 'controls', array( 'label' => __( 'Commandes du lecteur', 'catalogue-formations' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes', 'condition' => array( 'source' => 'media' ) ) );
		$this->add_control( 'start', array( 'label' => __( 'Début (secondes)', 'catalogue-formations' ), 'type' => Controls_Manager::NUMBER, 'min' => 0 ) );
		$this->add_control( 'poster', array( 'label' => __( 'Image d\'aperçu', 'catalogue-formations' ), 'type' => Controls_Manager::MEDIA, 'condition' => array( 'source' => 'media' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_video', array( 'label' => __( 'Vidéo', 'catalogue-formations' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control(
			'ratio',
			array(
				'label'     => __( 'Format', 'catalogue-formations' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '16 / 9',
				'options'   => array( '16 / 9' => '16:9', '4 / 3' => '4:3', '1 / 1' => '1:1', '9 / 16' => '9:16 (vertical)', '21 / 9' => '21:9' ),
				'selectors' => array( '{{WRAPPER}} .cf-video-frame' => 'aspect-ratio: {{VALUE}};' ),
			)
		);
		$this->add_responsive_control(
			'max_width',
			array(
				'label'      => __( 'Largeur maximale', 'catalogue-formations' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( '%', 'px' ),
				'range'      => array( '%' => array( 'min' => 10, 'max' => 100 ), 'px' => array( 'min' => 100, 'max' => 1600 ) ),
				'selectors'  => array( '{{WRAPPER}} .cf-video-frame' => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control( 'video_radius', array( 'label' => __( 'Arrondi', 'catalogue-formations' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px' ), 'selectors' => array( '{{WRAPPER}} .cf-video-frame' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;' ) ) );
		$this->end_controls_section();

		$this->style_controls();
	}

	/** Lecteur avec les options choisies (fichier : balise vidéo ; YouTube / Vimeo : lecteur intégré). */
	protected function player( $s ) {
		$is_media = isset( $s['source'] ) && 'media' === $s['source'];
		$url      = $is_media ? ( isset( $s['video_file']['url'] ) ? $s['video_file']['url'] : '' ) : ( isset( $s['video_url'] ) ? trim( $s['video_url'] ) : '' );
		if ( ! $url ) {
			return '';
		}
		$on    = function ( $k ) use ( $s ) {
			return isset( $s[ $k ] ) && 'yes' === $s[ $k ];
		};
		$start = ! empty( $s['start'] ) ? (int) $s['start'] : 0;
		$align = 'margin-left:auto;margin-right:auto;';
		if ( $is_media || preg_match( '/\.(mp4|m4v|webm|ogv|mov)(\?.*)?$/i', $url ) ) {
			$attrs = ( $on( 'autoplay' ) ? ' autoplay playsinline' : '' ) . ( $on( 'mute' ) ? ' muted' : '' ) . ( $on( 'loop' ) ? ' loop' : '' ) . ( ! isset( $s['controls'] ) || $on( 'controls' ) ? ' controls' : '' );
			$poster = ! empty( $s['poster']['url'] ) ? ' poster="' . esc_url( $s['poster']['url'] ) . '"' : '';
			$src    = $start ? $url . '#t=' . $start : $url;
			return '<div class="cf-video-frame" style="' . $align . '"><video src="' . esc_url( $src ) . '"' . $attrs . $poster . ' style="width:100%;height:100%;object-fit:cover;display:block"></video></div>';
		}
		// YouTube / Vimeo : adresse du lecteur intégré avec les options.
		$embed = '';
		if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m ) ) {
			$q     = array_filter( array( 'autoplay' => $on( 'autoplay' ) ? 1 : null, 'mute' => $on( 'mute' ) ? 1 : null, 'loop' => $on( 'loop' ) ? 1 : null, 'playlist' => $on( 'loop' ) ? $m[1] : null, 'start' => $start ? $start : null, 'rel' => 0 ) );
			$embed = 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?' . http_build_query( $q );
		} elseif ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
			$q     = array_filter( array( 'autoplay' => $on( 'autoplay' ) ? 1 : null, 'muted' => $on( 'mute' ) ? 1 : null, 'loop' => $on( 'loop' ) ? 1 : null ) );
			$embed = 'https://player.vimeo.com/video/' . $m[1] . ( $q ? '?' . http_build_query( $q ) : '' ) . ( $start ? '#t=' . $start . 's' : '' );
		}
		if ( $embed ) {
			return '<div class="cf-video-frame" style="' . $align . '"><iframe src="' . esc_url( $embed ) . '" style="width:100%;height:100%;border:0;display:block" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="' . esc_attr( isset( $s['title'] ) ? $s['title'] : 'Vidéo' ) . '"></iframe></div>';
		}
		return if_render_video( $url ); // autre lien : lecteur WordPress par défaut.
	}

	protected function render() {
		$s           = $this->get_settings_for_display();
		$item        = $this->base_item( $s );
		$item['url'] = ( isset( $s['source'] ) && 'media' === $s['source'] ) ? ( isset( $s['video_file']['url'] ) ? $s['video_file']['url'] : '' ) : ( isset( $s['video_url'] ) ? $s['video_url'] : '' );
		echo if_render_item( if_current_notion_id(), 'video', $item, $this->player( $s ) ); // phpcs:ignore
	}
}

class CF_Elementor_Document extends CF_Elementor_Base {
	public function get_name() {
		return 'cf_document';
	}
	public function get_title() {
		return __( 'Document suivi', 'catalogue-formations' );
	}
	public function get_icon() {
		return 'eicon-document-file';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Document', 'catalogue-formations' ) ) );
		$this->title_controls();
		$this->add_control( 'file', array( 'label' => __( 'PDF', 'catalogue-formations' ), 'type' => Controls_Manager::MEDIA, 'media_types' => array( 'application/pdf' ) ) );
		$this->add_control( 'preview', array( 'label' => __( 'Afficher l\'aperçu du PDF dans la page', 'catalogue-formations' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'preview_height', array( 'label' => __( 'Hauteur de l\'aperçu (px)', 'catalogue-formations' ), 'type' => Controls_Manager::NUMBER, 'default' => 600, 'min' => 200, 'condition' => array( 'preview' => 'yes' ) ) );
		$this->end_controls_section();
		$this->style_controls();
	}

	protected function render() {
		$s                      = $this->get_settings_for_display();
		$item                   = $this->base_item( $s );
		$item['attachment_id']  = isset( $s['file']['id'] ) ? absint( $s['file']['id'] ) : 0;
		$item['preview']        = isset( $s['preview'] ) && 'yes' === $s['preview'];
		$item['preview_height'] = ! empty( $s['preview_height'] ) ? (int) $s['preview_height'] : 600;
		echo if_render_item( if_current_notion_id(), 'pdf', $item ); // phpcs:ignore
	}
}

class CF_Elementor_Exercice extends CF_Elementor_Base {
	public function get_name() {
		return 'cf_exercice';
	}
	public function get_title() {
		return __( 'Exercice', 'catalogue-formations' );
	}
	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Exercice', 'catalogue-formations' ) ) );
		$this->title_controls();
		$this->add_control( 'instructions', array( 'label' => __( 'Consigne (texte, images, listes…)', 'catalogue-formations' ), 'type' => Controls_Manager::WYSIWYG ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_instr', array( 'label' => __( 'Consigne', 'catalogue-formations' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'instr_bg', array( 'label' => __( 'Fond', 'catalogue-formations' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .if-instructions' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'instr_typo', 'selector' => '{{WRAPPER}} .if-instructions' ) );
		$this->end_controls_section();
		$this->style_controls();
	}

	protected function render() {
		$s                    = $this->get_settings_for_display();
		$item                 = $this->base_item( $s );
		$item['instructions'] = isset( $s['instructions'] ) ? $s['instructions'] : '';
		echo if_render_item( if_current_notion_id(), 'exercise', $item ); // phpcs:ignore
	}
}

class CF_Elementor_H5P extends CF_Elementor_Base {
	public function get_name() {
		return 'cf_h5p';
	}
	public function get_title() {
		return __( 'Activité H5P suivie', 'catalogue-formations' );
	}
	public function get_icon() {
		return 'eicon-puzzle-piece';
	}
	public function get_keywords() {
		return array( 'h5p', 'quiz', 'interactif', 'activité', 'formation', 'suivi' );
	}

	protected function register_controls() {
		$options = array( 0 => __( '— Choisir une activité —', 'catalogue-formations' ) );
		foreach ( if_h5p_contents() as $id => $c ) {
			$options[ $id ] = $c['title'] . ( $c['type'] ? ' (' . $c['type'] . ')' : '' );
		}
		$this->start_controls_section( 'content', array( 'label' => __( 'Activité H5P', 'catalogue-formations' ) ) );
		$this->title_controls();
		$this->add_control( 'content_id', array( 'label' => __( 'Activité', 'catalogue-formations' ), 'type' => Controls_Manager::SELECT, 'options' => $options, 'default' => 0 ) );
		$this->add_control(
			'new_h5p',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<a href="' . esc_url( admin_url( 'admin.php?page=h5p_new' ) ) . '" target="_blank">' . esc_html__( '+ Créer une activité H5P (nouvel onglet), puis rechargez Elementor', 'catalogue-formations' ) . '</a>',
			)
		);
		$this->add_control( 'pass', array( 'label' => __( 'Score minimum pour valider (%)', 'catalogue-formations' ), 'description' => __( '0 = il suffit de terminer l\'activité.', 'catalogue-formations' ), 'type' => Controls_Manager::NUMBER, 'min' => 0, 'max' => 100, 'default' => 0 ) );
		$this->end_controls_section();
		$this->style_controls();
	}

	protected function render() {
		$s                  = $this->get_settings_for_display();
		$item               = $this->base_item( $s );
		$item['content_id'] = isset( $s['content_id'] ) ? absint( $s['content_id'] ) : 0;
		$item['pass']       = isset( $s['pass'] ) ? (int) $s['pass'] : 0;
		echo if_render_item( if_current_notion_id(), 'h5p', $item ); // phpcs:ignore
	}
}
