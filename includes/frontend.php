<?php
/**
 * Côté apprenant : catalogue, page d'une notion, tableau de bord.
 *
 * Shortcodes :
 *  [if_catalogue]          catalogue par niveaux et matières
 *  [if_tableau_de_bord]    mes accès, mon avancement, mes corrections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'if_front_assets' );
add_filter( 'the_content', 'if_notion_content', 20 );
add_shortcode( 'if_catalogue', 'if_sc_catalogue' );
add_shortcode( 'if_tableau_de_bord', 'if_sc_dashboard' );

function if_front_assets() {
	wp_enqueue_style( 'if-front', IF_URL . 'assets/front.css', array(), IF_VERSION );
}

/* ---------- Petits éléments réutilisables ---------- */

function if_notice() {
	if ( empty( $_GET['if_msg'] ) ) { // phpcs:ignore
		return '';
	}
	$msgs = array(
		'requested' => array( 'ok', __( 'Votre demande a bien été envoyée. Vous recevrez un e-mail quand l\'accès sera ouvert.', 'catalogue-formations' ) ),
		'exists'    => array( 'info', __( 'Vous avez déjà une demande ou un accès pour cette notion.', 'catalogue-formations' ) ),
		'submitted' => array( 'ok', __( 'Votre réponse a bien été envoyée. Elle sera corrigée prochainement.', 'catalogue-formations' ) ),
		'empty'     => array( 'error', __( 'Écrivez une réponse ou joignez un fichier.', 'catalogue-formations' ) ),
		'upload'    => array( 'error', __( 'Le fichier n\'a pas pu être envoyé (formats acceptés : PDF, Word, ODT, JPG, PNG, TXT).', 'catalogue-formations' ) ),
		'noaccess'  => array( 'error', __( 'Vous n\'avez pas accès à cette notion actuellement.', 'catalogue-formations' ) ),
		'error'     => array( 'error', __( 'Une erreur est survenue.', 'catalogue-formations' ) ),
	);
	$k = sanitize_key( wp_unslash( $_GET['if_msg'] ) ); // phpcs:ignore
	if ( ! isset( $msgs[ $k ] ) ) {
		return '';
	}
	return '<div class="if-notice if-notice--' . esc_attr( $msgs[ $k ][0] ) . '" role="status">' . esc_html( $msgs[ $k ][1] ) . '</div>';
}

function if_badge( $state ) {
	return '<span class="if-badge if-badge--' . esc_attr( $state ) . '">' . esc_html( if_state_label( $state ) ) . '</span>';
}

function if_progress_bar( $p ) {
	return sprintf(
		'<div class="if-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d"><span style="width:%1$d%%"></span></div><small>%2$s</small>',
		(int) $p['percent'],
		esc_html( sprintf( '%d %% — %d / %d', $p['percent'], $p['done'], $p['total'] ) )
	);
}

function if_login_links() {
	$here = get_permalink();
	$html = '<p class="if-login">' . esc_html__( 'Connectez-vous pour commander cette notion.', 'catalogue-formations' ) . ' ';
	$html .= '<a class="if-btn" href="' . esc_url( wp_login_url( $here ) ) . '">' . esc_html__( 'Se connecter', 'catalogue-formations' ) . '</a> ';
	if ( get_option( 'users_can_register' ) ) {
		$html .= '<a class="if-btn if-btn--ghost" href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Créer un compte', 'catalogue-formations' ) . '</a>';
	}
	return $html . '</p>';
}

function if_request_form( $notion_id, $with_message = true ) {
	ob_start();
	?>
	<form class="if-request" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="if_request">
		<input type="hidden" name="notion_id" value="<?php echo (int) $notion_id; ?>">
		<?php wp_nonce_field( 'if_request_' . $notion_id ); ?>
		<?php if ( $with_message ) : ?>
			<label for="if-msg-<?php echo (int) $notion_id; ?>"><?php esc_html_e( 'Message (facultatif) : vos disponibilités, vos besoins…', 'catalogue-formations' ); ?></label>
			<textarea id="if-msg-<?php echo (int) $notion_id; ?>" name="message" rows="3"></textarea>
		<?php endif; ?>
		<button type="submit" class="if-btn"><?php esc_html_e( 'Commander cette notion', 'catalogue-formations' ); ?></button>
	</form>
	<?php
	return ob_get_clean();
}

/* ---------- Page d'une notion ---------- */

/** Bloc d'état quand l'utilisateur n'a pas (encore) accès. */
function if_access_box( $notion_id, $user ) {
	if ( ! $user ) {
		return if_login_links();
	}
	$a   = if_user_access( $user, $notion_id );
	$out = '<div class="if-box"><p>' . if_badge( $a['state'] ) . '</p>';
	if ( 'upcoming' === $a['state'] ) {
		$out .= '<p>' . esc_html( sprintf( __( 'Votre accès ouvrira le %s.', 'catalogue-formations' ), if_date( $a['row']->date_start ) ) ) . '</p>';
	} elseif ( 'pending' === $a['state'] ) {
		$out .= '<p>' . esc_html__( 'Votre demande est en cours de traitement.', 'catalogue-formations' ) . '</p>';
	} else {
		if ( 'expired' === $a['state'] ) {
			$out .= '<p>' . esc_html( sprintf( __( 'Votre accès s\'est terminé le %s. Vous pouvez faire une nouvelle demande.', 'catalogue-formations' ), if_date( $a['row']->date_end ) ) ) . '</p>';
		}
		$out .= if_request_form( $notion_id );
	}
	return $out . '</div>';
}

/** Bloc « Mon avancement » quand l'accès est ouvert. */
function if_summary_box( $notion_id, $user ) {
	$a   = if_user_access( $user, $notion_id );
	$p   = if_progress( $user, $notion_id );
	$out = '<div class="if-box if-summary">';
	if ( $a['row'] && 'active' === $a['state'] ) {
		$out .= '<p>' . if_badge( 'active' ) . ' ' . esc_html( sprintf( __( 'jusqu\'au %s', 'catalogue-formations' ), if_date( $a['row']->date_end ) ) ) . '</p>';
	}
	$out .= '<p><strong>' . esc_html__( 'Mon avancement', 'catalogue-formations' ) . '</strong></p>' . if_progress_bar( $p );
	return $out . '</div>';
}

function if_notion_content( $content ) {
	if ( ! is_singular( 'if_notion' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$notion_id = get_the_ID();
	$user      = get_current_user_id();
	$can_view  = $user && if_user_can_view( $user, $notion_id );

	$meta  = '<p class="if-meta"><strong>' . esc_html__( 'Niveau', 'catalogue-formations' ) . ' :</strong> ' . esc_html( if_terms_text( $notion_id, 'if_niveau' ) );
	$meta .= ' · <strong>' . esc_html__( 'Matière', 'catalogue-formations' ) . ' :</strong> ' . esc_html( if_terms_text( $notion_id, 'if_matiere' ) ) . '</p>';
	$top   = if_notice() . $meta . ( $can_view ? if_summary_box( $notion_id, $user ) : if_access_box( $notion_id, $user ) );

	// Contenus placés dans la page (blocs ou Elementor) : ils s'affichent eux-mêmes, verrouillés si besoin.
	if ( if_uses_inline( $notion_id ) ) {
		return $top . $content;
	}

	// Ancienne boîte : contenus ajoutés après la présentation, seulement avec l'accès.
	return $top . $content . ( $can_view ? if_render_notion_body( $notion_id, $user ) : '' );
}

function if_complete_button( $notion_id, $key, $done ) {
	ob_start();
	?>
	<form class="if-complete" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="if_complete">
		<input type="hidden" name="notion_id" value="<?php echo (int) $notion_id; ?>">
		<input type="hidden" name="item_key" value="<?php echo esc_attr( $key ); ?>">
		<?php wp_nonce_field( 'if_complete_' . $notion_id . '_' . $key ); ?>
		<?php if ( $done ) : ?>
			<input type="hidden" name="undo" value="1">
			<span class="if-done">✔ <?php esc_html_e( 'Terminé', 'catalogue-formations' ); ?></span>
			<button type="submit" class="if-link"><?php esc_html_e( 'Annuler', 'catalogue-formations' ); ?></button>
		<?php else : ?>
			<button type="submit" class="if-btn if-btn--small"><?php esc_html_e( 'J\'ai terminé', 'catalogue-formations' ); ?></button>
		<?php endif; ?>
	</form>
	<?php
	return ob_get_clean();
}

function if_render_video( $url ) {
	if ( ! $url ) {
		return '<p class="if-empty-item">' . esc_html__( 'Aucune vidéo choisie.', 'catalogue-formations' ) . '</p>';
	}
	if ( preg_match( '/\.(mp4|m4v|webm|ogv|mov)(\?.*)?$/i', $url ) ) {
		return wp_video_shortcode( array( 'src' => $url ) );
	}
	$embed = wp_oembed_get( $url );
	return $embed ? '<div class="if-embed">' . $embed . '</div>' : '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ouvrir la vidéo', 'catalogue-formations' ) . '</a></p>';
}

/**
 * Affiche UN élément suivi (vidéo, PDF, exercice), utilisé par la boîte, les blocs et les widgets.
 * $item : [ key, title, url | attachment_id | instructions ].
 */
function if_render_item( $notion_id, $type, $item, $inner_html = null, $wrapper = null ) {
	$user  = get_current_user_id();
	$key   = isset( $item['key'] ) ? $item['key'] : '';
	$title = isset( $item['title'] ) && '' !== $item['title'] ? $item['title'] : if_type_label( $type );
	$icons = array( 'video' => '🎬', 'pdf' => '📄', 'exercise' => '✏️', 'h5p' => '🧩' );

	if ( ! $notion_id || 'if_notion' !== get_post_type( $notion_id ) ) {
		return '<p class="if-empty-item">' . esc_html__( 'Cet élément ne fonctionne que dans une notion.', 'catalogue-formations' ) . '</p>';
	}

	// Sans accès : élément visible mais verrouillé.
	if ( ! $user || ! if_user_can_view( $user, $notion_id ) ) {
		$open = $wrapper ? '<section ' . str_replace( 'if-item ', 'if-item if-item--locked ', $wrapper ) . '>' : '<section class="if-item if-item--locked">';
		return $open . '<h3>🔒 ' . esc_html( $title ) . '</h3><p>' . esc_html( sprintf( __( '%s disponible avec l\'accès à cette notion.', 'catalogue-formations' ), if_type_label( $type ) ) ) . '</p></section>';
	}

	$completed = if_completed_keys( $user, $notion_id );
	$done      = in_array( $key, $completed, true );

	ob_start();
	echo $wrapper ? '<section ' . $wrapper . '>' : '<section class="if-item if-item--' . esc_attr( $type ) . '" id="' . esc_attr( $key ) . '">'; // phpcs:ignore
	if ( ! isset( $item['show_title'] ) || $item['show_title'] ) {
		echo '<h3 class="if-item__title">' . esc_html( $icons[ $type ] . ' ' . $title ) . '</h3>';
	}

	if ( 'video' === $type ) {
		echo null !== $inner_html && '' !== trim( $inner_html ) ? $inner_html : if_render_video( isset( $item['url'] ) ? $item['url'] : '' ); // phpcs:ignore
		echo $key ? if_complete_button( $notion_id, $key, $done ) : ''; // phpcs:ignore
	} elseif ( 'pdf' === $type ) {
		if ( ! empty( $item['attachment_id'] ) && null !== $inner_html && '' !== trim( $inner_html ) ) {
			// Bloc Fichier de WordPress : ses liens pointent vers l'adresse protégée.
			$direct = wp_get_attachment_url( (int) $item['attachment_id'] );
			$safe   = if_pdf_url( $notion_id, $key );
			echo $direct ? str_replace( array( esc_url( $direct ), $direct ), $safe, $inner_html ) : $inner_html; // phpcs:ignore
			echo $key ? if_complete_button( $notion_id, $key, $done ) : ''; // phpcs:ignore
		} elseif ( ! empty( $item['attachment_id'] ) ) {
			if ( ! empty( $item['preview'] ) ) {
				echo '<iframe class="if-pdf-preview" src="' . esc_url( if_pdf_url( $notion_id, $key ) ) . '" style="height:' . (int) ( ! empty( $item['preview_height'] ) ? $item['preview_height'] : 600 ) . 'px" title="' . esc_attr( $title ) . '"></iframe>';
			}
			echo '<p><a class="if-btn if-btn--ghost" href="' . esc_url( if_pdf_url( $notion_id, $key ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ouvrir le PDF', 'catalogue-formations' ) . '</a></p>';
			echo $key ? if_complete_button( $notion_id, $key, $done ) : ''; // phpcs:ignore
		} else {
			echo '<p class="if-empty-item">' . esc_html__( 'Aucun document choisi.', 'catalogue-formations' ) . '</p>';
		}
	} elseif ( 'h5p' === $type ) {
		echo if_render_h5p( $notion_id, $item ); // phpcs:ignore
	} else {
		$subs = if_user_submissions( $user, $notion_id );
		$sub  = isset( $subs[ $key ] ) ? $subs[ $key ] : null;
		if ( null !== $inner_html && '' !== trim( $inner_html ) ) {
			echo '<div class="if-instructions">' . $inner_html . '</div>'; // phpcs:ignore -- contenu de blocs déjà rendu.
		} else {
			echo '<div class="if-instructions">' . wp_kses_post( wpautop( isset( $item['instructions'] ) ? $item['instructions'] : '' ) ) . '</div>';
		}
		if ( $sub ) {
			echo '<div class="if-answer"><p><strong>' . esc_html__( 'Ma dernière réponse', 'catalogue-formations' ) . '</strong> — ' . esc_html( mysql2date( get_option( 'date_format' ), $sub->submitted_at ) ) . '</p>';
			if ( $sub->answer ) {
				echo wp_kses_post( wpautop( $sub->answer ) );
			}
			if ( $sub->file_id ) {
				echo '<p>📎 <a href="' . esc_url( wp_get_attachment_url( $sub->file_id ) ) . '" target="_blank" rel="noopener">' . esc_html( basename( (string) get_attached_file( $sub->file_id ) ) ) . '</a></p>';
			}
			if ( 'corrected' === $sub->status ) {
				echo '<div class="if-feedback"><p><strong>✅ ' . esc_html__( 'Correction', 'catalogue-formations' ) . '</strong></p>' . wp_kses_post( wpautop( $sub->feedback ) ) . '</div>';
			} else {
				echo '<p class="if-badge if-badge--pending">' . esc_html__( 'En attente de correction', 'catalogue-formations' ) . '</p>';
			}
			echo '</div>';
		}
		if ( $key ) {
			?>
			<details class="if-submit" <?php echo $sub ? '' : 'open'; ?>>
				<summary><?php echo $sub ? esc_html__( 'Envoyer une nouvelle réponse', 'catalogue-formations' ) : esc_html__( 'Répondre', 'catalogue-formations' ); ?></summary>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="if_submit">
					<input type="hidden" name="notion_id" value="<?php echo (int) $notion_id; ?>">
					<input type="hidden" name="exercise_key" value="<?php echo esc_attr( $key ); ?>">
					<?php wp_nonce_field( 'if_submit_' . $notion_id . '_' . $key ); ?>
					<label for="ans-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Ma réponse', 'catalogue-formations' ); ?></label>
					<textarea id="ans-<?php echo esc_attr( $key ); ?>" name="answer" rows="6"></textarea>
					<label for="file-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Joindre un fichier (facultatif)', 'catalogue-formations' ); ?></label>
					<input id="file-<?php echo esc_attr( $key ); ?>" type="file" name="answer_file" accept=".pdf,.doc,.docx,.odt,.jpg,.jpeg,.png,.txt">
					<button type="submit" class="if-btn"><?php esc_html_e( 'Envoyer', 'catalogue-formations' ); ?></button>
				</form>
			</details>
			<?php
		}
	}
	echo '</section>';
	return ob_get_clean();
}

/** Ancienne boîte : contenus regroupés par type, après la présentation. */
function if_render_notion_body( $notion_id, $user ) {
	$out    = '';
	$groups = array(
		'video'    => array( '🎬 ' . __( 'Vidéos', 'catalogue-formations' ), if_get_videos( $notion_id ) ),
		'pdf'      => array( '📄 ' . __( 'Documents', 'catalogue-formations' ), if_get_pdfs( $notion_id ) ),
		'exercise' => array( '✏️ ' . __( 'Exercices', 'catalogue-formations' ), if_get_exercises( $notion_id ) ),
	);
	foreach ( $groups as $type => $g ) {
		if ( $g[1] ) {
			$out .= '<h2>' . esc_html( $g[0] ) . '</h2>';
			foreach ( $g[1] as $it ) {
				$out .= if_render_item( $notion_id, $type, $it );
			}
		}
	}
	return $out ? $out : '<p>' . esc_html__( 'Les contenus de cette notion arrivent bientôt.', 'catalogue-formations' ) . '</p>';
}

/**
 * Liste hiérarchique des termes (parents puis enfants), triés par « Ordre ».
 * Chaque élément : objet WP_Term + ->if_depth + ->if_path (« Collège › 6ème »).
 */
function if_terms_tree( $tax ) {
	$all = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
	if ( is_wp_error( $all ) || ! $all ) {
		return array();
	}
	$by_parent = array();
	foreach ( $all as $t ) {
		$by_parent[ (int) $t->parent ][] = $t;
	}
	$sort = function ( &$list ) {
		usort( $list, 'if_compare_terms' );
	};
	$out  = array();
	$walk = function ( $parent, $depth, $path ) use ( &$walk, &$out, $by_parent, $sort ) {
		if ( empty( $by_parent[ $parent ] ) ) {
			return;
		}
		$list = $by_parent[ $parent ];
		$sort( $list );
		foreach ( $list as $t ) {
			$t->if_depth = $depth;
			$t->if_path  = $path ? $path . ' › ' . $t->name : $t->name;
			$out[]       = $t;
			$walk( (int) $t->term_id, $depth + 1, $t->if_path );
		}
	};
	$walk( 0, 0, '' );
	return $out;
}

/** Réglages des filtres du catalogue (Formation → Réglages). */
function if_catalogue_settings() {
	$f = get_option( 'if_filters', null );
	if ( ! is_array( $f ) ) {
		$f = array( 'niveau' => 1, 'gniveau' => 1, 'matiere' => 1, 'gmatiere' => 1 );
	}
	return array(
		'filters'  => wp_parse_args( $f, array( 'niveau' => 0, 'gniveau' => 0, 'matiere' => 0, 'gmatiere' => 0 ) ),
		'sections' => get_option( 'if_sections', 'niveau' ) === 'matiere' ? 'matiere' : 'niveau',
	);
}

/**
 * Catalogue.
 * Attributs (facultatifs, ils remplacent les réglages) :
 *   groupe="niveau" | "matiere"  : sections par niveau puis matière, ou l'inverse
 *   niveau="slug" / matiere="slug" / gniveau="slug" / gmatiere="slug" : n'afficher qu'une partie
 *   filtres="non"                : masquer les listes déroulantes
 */
function if_sc_catalogue( $atts ) {
	$set  = if_catalogue_settings();
	$atts = shortcode_atts( array( 'niveau' => '', 'matiere' => '', 'gniveau' => '', 'gmatiere' => '', 'groupe' => $set['sections'], 'filtres' => 'oui' ), $atts, 'if_catalogue' );

	$get = function ( $key ) use ( $atts ) {
		if ( $atts[ $key ] ) {
			return sanitize_title( $atts[ $key ] );
		}
		return isset( $_GET[ $key ] ) ? sanitize_title( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore
	};
	$f = array(
		'niveau'   => $get( 'niveau' ),
		'gniveau'  => $get( 'gniveau' ),
		'matiere'  => $get( 'matiere' ),
		'gmatiere' => $get( 'gmatiere' ),
	);

	$niveaux  = if_terms_tree( 'if_niveau' );
	$matieres = if_terms_tree( 'if_matiere' );

	// Éléments retenus selon le filtre direct et le filtre de groupe.
	$keep = function ( $terms, $slug, $tax, $gslug ) {
		if ( $slug ) {
			$terms = array_values( array_filter( $terms, function ( $t ) use ( $slug ) { return $t->slug === $slug; } ) );
		}
		if ( $gslug ) {
			$g = get_term_by( 'slug', $gslug, if_group_tax( $tax ) );
			$terms = $g ? array_values( array_filter( $terms, function ( $t ) use ( $g ) { return in_array( (int) $g->term_id, if_groups_of( $t->term_id ), true ); } ) ) : array();
		}
		return $terms;
	};
	$sec_niv = $keep( $niveaux, $f['niveau'], 'if_niveau', $f['gniveau'] );
	$sec_mat = $keep( $matieres, $f['matiere'], 'if_matiere', $f['gmatiere'] );

	ob_start();
	echo if_notice(); // phpcs:ignore

	// Listes déroulantes (selon les réglages ; masquées si imposées par le shortcode).
	$forced = $atts['niveau'] || $atts['matiere'] || $atts['gniveau'] || $atts['gmatiere'];
	if ( 'non' !== $atts['filtres'] && ! $forced ) {
		$groups_with_members = function ( $gtax ) {
			$out = array();
			foreach ( get_terms( array( 'taxonomy' => $gtax, 'hide_empty' => false ) ) as $g ) {
				if ( if_members_of( $g->term_id, $gtax ) ) {
					$out[] = $g;
				}
			}
			usort( $out, 'if_compare_terms' );
			return $out;
		};
		$selects = array();
		if ( $set['filters']['gniveau'] ) {
			$selects[] = array( 'gniveau', __( 'Groupe de niveaux', 'catalogue-formations' ), $groups_with_members( 'if_groupe_niveau' ), __( 'Tous', 'catalogue-formations' ) );
		}
		if ( $set['filters']['niveau'] ) {
			$selects[] = array( 'niveau', __( 'Niveau', 'catalogue-formations' ), $niveaux, __( 'Tous', 'catalogue-formations' ) );
		}
		if ( $set['filters']['gmatiere'] ) {
			$selects[] = array( 'gmatiere', __( 'Groupe de matières', 'catalogue-formations' ), $groups_with_members( 'if_groupe_matiere' ), __( 'Tous', 'catalogue-formations' ) );
		}
		if ( $set['filters']['matiere'] ) {
			$selects[] = array( 'matiere', __( 'Matière', 'catalogue-formations' ), $matieres, __( 'Toutes', 'catalogue-formations' ) );
		}
		$selects = array_filter( $selects, function ( $s ) { return ! empty( $s[2] ); } );
		if ( $selects ) {
			echo '<form class="if-filters" method="get">';
			foreach ( $selects as $s ) {
				echo '<label>' . esc_html( $s[1] ) . '<select name="' . esc_attr( $s[0] ) . '"><option value="">' . esc_html( $s[3] ) . '</option>';
				foreach ( $s[2] as $t ) {
					echo '<option value="' . esc_attr( $t->slug ) . '" ' . selected( $f[ $s[0] ], $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
				}
				echo '</select></label>';
			}
			echo '<button type="submit" class="if-btn if-btn--small">' . esc_html__( 'Filtrer', 'catalogue-formations' ) . '</button>';
			if ( array_filter( $f ) ) {
				echo ' <a class="if-link" href="' . esc_url( strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '?' ) ) . '">' . esc_html__( 'Tout afficher', 'catalogue-formations' ) . '</a>'; // phpcs:ignore
			}
			echo '</form>';
		}
	}

	// Ordre des sections : niveau puis matière, ou l'inverse.
	if ( 'matiere' === $atts['groupe'] ) {
		$outer = array( $sec_mat, 'if_matiere' );
		$inner = array( $sec_niv, 'if_niveau' );
	} else {
		$outer = array( $sec_niv, 'if_niveau' );
		$inner = array( $sec_mat, 'if_matiere' );
	}

	$shown = 0;
	foreach ( $outer[0] as $o ) {
		$html = '';
		foreach ( $inner[0] as $i ) {
			$q = new WP_Query(
				array(
					'post_type'      => 'if_notion',
					'posts_per_page' => -1,
					'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
					'tax_query'      => array( // phpcs:ignore
						'relation' => 'AND',
						array( 'taxonomy' => $outer[1], 'field' => 'term_id', 'terms' => $o->term_id, 'include_children' => false ),
						array( 'taxonomy' => $inner[1], 'field' => 'term_id', 'terms' => $i->term_id, 'include_children' => false ),
					),
				)
			);
			if ( $q->have_posts() ) {
				$html .= '<h3 class="if-cat-matiere">' . esc_html( $i->name ) . '</h3><div class="if-grid">';
				foreach ( $q->posts as $p ) {
					$html .= if_card( $p );
					$shown++;
				}
				$html .= '</div>';
			}
		}
		if ( $html ) {
			echo '<section class="if-cat-niveau"><h2>' . esc_html( $o->name ) . '</h2>' . $html . '</section>'; // phpcs:ignore
		}
	}

	// Notions sans niveau ou sans matière (pour ne rien oublier).
	if ( ! array_filter( $f ) ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'if_notion',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore
					'relation' => 'OR',
					array( 'taxonomy' => 'if_niveau', 'operator' => 'NOT EXISTS' ),
					array( 'taxonomy' => 'if_matiere', 'operator' => 'NOT EXISTS' ),
				),
			)
		);
		if ( $q->have_posts() ) {
			echo '<section class="if-cat-niveau"><h2>' . esc_html__( 'Autres notions', 'catalogue-formations' ) . '</h2><div class="if-grid">';
			foreach ( $q->posts as $p ) {
				echo if_card( $p ); // phpcs:ignore
				$shown++;
			}
			echo '</div></section>';
		}
	}

	if ( ! $shown ) {
		echo '<p>' . esc_html__( 'Aucune notion pour ces critères.', 'catalogue-formations' ) . '</p>';
	}
	return ob_get_clean();
}

function if_card( $post ) {
	$user  = get_current_user_id();
	$state = $user ? if_user_access( $user, $post->ID )['state'] : 'none';
	$html  = '<article class="if-card">';
	if ( has_post_thumbnail( $post ) ) {
		$html .= get_the_post_thumbnail( $post, 'medium', array( 'class' => 'if-card__img' ) );
	}
	$html .= '<h4><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h4>';
	$html .= '<p class="if-card__tags">' . esc_html( if_terms_text( $post->ID, 'if_niveau' ) . ' · ' . if_terms_text( $post->ID, 'if_matiere' ) ) . '</p>';
	$html .= '<p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 25 ) ) . '</p>';
	if ( $user ) {
		$html .= '<p>' . if_badge( $state ) . '</p>';
	}
	if ( $user && in_array( $state, array( 'none', 'expired', 'refused' ), true ) ) {
		$html .= if_request_form( $post->ID, false );
	} else {
		$html .= '<a class="if-btn if-btn--ghost" href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Voir la notion', 'catalogue-formations' ) . '</a>';
	}
	return $html . '</article>';
}

/* ---------- Tableau de bord apprenant ---------- */

function if_sc_dashboard() {
	if ( ! is_user_logged_in() ) {
		return '<p>' . esc_html__( 'Connectez-vous pour voir votre tableau de bord.', 'catalogue-formations' ) . ' <a class="if-btn" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Se connecter', 'catalogue-formations' ) . '</a></p>';
	}
	$user = get_current_user_id();
	$rows = if_get_access_rows( array( 'user_id' => $user ) );

	ob_start();
	echo if_notice(); // phpcs:ignore
	echo '<h2>' . esc_html__( 'Mes notions', 'catalogue-formations' ) . '</h2>';

	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'Vous n\'avez encore commandé aucune notion.', 'catalogue-formations' ) . ' <a href="' . esc_url( if_catalogue_url() ) . '">' . esc_html__( 'Voir le catalogue', 'catalogue-formations' ) . '</a></p>';
		return ob_get_clean();
	}
	?>
	<div class="if-table-wrap">
	<table class="if-table">
		<thead><tr>
			<th><?php esc_html_e( 'Notion', 'catalogue-formations' ); ?></th>
			<th><?php esc_html_e( 'Période', 'catalogue-formations' ); ?></th>
			<th><?php esc_html_e( 'État', 'catalogue-formations' ); ?></th>
			<th><?php esc_html_e( 'Avancement', 'catalogue-formations' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : ?>
			<?php
			$state = if_row_state( $r );
			$p     = if_progress( $user, $r->notion_id );
			?>
			<tr>
				<td data-label="<?php esc_attr_e( 'Notion', 'catalogue-formations' ); ?>"><a href="<?php echo esc_url( get_permalink( $r->notion_id ) ); ?>"><?php echo esc_html( get_the_title( $r->notion_id ) ); ?></a></td>
				<td data-label="<?php esc_attr_e( 'Période', 'catalogue-formations' ); ?>"><?php echo 'active' === $r->status ? esc_html( if_date( $r->date_start ) . ' → ' . if_date( $r->date_end ) ) : '—'; ?></td>
				<td data-label="<?php esc_attr_e( 'État', 'catalogue-formations' ); ?>"><?php echo if_badge( $state ); // phpcs:ignore ?></td>
				<td data-label="<?php esc_attr_e( 'Avancement', 'catalogue-formations' ); ?>"><?php echo 'pending' === $state || 'refused' === $state ? '—' : if_progress_bar( $p ); // phpcs:ignore ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
	// Corrections reçues.
	global $wpdb;
	$t    = if_table( 'submissions' );
	$subs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d ORDER BY submitted_at DESC LIMIT 20", $user ) ); // phpcs:ignore
	if ( $subs ) {
		echo '<h2>' . esc_html__( 'Mes exercices', 'catalogue-formations' ) . '</h2><ul class="if-list">';
		foreach ( $subs as $s ) {
			$ex    = if_find_item( $s->notion_id, $s->exercise_key, 'exercise' );
			$label = 'corrected' === $s->status ? '✅ ' . __( 'Corrigé', 'catalogue-formations' ) : '⏳ ' . __( 'En attente de correction', 'catalogue-formations' );
			echo '<li><a href="' . esc_url( get_permalink( $s->notion_id ) . '#' . $s->exercise_key ) . '">' . esc_html( get_the_title( $s->notion_id ) . ' — ' . ( $ex ? $ex['title'] : '' ) ) . '</a> · ' . esc_html( $label ) . '</li>';
		}
		echo '</ul>';
	}
	return ob_get_clean();
}
