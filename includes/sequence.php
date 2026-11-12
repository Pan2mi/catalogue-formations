<?php
/**
 * Accès aux contenus d'une notion (comme les « restrictions d'accès » de Moodle).
 *
 * - Notion : « Parcours dans l'ordre » (encadré Parcours) → chaque contenu attend le précédent.
 * - Contenu : « Toujours accessible » (exception au parcours dans l'ordre).
 * - Contenu : « Masqué aux apprenants » (brouillon : invisible et hors avancement).
 * - Contenu : conditions (toutes / au moins une) :
 *     done  : tel contenu terminé ;
 *     score : score minimum (%) à tel contenu noté ;
 *     date  : à partir du… / jusqu'au… ;
 *     delay : X jours après le début de l'accès de l'apprenant.
 *   Si elles ne sont pas remplies : contenu affiché avec un cadenas, ou caché.
 * Les formateurs (droit de modifier la notion) voient tout, avec un rappel des conditions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes_if_notion', 'if_sequence_metabox' );
add_action( 'save_post_if_notion', 'if_sequence_save' );

function if_is_sequential( $notion_id ) {
	return (bool) get_post_meta( $notion_id, '_if_sequential', true );
}

/* ---------- Règles : nettoyage ---------- */

function if_sanitize_rules( $r ) {
	if ( is_string( $r ) ) {
		$r = json_decode( $r, true );
	}
	if ( ! is_array( $r ) ) {
		return array();
	}
	$conds = array();
	foreach ( isset( $r['conds'] ) && is_array( $r['conds'] ) ? $r['conds'] : array() as $c ) {
		$type = isset( $c['type'] ) ? $c['type'] : '';
		if ( 'done' === $type && ! empty( $c['key'] ) ) {
			$conds[] = array( 'type' => 'done', 'key' => sanitize_key( $c['key'] ) );
		} elseif ( 'score' === $type && ! empty( $c['key'] ) ) {
			$conds[] = array( 'type' => 'score', 'key' => sanitize_key( $c['key'] ), 'min' => min( 100, max( 0, (int) ( isset( $c['min'] ) ? $c['min'] : 50 ) ) ) );
		} elseif ( 'date' === $type ) {
			$from  = isset( $c['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $c['from'] ) ? $c['from'] : '';
			$until = isset( $c['until'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $c['until'] ) ? $c['until'] : '';
			if ( $from || $until ) {
				$conds[] = array( 'type' => 'date', 'from' => $from, 'until' => $until );
			}
		} elseif ( 'delay' === $type ) {
			$conds[] = array( 'type' => 'delay', 'days' => max( 0, (int) ( isset( $c['days'] ) ? $c['days'] : 0 ) ) );
		}
	}
	if ( ! $conds ) {
		return array();
	}
	return array(
		'match'   => isset( $r['match'] ) && 'any' === $r['match'] ? 'any' : 'all',
		'display' => isset( $r['display'] ) && 'hidden' === $r['display'] ? 'hidden' : 'locked',
		'conds'   => $conds,
	);
}

/** Règles depuis un widget Elementor (répéteur « Conditions »). */
function if_rules_from_elementor( $s ) {
	$conds = array();
	foreach ( isset( $s['conds'] ) && is_array( $s['conds'] ) ? $s['conds'] : array() as $c ) {
		$type    = isset( $c['type'] ) ? $c['type'] : '';
		$conds[] = array(
			'type'  => $type,
			'key'   => isset( $c['item'] ) ? $c['item'] : '',
			'min'   => isset( $c['min'] ) ? $c['min'] : 50,
			'from'  => isset( $c['from'] ) ? substr( (string) $c['from'], 0, 10 ) : '',
			'until' => isset( $c['until'] ) ? substr( (string) $c['until'], 0, 10 ) : '',
			'days'  => isset( $c['days'] ) ? $c['days'] : 0,
		);
	}
	return if_sanitize_rules(
		array(
			'match'   => isset( $s['cond_match'] ) ? $s['cond_match'] : 'all',
			'display' => isset( $s['cond_display'] ) ? $s['cond_display'] : 'locked',
			'conds'   => $conds,
		)
	);
}

/* ---------- Évaluation ---------- */

/** Texte d'une condition (pour l'apprenant et le formateur). */
function if_cond_text( $c, $titles, $user_id = 0, $notion_id = 0 ) {
	$t = function ( $key ) use ( $titles ) {
		return isset( $titles[ $key ] ) ? '« ' . $titles[ $key ] . ' »' : __( 'un contenu supprimé', 'catalogue-formations' );
	};
	switch ( $c['type'] ) {
		case 'done':
			return sprintf( __( 'terminer %s', 'catalogue-formations' ), $t( $c['key'] ) );
		case 'score':
			return sprintf( __( 'obtenir au moins %1$d %% à %2$s', 'catalogue-formations' ), $c['min'], $t( $c['key'] ) );
		case 'date':
			if ( $c['from'] && $c['until'] ) {
				return sprintf( __( 'disponible du %1$s au %2$s', 'catalogue-formations' ), if_date( $c['from'] ), if_date( $c['until'] ) );
			}
			return $c['from'] ? sprintf( __( 'disponible à partir du %s', 'catalogue-formations' ), if_date( $c['from'] ) ) : sprintf( __( 'disponible jusqu\'au %s', 'catalogue-formations' ), if_date( $c['until'] ) );
		case 'delay':
			$when = '';
			if ( $user_id ) {
				$a = if_user_access( $user_id, $notion_id );
				if ( $a['row'] && $a['row']->date_start ) {
					$when = ' (' . if_date( gmdate( 'Y-m-d', strtotime( $a['row']->date_start . ' +' . (int) $c['days'] . ' days' ) ) ) . ')';
				}
			}
			return sprintf( _n( 'disponible %d jour après le début de l\'accès', 'disponible %d jours après le début de l\'accès', $c['days'], 'catalogue-formations' ), $c['days'] ) . $when;
	}
	return '';
}

function if_cond_met( $c, $user_id, $notion_id, $by_key, $completed, $subs ) {
	$today = if_today();
	switch ( $c['type'] ) {
		case 'done':
			return isset( $by_key[ $c['key'] ] ) && if_item_done( $by_key[ $c['key'] ], $completed, $subs );
		case 'score':
			$row = if_progress_row( $user_id, $notion_id, $c['key'] );
			if ( ! $row ) {
				return false;
			}
			$pct = null !== $row->max_score && (int) $row->max_score > 0 ? 100 * (int) $row->score / (int) $row->max_score : ( (int) $row->passed ? 100 : 0 );
			return $pct >= $c['min'];
		case 'date':
			return ( ! $c['from'] || $today >= $c['from'] ) && ( ! $c['until'] || $today <= $c['until'] );
		case 'delay':
			$a = if_user_access( $user_id, $notion_id );
			if ( ! $a['row'] || ! $a['row']->date_start ) {
				return true;
			}
			return $today >= gmdate( 'Y-m-d', strtotime( $a['row']->date_start . ' +' . (int) $c['days'] . ' days' ) );
	}
	return true;
}

/**
 * Pourquoi un contenu n'est-il pas accessible ?
 * Retourne null (accessible) ou array( 'display' => 'locked'|'hidden', 'reasons' => array( texte… ), 'link' => item|null ).
 */
function if_item_restriction( $user_id, $notion_id, $key ) {
	$all = if_get_items( $notion_id, true );
	$by  = array();
	foreach ( $all as $it ) {
		$by[ $it['key'] ] = $it;
	}
	if ( ! isset( $by[ $key ] ) ) {
		return null;
	}
	$item = $by[ $key ];
	if ( $item['hidden'] ) {
		return array( 'display' => 'hidden', 'reasons' => array(), 'link' => null );
	}
	$completed = if_completed_keys( $user_id, $notion_id );
	$subs      = if_user_submissions( $user_id, $notion_id );

	// 1. Parcours dans l'ordre : le contenu précédent (non « toujours accessible ») doit être terminé.
	if ( if_is_sequential( $notion_id ) && ! $item['free'] ) {
		foreach ( $all as $it ) {
			if ( $it['key'] === $key ) {
				break;
			}
			if ( ! $it['free'] && ! $it['hidden'] && ! if_item_done( $it, $completed, $subs ) ) {
				return array( 'display' => 'locked', 'reasons' => array( sprintf( __( 'terminer %s', 'catalogue-formations' ), '« ' . $it['title'] . ' »' ) ), 'link' => $it );
			}
		}
	}

	// 2. Conditions du contenu.
	$rules = $item['rules'];
	if ( empty( $rules['conds'] ) ) {
		return null;
	}
	$titles  = wp_list_pluck( $all, 'title', 'key' );
	$missing = array();
	$any_ok  = false;
	foreach ( $rules['conds'] as $c ) {
		if ( if_cond_met( $c, $user_id, $notion_id, $by, $completed, $subs ) ) {
			$any_ok = true;
		} else {
			$missing[] = if_cond_text( $c, $titles, $user_id, $notion_id );
		}
	}
	$ok = 'any' === $rules['match'] ? $any_ok : ! $missing;
	if ( $ok ) {
		return null;
	}
	$first = null;
	foreach ( $rules['conds'] as $c ) {
		if ( in_array( $c['type'], array( 'done', 'score' ), true ) && isset( $by[ $c['key'] ] ) ) {
			$first = $by[ $c['key'] ];
			break;
		}
	}
	return array(
		'display' => $rules['display'],
		'reasons' => $missing,
		'match'   => $rules['match'],
		'link'    => $first,
	);
}

/** Vrai si l'utilisateur ne peut pas encore utiliser ce contenu (les formateurs peuvent toujours). */
function if_item_locked( $user_id, $notion_id, $key ) {
	if ( user_can( $user_id, 'edit_post', $notion_id ) ) {
		return false;
	}
	return null !== if_item_restriction( $user_id, $notion_id, $key );
}

/** Ancien nom gardé pour compatibilité. */
function if_item_blocker( $user_id, $notion_id, $key ) {
	return if_item_locked( $user_id, $notion_id, $key );
}

/** Clé du contenu suivant s'il vient d'être débloqué (pour proposer « Continuer »). */
function if_next_unlocked( $user_id, $notion_id, $after_key ) {
	$seen = false;
	foreach ( if_get_items( $notion_id ) as $it ) {
		if ( $seen ) {
			return if_item_locked( $user_id, $notion_id, $it['key'] ) ? '' : ( if_is_sequential( $notion_id ) || ! empty( $it['rules']['conds'] ) ? $it['key'] : '' );
		}
		if ( $it['key'] === $after_key ) {
			$seen = true;
		}
	}
	return '';
}

/* ---------- Affichage ---------- */

function if_join_reasons( $reasons, $match ) {
	$sep = 'any' === $match ? ' ' . __( 'ou', 'catalogue-formations' ) . ' ' : ' ; ';
	return implode( $sep, $reasons );
}

/** Contenu bloqué (cadenas + ce qu'il faut faire). */
function if_render_restricted( $type, $title, $r, $wrapper ) {
	$icons = array( 'video' => '🎬', 'pdf' => '📄', 'exercise' => '✏️', 'h5p' => '🧩', 'quiz' => '❓' );
	$open  = $wrapper ? '<section ' . str_replace( 'if-item ', 'if-item if-item--locked if-item--sequence ', $wrapper ) . '>' : '<section class="if-item if-item--locked if-item--sequence">';
	$text  = ucfirst( if_join_reasons( $r['reasons'], isset( $r['match'] ) ? $r['match'] : 'all' ) ) . '.';
	$link  = $r['link'] ? ' <a href="#' . esc_attr( $r['link']['key'] ) . '">' . esc_html__( 'Y aller', 'catalogue-formations' ) . ' ↑</a>' : '';
	return $open . '<h3>🔒 ' . esc_html( ( isset( $icons[ $type ] ) ? $icons[ $type ] . ' ' : '' ) . $title ) . '</h3><p>' . esc_html__( 'Pour débloquer :', 'catalogue-formations' ) . ' ' . esc_html( $text ) . $link . '</p></section>';
}

/** Bandeau pour le formateur : rappel de ce que voit l'apprenant. */
function if_render_staff_note( $notion_id, $key ) {
	$all = if_get_items( $notion_id, true );
	foreach ( $all as $it ) {
		if ( $it['key'] !== $key ) {
			continue;
		}
		$notes = array();
		if ( $it['hidden'] ) {
			$notes[] = '👁 ' . __( 'Masqué aux apprenants', 'catalogue-formations' );
		}
		if ( ! empty( $it['rules']['conds'] ) ) {
			$titles  = wp_list_pluck( $all, 'title', 'key' );
			$txt     = array_map( function ( $c ) use ( $titles ) { return if_cond_text( $c, $titles ); }, $it['rules']['conds'] );
			$notes[] = '🔒 ' . ( 'hidden' === $it['rules']['display'] ? __( 'Caché tant que :', 'catalogue-formations' ) : __( 'Bloqué tant que :', 'catalogue-formations' ) ) . ' ' . if_join_reasons( $txt, $it['rules']['match'] );
		}
		if ( if_is_sequential( $notion_id ) && ! $it['free'] ) {
			$notes[] = '➡ ' . __( 'Parcours dans l\'ordre', 'catalogue-formations' );
		}
		return $notes ? '<p class="cf-staff-note">' . esc_html( implode( ' · ', $notes ) ) . ' <small>' . esc_html__( '(visible seulement par les formateurs)', 'catalogue-formations' ) . '</small></p>' : '';
	}
	return '';
}

/* ---------- Réglage « Parcours » dans l'éditeur ---------- */

function if_sequence_metabox() {
	add_meta_box( 'if_sequence_box', __( 'Parcours', 'catalogue-formations' ), 'if_sequence_metabox_render', 'if_notion', 'side', 'high' );
}

function if_sequence_metabox_render( $post ) {
	wp_nonce_field( 'if_sequence_' . $post->ID, 'if_sequence_nonce' );
	echo '<label><input type="checkbox" name="if_sequential" value="1" ' . checked( if_is_sequential( $post->ID ), true, false ) . '> <strong>' . esc_html__( 'Parcours dans l\'ordre', 'catalogue-formations' ) . '</strong></label>';
	echo '<p class="description">' . esc_html__( 'Chaque contenu suivi se débloque quand le précédent est terminé. Sur chaque contenu : « Toujours accessible », « Masqué aux apprenants » et conditions d\'accès (panneau « Restrictions d\'accès » du bloc).', 'catalogue-formations' ) . '</p>';
}

function if_sequence_save( $post_id ) {
	if ( ! isset( $_POST['if_sequence_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['if_sequence_nonce'] ) ), 'if_sequence_' . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! empty( $_POST['if_sequential'] ) ) {
		update_post_meta( $post_id, '_if_sequential', 1 );
	} else {
		delete_post_meta( $post_id, '_if_sequential' );
	}
}
