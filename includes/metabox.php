<?php
/**
 * Boîte « Contenus de la notion » : vidéos, PDF, exercices (lignes répétables).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// La boîte « Contenus de la notion » est remplacée par les blocs / widgets suivis (v1.8).
// add_action( 'add_meta_boxes', 'if_add_metaboxes' );
add_action( 'save_post_if_notion', 'if_save_metabox', 10, 2 );
add_action( 'admin_enqueue_scripts', 'if_admin_assets' );

function if_add_metaboxes() {
	add_meta_box( 'if_contents', __( 'Contenus de la notion', 'catalogue-formations' ), 'if_render_metabox', 'if_notion', 'normal', 'high' );
}

function if_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( $screen && 'if_notion' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
	}
	wp_enqueue_style( 'if-admin', IF_URL . 'assets/admin.css', array(), IF_VERSION );
	wp_enqueue_script( 'if-admin', IF_URL . 'assets/admin.js', array( 'jquery' ), IF_VERSION, true );
}

function if_render_metabox( $post ) {
	$hint = '<p class="if-hint">💡 ' . esc_html__( 'Pour placer les contenus où vous voulez (entre du texte, des images, des colonnes…), ajoutez dans la page les blocs « Vidéo suivie », « Document suivi » et « Exercice » (catégorie Formation), ou les widgets du même nom dans Elementor.', 'catalogue-formations' ) . '</p>';

	// Contenus placés dans la page : la boîte affiche seulement ce qui a été détecté.
	if ( if_uses_inline( $post->ID ) ) {
		$items = if_get_items( $post->ID );
		echo '<p><strong>' . esc_html__( 'Les contenus de cette notion sont placés directement dans la page.', 'catalogue-formations' ) . '</strong> ' . esc_html__( 'Modifiez-les dans l\'éditeur (blocs ou Elementor).', 'catalogue-formations' ) . '</p>';
		echo '<p>' . esc_html( sprintf( _n( '%d élément suivi détecté (mis à jour à chaque enregistrement) :', '%d éléments suivis détectés (mis à jour à chaque enregistrement) :', count( $items ), 'catalogue-formations' ), count( $items ) ) ) . '</p><ol>';
		foreach ( $items as $it ) {
			echo '<li>' . esc_html( if_type_label( $it['type'] ) . ' — ' . $it['title'] ) . '</li>';
		}
		echo '</ol>';
		return;
	}

	echo $hint; // phpcs:ignore
	wp_nonce_field( 'if_save_contents', 'if_contents_nonce' );
	$videos    = if_get_videos( $post->ID );
	$pdfs      = if_get_pdfs( $post->ID );
	$exercises = if_get_exercises( $post->ID );
	?>
	<p class="description"><?php esc_html_e( 'Ajoutez les contenus dans l\'ordre où l\'apprenant doit les suivre. Chaque élément compte dans l\'avancement.', 'catalogue-formations' ); ?></p>

	<h3>🎬 <?php esc_html_e( 'Vidéos', 'catalogue-formations' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Collez un lien YouTube / Vimeo, ou téléversez un fichier vidéo (.mp4, .webm) avec « Choisir dans la médiathèque ».', 'catalogue-formations' ); ?></p>
	<div class="if-repeat" data-template="if-tpl-video">
		<?php
		foreach ( $videos as $i => $v ) {
			if_row_video( $i, $v );
		}
		?>
	</div>
	<p><button type="button" class="button if-add" data-target="if-tpl-video">+ <?php esc_html_e( 'Ajouter une vidéo', 'catalogue-formations' ); ?></button></p>

	<h3>📄 <?php esc_html_e( 'Documents PDF', 'catalogue-formations' ); ?></h3>
	<div class="if-repeat" data-template="if-tpl-pdf">
		<?php
		foreach ( $pdfs as $i => $p ) {
			if_row_pdf( $i, $p );
		}
		?>
	</div>
	<p><button type="button" class="button if-add" data-target="if-tpl-pdf">+ <?php esc_html_e( 'Ajouter un PDF', 'catalogue-formations' ); ?></button></p>

	<h3>✏️ <?php esc_html_e( 'Exercices', 'catalogue-formations' ); ?></h3>
	<p class="description"><?php esc_html_e( 'L\'apprenant répond par écrit et peut joindre un fichier. Vous recevez une alerte pour corriger.', 'catalogue-formations' ); ?></p>
	<div class="if-repeat" data-template="if-tpl-exercise">
		<?php
		foreach ( $exercises as $i => $e ) {
			if_row_exercise( $i, $e );
		}
		?>
	</div>
	<p><button type="button" class="button if-add" data-target="if-tpl-exercise">+ <?php esc_html_e( 'Ajouter un exercice', 'catalogue-formations' ); ?></button></p>

	<script type="text/html" id="if-tpl-video"><?php if_row_video( '__i__', array() ); ?></script>
	<script type="text/html" id="if-tpl-pdf"><?php if_row_pdf( '__i__', array() ); ?></script>
	<script type="text/html" id="if-tpl-exercise"><?php if_row_exercise( '__i__', array() ); ?></script>
	<?php
}

function if_row_video( $i, $v ) {
	$v = wp_parse_args( $v, array( 'key' => '', 'title' => '', 'url' => '' ) );
	?>
	<div class="if-row">
		<input type="hidden" name="if_videos[<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $v['key'] ); ?>">
		<label><?php esc_html_e( 'Titre', 'catalogue-formations' ); ?>
			<input type="text" class="widefat" name="if_videos[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $v['title'] ); ?>"></label>
		<label><?php esc_html_e( 'Vidéo : lien YouTube / Vimeo, ou fichier de la médiathèque', 'catalogue-formations' ); ?>
			<span class="if-video-line">
				<input type="url" class="widefat if-video-url" name="if_videos[<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $v['url'] ); ?>" placeholder="https://www.youtube.com/watch?v=…">
				<button type="button" class="button if-pick-video"><?php esc_html_e( 'Choisir dans la médiathèque', 'catalogue-formations' ); ?></button>
			</span></label>
		<button type="button" class="button-link-delete if-remove"><?php esc_html_e( 'Supprimer', 'catalogue-formations' ); ?></button>
	</div>
	<?php
}

function if_row_pdf( $i, $p ) {
	$p    = wp_parse_args( $p, array( 'key' => '', 'title' => '', 'attachment_id' => '' ) );
	$file = $p['attachment_id'] ? basename( (string) get_attached_file( (int) $p['attachment_id'] ) ) : '';
	?>
	<div class="if-row">
		<input type="hidden" name="if_pdfs[<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $p['key'] ); ?>">
		<label><?php esc_html_e( 'Titre', 'catalogue-formations' ); ?>
			<input type="text" class="widefat" name="if_pdfs[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $p['title'] ); ?>"></label>
		<input type="hidden" class="if-attachment-id" name="if_pdfs[<?php echo esc_attr( $i ); ?>][attachment_id]" value="<?php echo esc_attr( $p['attachment_id'] ); ?>">
		<button type="button" class="button if-pick-file"><?php esc_html_e( 'Choisir le PDF', 'catalogue-formations' ); ?></button>
		<span class="if-file-name"><?php echo esc_html( $file ); ?></span>
		<button type="button" class="button-link-delete if-remove"><?php esc_html_e( 'Supprimer', 'catalogue-formations' ); ?></button>
	</div>
	<?php
}

function if_row_exercise( $i, $e ) {
	$e = wp_parse_args( $e, array( 'key' => '', 'title' => '', 'instructions' => '' ) );
	?>
	<div class="if-row">
		<input type="hidden" name="if_exercises[<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $e['key'] ); ?>">
		<label><?php esc_html_e( 'Titre', 'catalogue-formations' ); ?>
			<input type="text" class="widefat" name="if_exercises[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $e['title'] ); ?>"></label>
		<label><?php esc_html_e( 'Consigne', 'catalogue-formations' ); ?>
			<textarea class="widefat" rows="4" name="if_exercises[<?php echo esc_attr( $i ); ?>][instructions]"><?php echo esc_textarea( $e['instructions'] ); ?></textarea></label>
		<button type="button" class="button-link-delete if-remove"><?php esc_html_e( 'Supprimer', 'catalogue-formations' ); ?></button>
	</div>
	<?php
}

function if_save_metabox( $post_id, $post ) {
	if ( ! isset( $_POST['if_contents_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['if_contents_nonce'] ) ), 'if_save_contents' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- nettoyé ligne par ligne ci-dessous.
	$raw_v = isset( $_POST['if_videos'] ) && is_array( $_POST['if_videos'] ) ? wp_unslash( $_POST['if_videos'] ) : array();
	$raw_p = isset( $_POST['if_pdfs'] ) && is_array( $_POST['if_pdfs'] ) ? wp_unslash( $_POST['if_pdfs'] ) : array();
	$raw_e = isset( $_POST['if_exercises'] ) && is_array( $_POST['if_exercises'] ) ? wp_unslash( $_POST['if_exercises'] ) : array();
	// phpcs:enable

	$videos = array();
	foreach ( $raw_v as $row ) {
		$url = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
		if ( ! $url ) {
			continue;
		}
		$videos[] = array(
			'key'   => ! empty( $row['key'] ) ? sanitize_key( $row['key'] ) : if_new_key( 'v' ),
			'title' => sanitize_text_field( $row['title'] ?? '' ) ?: __( 'Vidéo', 'catalogue-formations' ),
			'url'   => $url,
		);
	}

	$pdfs = array();
	foreach ( $raw_p as $row ) {
		$aid = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
		if ( ! $aid ) {
			continue;
		}
		$pdfs[] = array(
			'key'           => ! empty( $row['key'] ) ? sanitize_key( $row['key'] ) : if_new_key( 'p' ),
			'title'         => sanitize_text_field( $row['title'] ?? '' ) ?: get_the_title( $aid ),
			'attachment_id' => $aid,
		);
	}

	$exercises = array();
	foreach ( $raw_e as $row ) {
		$title = sanitize_text_field( $row['title'] ?? '' );
		$instr = wp_kses_post( $row['instructions'] ?? '' );
		if ( ! $title && ! $instr ) {
			continue;
		}
		$exercises[] = array(
			'key'          => ! empty( $row['key'] ) ? sanitize_key( $row['key'] ) : if_new_key( 'e' ),
			'title'        => $title ?: __( 'Exercice', 'catalogue-formations' ),
			'instructions' => $instr,
		);
	}

	update_post_meta( $post_id, '_if_videos', $videos );
	update_post_meta( $post_id, '_if_pdfs', $pdfs );
	update_post_meta( $post_id, '_if_exercises', $exercises );
}

/**
 * Éditeur de blocs : ouvre automatiquement la zone « Boîtes méta »
 * pour que « Contenus de la notion » soit visible sans clic.
 */
// add_action( 'enqueue_block_editor_assets', 'if_open_metaboxes_pane' ); // plus de boîte à ouvrir.
function if_open_metaboxes_pane() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'if_notion' !== $screen->post_type ) {
		return;
	}
	// Notion construite avec les blocs : on laisse la place à l'éditeur.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore
	if ( $post_id && ( if_uses_inline( $post_id ) || false !== strpos( (string) get_post_field( 'post_content', $post_id ), '<!-- wp:catalogue-formations/' ) ) ) {
		return;
	}
	$js = "wp.domReady(function(){try{var p=wp.data.dispatch('core/preferences');p.set('core/edit-post','metaBoxesMainIsOpen',true);var h=wp.data.select('core/preferences').get('core/edit-post','metaBoxesMainOpenHeight');if(!h||h<350){p.set('core/edit-post','metaBoxesMainOpenHeight',Math.round(window.innerHeight*0.4));}}catch(e){}});";
	wp_add_inline_script( 'wp-edit-post', $js );
}
