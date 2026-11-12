<?php
/**
 * Quiz intégré (sans H5P) : QCM une réponse, QCM plusieurs réponses, vrai/faux, réponse courte.
 *
 * - Corrigé automatiquement côté serveur (les bonnes réponses ne sont jamais envoyées au navigateur avant la correction).
 * - 1 point par question ; « plusieurs réponses » : toutes les bonnes et aucune fausse.
 * - Score minimum pour valider, nombre d'essais (0 = illimité), ordre mélangé, affichage de la correction.
 * - Le meilleur score est gardé (table d'avancement) : utilisable dans les conditions « score minimum ».
 * - Dernier essai gardé dans les métadonnées de l'utilisateur (_if_quiz_{notion}_{clé}).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_if_quiz_submit', 'if_quiz_submit' );

/* ---------- Données ---------- */

function if_sanitize_questions( $qs ) {
	if ( is_string( $qs ) ) {
		$qs = json_decode( $qs, true );
	}
	$out = array();
	foreach ( is_array( $qs ) ? $qs : array() as $q ) {
		$type = isset( $q['type'] ) && in_array( $q['type'], array( 'single', 'multiple', 'truefalse', 'short' ), true ) ? $q['type'] : 'single';
		$text = isset( $q['text'] ) ? wp_kses_post( $q['text'] ) : '';
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			continue;
		}
		$item = array(
			'type'        => $type,
			'text'        => $text,
			'explanation' => isset( $q['explanation'] ) ? wp_kses_post( $q['explanation'] ) : '',
		);
		if ( 'truefalse' === $type ) {
			$item['answerTrue'] = ! empty( $q['answerTrue'] );
		} elseif ( 'short' === $type ) {
			$answers = isset( $q['answers'] ) ? ( is_array( $q['answers'] ) ? $q['answers'] : preg_split( '/\r\n|\r|\n/', (string) $q['answers'] ) ) : array();
			$answers = array_values( array_filter( array_map( 'sanitize_text_field', $answers ), 'strlen' ) );
			if ( ! $answers ) {
				continue;
			}
			$item['answers'] = $answers;
		} else {
			$choices = array();
			foreach ( isset( $q['choices'] ) && is_array( $q['choices'] ) ? $q['choices'] : array() as $c ) {
				$t = isset( $c['text'] ) ? sanitize_text_field( $c['text'] ) : '';
				if ( '' !== $t ) {
					$choices[] = array( 'text' => $t, 'correct' => ! empty( $c['correct'] ) );
				}
			}
			if ( count( $choices ) < 2 ) {
				continue;
			}
			$item['choices'] = $choices;
		}
		$out[] = $item;
	}
	return $out;
}

/** Questions depuis le widget Elementor (une ligne par choix, « * » devant les bonnes réponses). */
function if_questions_from_elementor( $rows ) {
	$qs = array();
	foreach ( is_array( $rows ) ? $rows : array() as $r ) {
		$type = isset( $r['qtype'] ) ? $r['qtype'] : 'single';
		$q    = array( 'type' => $type, 'text' => isset( $r['question'] ) ? $r['question'] : '', 'explanation' => isset( $r['explanation'] ) ? $r['explanation'] : '' );
		if ( 'truefalse' === $type ) {
			$q['answerTrue'] = ! isset( $r['tf'] ) || 'true' === $r['tf'];
		} elseif ( 'short' === $type ) {
			$q['answers'] = isset( $r['answers'] ) ? $r['answers'] : '';
		} else {
			$q['choices'] = array();
			foreach ( preg_split( '/\r\n|\r|\n/', isset( $r['choices'] ) ? (string) $r['choices'] : '' ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$ok             = '*' === substr( $line, 0, 1 );
				$q['choices'][] = array( 'text' => trim( $ok ? substr( $line, 1 ) : $line ), 'correct' => $ok );
			}
		}
		$qs[] = $q;
	}
	return $qs;
}

function if_quiz_meta_key( $notion_id, $key ) {
	return '_if_quiz_' . (int) $notion_id . '_' . sanitize_key( $key );
}

function if_quiz_state( $user_id, $notion_id, $key ) {
	$s = get_user_meta( $user_id, if_quiz_meta_key( $notion_id, $key ), true );
	return is_array( $s ) ? $s + array( 'attempts' => 0, 'last' => null ) : array( 'attempts' => 0, 'last' => null );
}

/* ---------- Correction ---------- */

function if_quiz_normalize( $s ) {
	$s = strtolower( remove_accents( wp_strip_all_tags( (string) $s ) ) );
	$s = preg_replace( '/[\s’\']+/u', ' ', $s );
	return trim( preg_replace( '/[\s.!?;:,]+$/', '', $s ) );
}

/** Corrige une question. $given : index choisi, tableau d'index, 'true'/'false' ou texte. */
function if_quiz_check( $q, $given ) {
	switch ( $q['type'] ) {
		case 'truefalse':
			return null !== $given && '' !== $given && ( 'true' === $given ) === (bool) $q['answerTrue'];
		case 'short':
			$g = if_quiz_normalize( $given );
			foreach ( $q['answers'] as $a ) {
				if ( '' !== $g && if_quiz_normalize( $a ) === $g ) {
					return true;
				}
			}
			return false;
		case 'multiple':
			$given = array_map( 'intval', (array) $given );
			sort( $given );
			$good = array();
			foreach ( $q['choices'] as $i => $c ) {
				if ( $c['correct'] ) {
					$good[] = $i;
				}
			}
			return $given === $good;
		default:
			return null !== $given && '' !== $given && ! empty( $q['choices'][ (int) $given ]['correct'] );
	}
}

/** Réception d'un essai. */
function if_quiz_submit() {
	$notion_id = isset( $_POST['notion_id'] ) ? absint( $_POST['notion_id'] ) : 0;
	$key       = isset( $_POST['quiz_key'] ) ? sanitize_key( wp_unslash( $_POST['quiz_key'] ) ) : '';
	check_admin_referer( 'if_quiz_' . $notion_id . '_' . $key );
	$user = get_current_user_id();
	$back = get_permalink( $notion_id );
	$item = if_find_item( $notion_id, $key, 'quiz' );
	if ( ! $user || ! $item || ! if_user_can_view( $user, $notion_id ) || if_item_locked( $user, $notion_id, $key ) ) {
		wp_safe_redirect( add_query_arg( 'if_msg', 'noaccess', $back ) );
		exit;
	}
	$state = if_quiz_state( $user, $notion_id, $key );
	$max_attempts = isset( $item['attempts'] ) ? (int) $item['attempts'] : 0;
	if ( $max_attempts && $state['attempts'] >= $max_attempts && ! user_can( $user, 'edit_post', $notion_id ) ) {
		wp_safe_redirect( add_query_arg( 'if_msg', 'quizmax', $back ) . '#' . $key );
		exit;
	}
	$raw     = isset( $_POST['q'] ) && is_array( $_POST['q'] ) ? wp_unslash( $_POST['q'] ) : array(); // phpcs:ignore
	$answers = array();
	$score   = 0;
	foreach ( $item['questions'] as $i => $q ) {
		$given = isset( $raw[ $i ] ) ? $raw[ $i ] : null;
		$given = is_array( $given ) ? array_map( 'sanitize_text_field', $given ) : ( null === $given ? null : sanitize_text_field( $given ) );
		$ok    = if_quiz_check( $q, $given );
		$score += $ok ? 1 : 0;
		$answers[ $i ] = array( 'given' => $given, 'ok' => $ok );
	}
	$max = count( $item['questions'] );
	$state['attempts']++;
	$state['last'] = array( 'answers' => $answers, 'score' => $score, 'max' => $max, 'date' => if_now() );
	update_user_meta( $user, if_quiz_meta_key( $notion_id, $key ), $state );
	if_record_h5p_score( $user, $notion_id, $item, $score, $max ); // même règle : meilleur score gardé, validé au seuil.
	wp_safe_redirect( add_query_arg( 'if_msg', 'quizdone', $back ) . '#' . $key );
	exit;
}

/* ---------- Affichage ---------- */

function if_render_quiz( $notion_id, $item ) {
	$user  = get_current_user_id();
	$key   = $item['key'];
	$qs    = isset( $item['questions'] ) ? $item['questions'] : array();
	$pass  = isset( $item['pass'] ) ? (int) $item['pass'] : 0;
	$max_a = isset( $item['attempts'] ) ? (int) $item['attempts'] : 0;
	$show  = isset( $item['show'] ) ? $item['show'] : 'always';
	if ( ! $qs ) {
		return '<p class="if-empty-item">' . esc_html__( 'Ce quiz n\'a pas encore de question.', 'catalogue-formations' ) . '</p>';
	}
	$state  = if_quiz_state( $user, $notion_id, $key );
	$row    = function_exists( 'if_progress_row' ) ? if_progress_row( $user, $notion_id, $key ) : null;
	$passed = $row && (int) $row->passed;
	$left   = $max_a ? max( 0, $max_a - (int) $state['attempts'] ) : -1;
	$staff  = user_can( $user, 'edit_post', $notion_id );

	ob_start();
	echo '<div class="cf-quiz">';
	// Résumé.
	$info = array( sprintf( _n( '%d question', '%d questions', count( $qs ), 'catalogue-formations' ), count( $qs ) ) );
	if ( $pass ) {
		$info[] = sprintf( __( 'validé à partir de %d %%', 'catalogue-formations' ), $pass );
	}
	if ( $max_a ) {
		$info[] = sprintf( _n( '%d essai', '%d essais', $max_a, 'catalogue-formations' ), $max_a );
	}
	echo '<p class="cf-quiz__info">' . esc_html( implode( ' · ', $info ) ) . '</p>';
	if ( $row ) {
		$best = sprintf( '%d / %d', $row->score, $row->max_score );
		echo '<p class="cf-quiz__status' . ( $passed ? ' is-passed' : '' ) . '">' . esc_html( $passed ? '✔ ' . sprintf( __( 'Quiz réussi — meilleur score : %s', 'catalogue-formations' ), $best ) : sprintf( __( 'Meilleur score : %1$s. Il faut au moins %2$d %% pour valider.', 'catalogue-formations' ), $best, $pass ) ) . '</p>';
	}

	// Correction du dernier essai.
	$last = $state['last'];
	if ( $last && ( 'always' === $show || ( 'passed' === $show && $passed ) || $staff ) ) {
		echo '<details class="cf-quiz__review"' . ( isset( $_GET['if_msg'] ) && 'quizdone' === $_GET['if_msg'] ? ' open' : '' ) . '><summary>' . esc_html( sprintf( __( 'Correction de mon dernier essai (%1$d / %2$d)', 'catalogue-formations' ), $last['score'], $last['max'] ) ) . '</summary><ol>'; // phpcs:ignore
		foreach ( $qs as $i => $q ) {
			$a  = isset( $last['answers'][ $i ] ) ? $last['answers'][ $i ] : array( 'given' => null, 'ok' => false );
			echo '<li class="' . ( $a['ok'] ? 'is-ok' : 'is-ko' ) . '"><div class="cf-quiz__q">' . wp_kses_post( $q['text'] ) . '</div>';
			echo '<p>' . ( $a['ok'] ? '✔ ' . esc_html__( 'Bonne réponse', 'catalogue-formations' ) : '✘ ' . esc_html__( 'Réponse attendue :', 'catalogue-formations' ) . ' <strong>' . esc_html( if_quiz_expected( $q ) ) . '</strong>' ) . '</p>';
			if ( ! $a['ok'] && null !== $a['given'] && '' !== $a['given'] && array() !== $a['given'] ) {
				echo '<p class="cf-quiz__given">' . esc_html__( 'Votre réponse :', 'catalogue-formations' ) . ' ' . esc_html( if_quiz_given_text( $q, $a['given'] ) ) . '</p>';
			}
			if ( '' !== trim( wp_strip_all_tags( $q['explanation'] ) ) ) {
				echo '<div class="cf-quiz__expl">💡 ' . wp_kses_post( $q['explanation'] ) . '</div>';
			}
			echo '</li>';
		}
		echo '</ol></details>';
	} elseif ( $last ) {
		echo '<p>' . esc_html( sprintf( __( 'Dernier essai : %1$d / %2$d.', 'catalogue-formations' ), $last['score'], $last['max'] ) ) . '</p>';
	}

	// Formulaire.
	if ( 0 === $left && ! $staff ) {
		echo '<p class="cf-quiz__info">' . esc_html__( 'Vous avez utilisé tous vos essais.', 'catalogue-formations' ) . '</p>';
	} else {
		$order = array_keys( $qs );
		if ( ! empty( $item['shuffle'] ) ) {
			shuffle( $order );
		}
		$label = $state['attempts'] ? __( 'Recommencer le quiz', 'catalogue-formations' ) : __( 'Commencer le quiz', 'catalogue-formations' );
		echo '<details class="cf-quiz__form"' . ( $state['attempts'] ? '' : ' open' ) . '><summary>' . esc_html( $label ) . ( $left > 0 ? ' <small>(' . esc_html( sprintf( _n( '%d essai restant', '%d essais restants', $left, 'catalogue-formations' ), $left ) ) . ')</small>' : '' ) . '</summary>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="if_quiz_submit"><input type="hidden" name="notion_id" value="' . (int) $notion_id . '"><input type="hidden" name="quiz_key" value="' . esc_attr( $key ) . '">';
		wp_nonce_field( 'if_quiz_' . $notion_id . '_' . $key );
		echo '<ol class="cf-quiz__list">';
		foreach ( $order as $i ) {
			$q    = $qs[ $i ];
			$name = 'q[' . $i . ']';
			$id   = 'cfq-' . $key . '-' . $i;
			echo '<li><fieldset><legend class="cf-quiz__q">' . wp_kses_post( $q['text'] ) . ( 'multiple' === $q['type'] ? ' <small>' . esc_html__( '(plusieurs réponses possibles)', 'catalogue-formations' ) . '</small>' : '' ) . '</legend>';
			if ( 'truefalse' === $q['type'] ) {
				foreach ( array( 'true' => __( 'Vrai', 'catalogue-formations' ), 'false' => __( 'Faux', 'catalogue-formations' ) ) as $v => $l ) {
					echo '<label class="cf-quiz__choice"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"> ' . esc_html( $l ) . '</label>';
				}
			} elseif ( 'short' === $q['type'] ) {
				echo '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Votre réponse', 'catalogue-formations' ) . '</label><input class="cf-quiz__short" id="' . esc_attr( $id ) . '" type="text" name="' . esc_attr( $name ) . '" autocomplete="off">';
			} else {
				$ci = array_keys( $q['choices'] );
				if ( ! empty( $item['shuffle'] ) ) {
					shuffle( $ci );
				}
				foreach ( $ci as $c ) {
					$multi = 'multiple' === $q['type'];
					echo '<label class="cf-quiz__choice"><input type="' . ( $multi ? 'checkbox' : 'radio' ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . (int) $c . '"> ' . esc_html( $q['choices'][ $c ]['text'] ) . '</label>';
				}
			}
			echo '</fieldset></li>';
		}
		echo '</ol><button type="submit" class="if-btn">' . esc_html__( 'Valider mes réponses', 'catalogue-formations' ) . '</button></form></details>';
	}
	echo '</div>';
	return ob_get_clean();
}

function if_quiz_expected( $q ) {
	if ( 'truefalse' === $q['type'] ) {
		return $q['answerTrue'] ? __( 'Vrai', 'catalogue-formations' ) : __( 'Faux', 'catalogue-formations' );
	}
	if ( 'short' === $q['type'] ) {
		return $q['answers'][0];
	}
	$good = array();
	foreach ( $q['choices'] as $c ) {
		if ( $c['correct'] ) {
			$good[] = $c['text'];
		}
	}
	return implode( ' + ', $good );
}

function if_quiz_given_text( $q, $given ) {
	if ( 'truefalse' === $q['type'] ) {
		return 'true' === $given ? __( 'Vrai', 'catalogue-formations' ) : __( 'Faux', 'catalogue-formations' );
	}
	if ( 'short' === $q['type'] ) {
		return (string) $given;
	}
	$t = array();
	foreach ( (array) $given as $g ) {
		if ( isset( $q['choices'][ (int) $g ] ) ) {
			$t[] = $q['choices'][ (int) $g ]['text'];
		}
	}
	return implode( ' + ', $t );
}
