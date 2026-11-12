<?php
/**
 * Parcours dans l'ordre.
 * - Réglage de la notion « Parcours dans l'ordre » (encadré dans la colonne de droite de l'éditeur).
 * - Chaque contenu suivi peut être « toujours accessible » (exception).
 * Un contenu est bloqué tant que le contenu suivi précédent (non « toujours accessible ») n'est pas terminé.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes_if_notion', 'if_sequence_metabox' );
add_action( 'save_post_if_notion', 'if_sequence_save' );

function if_is_sequential( $notion_id ) {
	return (bool) get_post_meta( $notion_id, '_if_sequential', true );
}

/**
 * Élément qui bloque $key (le précédent non terminé), ou null si $key est accessible.
 */
function if_item_blocker( $user_id, $notion_id, $key ) {
	if ( ! if_is_sequential( $notion_id ) ) {
		return null;
	}
	$items     = if_get_items( $notion_id );
	$completed = if_completed_keys( $user_id, $notion_id );
	$subs      = if_user_submissions( $user_id, $notion_id );
	$pending   = null;
	foreach ( $items as $it ) {
		if ( $it['key'] === $key ) {
			return ! empty( $it['free'] ) ? null : $pending;
		}
		if ( ! $pending && empty( $it['free'] ) && ! if_item_done( $it, $completed, $subs ) ) {
			$pending = $it;
		}
	}
	return null;
}

/** Clé du premier contenu encore bloqué qui vient d'être débloqué (pour proposer « Continuer »). */
function if_next_unlocked( $user_id, $notion_id, $after_key ) {
	if ( ! if_is_sequential( $notion_id ) ) {
		return '';
	}
	$seen = false;
	foreach ( if_get_items( $notion_id ) as $it ) {
		if ( $seen && empty( $it['free'] ) ) {
			return null === if_item_blocker( $user_id, $notion_id, $it['key'] ) ? $it['key'] : '';
		}
		if ( $it['key'] === $after_key ) {
			$seen = true;
		}
	}
	return '';
}

/** Rendu d'un contenu bloqué. */
function if_render_blocked( $type, $title, $blocker, $wrapper ) {
	$icons = array( 'video' => '🎬', 'pdf' => '📄', 'exercise' => '✏️', 'h5p' => '🧩' );
	$open  = $wrapper ? '<section ' . str_replace( 'if-item ', 'if-item if-item--locked if-item--sequence ', $wrapper ) . '>' : '<section class="if-item if-item--locked if-item--sequence">';
	$link  = '<a href="#' . esc_attr( $blocker['key'] ) . '">' . esc_html( $blocker['title'] ) . '</a>';
	return $open . '<h3>🔒 ' . esc_html( ( isset( $icons[ $type ] ) ? $icons[ $type ] . ' ' : '' ) . $title ) . '</h3><p>' . sprintf( esc_html__( 'Se débloque quand vous aurez terminé : %s', 'catalogue-formations' ), $link ) . '</p></section>';
}

/* ---------- Réglage dans l'éditeur ---------- */

function if_sequence_metabox() {
	add_meta_box( 'if_sequence_box', __( 'Parcours', 'catalogue-formations' ), 'if_sequence_metabox_render', 'if_notion', 'side', 'high' );
}

function if_sequence_metabox_render( $post ) {
	wp_nonce_field( 'if_sequence_' . $post->ID, 'if_sequence_nonce' );
	echo '<label><input type="checkbox" name="if_sequential" value="1" ' . checked( if_is_sequential( $post->ID ), true, false ) . '> <strong>' . esc_html__( 'Parcours dans l\'ordre', 'catalogue-formations' ) . '</strong></label>';
	echo '<p class="description">' . esc_html__( 'Chaque contenu suivi se débloque quand le précédent est terminé. Exception possible sur un contenu : « Toujours accessible ».', 'catalogue-formations' ) . '</p>';
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
