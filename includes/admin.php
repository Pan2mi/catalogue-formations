<?php
/**
 * Pages d'administration : Accès, Suivi, Corrections, Réglages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'if_admin_menu' );
add_action( 'admin_post_if_admin_grant', 'if_admin_handle_grant' );
add_action( 'admin_post_if_admin_refuse', 'if_admin_handle_refuse' );
add_action( 'admin_post_if_admin_delete', 'if_admin_handle_delete' );
add_action( 'admin_post_if_admin_correct', 'if_admin_handle_correct' );
add_action( 'admin_post_if_admin_settings', 'if_admin_handle_settings' );

/** Droit nécessaire pour gérer la formation (administrateurs et éditeurs par défaut). */
function if_cap() {
	return apply_filters( 'if_manage_cap', 'edit_others_posts' );
}

function if_admin_url( $page, $args = array() ) {
	return add_query_arg( array_merge( array( 'post_type' => 'if_notion', 'page' => $page ), $args ), admin_url( 'edit.php' ) );
}

function if_count( $table, $status ) {
	global $wpdb;
	$t = if_table( $table );
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = %s", $status ) ); // phpcs:ignore
}

function if_bubble( $n ) {
	return $n ? ' <span class="awaiting-mod"><span>' . (int) $n . '</span></span>' : '';
}

function if_admin_menu() {
	$parent = 'edit.php?post_type=if_notion';
	add_submenu_page( $parent, __( 'Accès', 'catalogue-formations' ), __( 'Accès', 'catalogue-formations' ) . if_bubble( if_count( 'access', 'pending' ) ), if_cap(), 'if-access', 'if_page_access' );
	add_submenu_page( $parent, __( 'Suivi des apprenants', 'catalogue-formations' ), __( 'Suivi', 'catalogue-formations' ), if_cap(), 'if-progress', 'if_page_progress' );
	add_submenu_page( $parent, __( 'Corrections', 'catalogue-formations' ), __( 'Corrections', 'catalogue-formations' ) . if_bubble( if_count( 'submissions', 'submitted' ) ), if_cap(), 'if-corrections', 'if_page_corrections' );
	add_submenu_page( $parent, __( 'Réglages', 'catalogue-formations' ), __( 'Réglages', 'catalogue-formations' ), 'manage_options', 'if-settings', 'if_page_settings' );
}

function if_admin_notice() {
	if ( empty( $_GET['if_done'] ) ) { // phpcs:ignore
		return;
	}
	$msgs = array(
		'granted'   => __( 'Accès enregistré.', 'catalogue-formations' ),
		'refused'   => __( 'Demande refusée.', 'catalogue-formations' ),
		'deleted'   => __( 'Accès supprimé.', 'catalogue-formations' ),
		'corrected' => __( 'Correction envoyée à l\'apprenant.', 'catalogue-formations' ),
		'saved'     => __( 'Réglages enregistrés.', 'catalogue-formations' ),
		'dates'     => __( 'Erreur : la date de fin doit être après la date de début.', 'catalogue-formations' ),
		'missing'   => __( 'Erreur : choisissez un utilisateur, une notion et les deux dates.', 'catalogue-formations' ),
	);
	$k     = sanitize_key( wp_unslash( $_GET['if_done'] ) ); // phpcs:ignore
	$class = in_array( $k, array( 'dates', 'missing' ), true ) ? 'notice-error' : 'notice-success';
	if ( isset( $msgs[ $k ] ) ) {
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msgs[ $k ] ) . '</p></div>';
	}
}

function if_notion_options( $selected = 0 ) {
	$notions = get_posts( array( 'post_type' => 'if_notion', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => array( 'publish', 'draft', 'private' ) ) );
	$html    = '';
	foreach ( $notions as $n ) {
		$html .= '<option value="' . (int) $n->ID . '" ' . selected( $selected, $n->ID, false ) . '>' . esc_html( $n->post_title ) . '</option>';
	}
	return $html;
}

function if_user_name( $user_id ) {
	$u = get_userdata( $user_id );
	return $u ? $u->display_name . ' (' . $u->user_email . ')' : '#' . $user_id;
}

/* ================= Page « Accès » ================= */

function if_page_access() {
	if ( ! current_user_can( if_cap() ) ) {
		return;
	}
	$f_notion = isset( $_GET['notion'] ) ? absint( $_GET['notion'] ) : 0; // phpcs:ignore
	$f_user   = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0; // phpcs:ignore
	$edit_id  = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore
	$edit     = $edit_id ? if_get_access( $edit_id ) : null;
	$pending  = if_get_access_rows( array( 'status' => 'pending' ) );
	?>
	<div class="wrap if-admin">
		<h1><?php esc_html_e( 'Accès aux notions', 'catalogue-formations' ); ?></h1>
		<?php if_admin_notice(); ?>

		<h2><?php esc_html_e( '1. Demandes en attente', 'catalogue-formations' ); ?> (<?php echo count( $pending ); ?>)</h2>
		<?php if ( ! $pending ) : ?>
			<p><?php esc_html_e( 'Aucune demande en attente.', 'catalogue-formations' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Apprenant', 'catalogue-formations' ); ?></th>
					<th><?php esc_html_e( 'Notion', 'catalogue-formations' ); ?></th>
					<th><?php esc_html_e( 'Demandé le', 'catalogue-formations' ); ?></th>
					<th><?php esc_html_e( 'Message', 'catalogue-formations' ); ?></th>
					<th><?php esc_html_e( 'Valider avec les dates', 'catalogue-formations' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $pending as $r ) : ?>
					<tr>
						<td><?php echo esc_html( if_user_name( $r->user_id ) ); ?></td>
						<td><?php echo esc_html( get_the_title( $r->notion_id ) ); ?></td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $r->requested_at ) ); ?></td>
						<td><?php echo esc_html( $r->message ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="if-inline">
								<input type="hidden" name="action" value="if_admin_grant">
								<input type="hidden" name="access_id" value="<?php echo (int) $r->id; ?>">
								<input type="hidden" name="user_id" value="<?php echo (int) $r->user_id; ?>">
								<input type="hidden" name="notion_id" value="<?php echo (int) $r->notion_id; ?>">
								<?php wp_nonce_field( 'if_admin_grant' ); ?>
								<label><?php esc_html_e( 'Du', 'catalogue-formations' ); ?> <input type="date" name="date_start" required value="<?php echo esc_attr( if_today() ); ?>"></label>
								<label><?php esc_html_e( 'au', 'catalogue-formations' ); ?> <input type="date" name="date_end" required></label>
								<button class="button button-primary"><?php esc_html_e( 'Valider', 'catalogue-formations' ); ?></button>
							</form>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="if_admin_refuse">
								<input type="hidden" name="access_id" value="<?php echo (int) $r->id; ?>">
								<?php wp_nonce_field( 'if_admin_refuse' ); ?>
								<button class="button"><?php esc_html_e( 'Refuser', 'catalogue-formations' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 id="if-grant"><?php echo $edit ? esc_html__( '2. Modifier un accès', 'catalogue-formations' ) : esc_html__( '2. Donner un accès directement', 'catalogue-formations' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="if-card-admin">
			<input type="hidden" name="action" value="if_admin_grant">
			<?php wp_nonce_field( 'if_admin_grant' ); ?>
			<?php if ( $edit ) : ?>
				<input type="hidden" name="access_id" value="<?php echo (int) $edit->id; ?>">
				<input type="hidden" name="user_id" value="<?php echo (int) $edit->user_id; ?>">
				<input type="hidden" name="notion_id" value="<?php echo (int) $edit->notion_id; ?>">
				<p><strong><?php echo esc_html( if_user_name( $edit->user_id ) ); ?></strong> — <?php echo esc_html( get_the_title( $edit->notion_id ) ); ?></p>
			<?php else : ?>
				<p>
					<label><?php esc_html_e( 'Utilisateur', 'catalogue-formations' ); ?><br>
					<?php wp_dropdown_users( array( 'name' => 'user_id', 'show_option_none' => __( '— Choisir —', 'catalogue-formations' ), 'option_none_value' => 0, 'show' => 'display_name_with_login' ) ); ?></label>
				</p>
				<p>
					<label><?php esc_html_e( 'Notion', 'catalogue-formations' ); ?><br>
					<select name="notion_id"><option value="0"><?php esc_html_e( '— Choisir —', 'catalogue-formations' ); ?></option><?php echo if_notion_options(); // phpcs:ignore ?></select></label>
				</p>
			<?php endif; ?>
			<p>
				<label><?php esc_html_e( 'Du', 'catalogue-formations' ); ?> <input type="date" name="date_start" required value="<?php echo esc_attr( $edit && $edit->date_start ? $edit->date_start : if_today() ); ?>"></label>
				<label><?php esc_html_e( 'au', 'catalogue-formations' ); ?> <input type="date" name="date_end" required value="<?php echo esc_attr( $edit && $edit->date_end ? $edit->date_end : '' ); ?>"></label>
			</p>
			<p>
				<button class="button button-primary"><?php echo $edit ? esc_html__( 'Enregistrer les dates', 'catalogue-formations' ) : esc_html__( 'Donner l\'accès', 'catalogue-formations' ); ?></button>
				<?php if ( $edit ) : ?>
					<a class="button" href="<?php echo esc_url( if_admin_url( 'if-access' ) ); ?>"><?php esc_html_e( 'Annuler', 'catalogue-formations' ); ?></a>
				<?php endif; ?>
			</p>
			<p class="description"><?php esc_html_e( 'Un même utilisateur peut avoir plusieurs périodes pour une notion. L\'apprenant reçoit un e-mail.', 'catalogue-formations' ); ?></p>
		</form>

		<h2><?php esc_html_e( '3. Tous les accès', 'catalogue-formations' ); ?></h2>
		<form method="get" class="if-inline">
			<input type="hidden" name="post_type" value="if_notion">
			<input type="hidden" name="page" value="if-access">
			<select name="notion"><option value="0"><?php esc_html_e( 'Toutes les notions', 'catalogue-formations' ); ?></option><?php echo if_notion_options( $f_notion ); // phpcs:ignore ?></select>
			<?php wp_dropdown_users( array( 'name' => 'user', 'selected' => $f_user, 'show_option_all' => __( 'Tous les utilisateurs', 'catalogue-formations' ) ) ); ?>
			<button class="button"><?php esc_html_e( 'Filtrer', 'catalogue-formations' ); ?></button>
		</form>
		<?php
		$rows = array_filter(
			if_get_access_rows( array( 'notion_id' => $f_notion, 'user_id' => $f_user ) ),
			function ( $r ) {
				return 'pending' !== $r->status;
			}
		);
		?>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Apprenant', 'catalogue-formations' ); ?></th>
				<th><?php esc_html_e( 'Notion', 'catalogue-formations' ); ?></th>
				<th><?php esc_html_e( 'Du', 'catalogue-formations' ); ?></th>
				<th><?php esc_html_e( 'Au', 'catalogue-formations' ); ?></th>
				<th><?php esc_html_e( 'État', 'catalogue-formations' ); ?></th>
				<th><?php esc_html_e( 'Avancement', 'catalogue-formations' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'catalogue-formations' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'Aucun accès.', 'catalogue-formations' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<?php $p = if_progress( $r->user_id, $r->notion_id ); ?>
				<tr>
					<td><?php echo esc_html( if_user_name( $r->user_id ) ); ?></td>
					<td><?php echo esc_html( get_the_title( $r->notion_id ) ); ?></td>
					<td><?php echo esc_html( if_date( $r->date_start ) ); ?></td>
					<td><?php echo esc_html( if_date( $r->date_end ) ); ?></td>
					<td><span class="if-state if-state--<?php echo esc_attr( if_row_state( $r ) ); ?>"><?php echo esc_html( if_state_label( if_row_state( $r ) ) ); ?></span></td>
					<td><?php echo (int) $p['percent']; ?> %</td>
					<td>
						<a href="<?php echo esc_url( if_admin_url( 'if-access', array( 'edit' => $r->id ) ) . '#if-grant' ); ?>"><?php esc_html_e( 'Modifier les dates', 'catalogue-formations' ); ?></a> |
						<a href="<?php echo esc_url( if_admin_url( 'if-progress', array( 'user' => $r->user_id, 'notion' => $r->notion_id ) ) ); ?>"><?php esc_html_e( 'Détail', 'catalogue-formations' ); ?></a> |
						<a class="if-danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'if_admin_delete', 'access_id' => $r->id ), admin_url( 'admin-post.php' ) ), 'if_admin_delete' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Supprimer cet accès ?', 'catalogue-formations' ) ); ?>');"><?php esc_html_e( 'Supprimer', 'catalogue-formations' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function if_admin_handle_grant() {
	if ( ! current_user_can( if_cap() ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_admin_grant' );
	$access_id = isset( $_POST['access_id'] ) ? absint( $_POST['access_id'] ) : 0;
	$user_id   = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$notion_id = isset( $_POST['notion_id'] ) ? absint( $_POST['notion_id'] ) : 0;
	$start     = isset( $_POST['date_start'] ) ? sanitize_text_field( wp_unslash( $_POST['date_start'] ) ) : '';
	$end       = isset( $_POST['date_end'] ) ? sanitize_text_field( wp_unslash( $_POST['date_end'] ) ) : '';
	$valid     = '/^\d{4}-\d{2}-\d{2}$/';

	if ( ! $user_id || ! $notion_id || ! preg_match( $valid, $start ) || ! preg_match( $valid, $end ) ) {
		wp_safe_redirect( if_admin_url( 'if-access', array( 'if_done' => 'missing' ) ) );
		exit;
	}
	$res = if_grant_access( $user_id, $notion_id, $start, $end, $access_id );
	wp_safe_redirect( if_admin_url( 'if-access', array( 'if_done' => is_wp_error( $res ) ? 'dates' : 'granted' ) ) );
	exit;
}

function if_admin_handle_refuse() {
	if ( ! current_user_can( if_cap() ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_admin_refuse' );
	if_refuse_access( isset( $_POST['access_id'] ) ? absint( $_POST['access_id'] ) : 0 );
	wp_safe_redirect( if_admin_url( 'if-access', array( 'if_done' => 'refused' ) ) );
	exit;
}

function if_admin_handle_delete() {
	if ( ! current_user_can( if_cap() ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_admin_delete' );
	if_delete_access( isset( $_GET['access_id'] ) ? absint( $_GET['access_id'] ) : 0 );
	wp_safe_redirect( if_admin_url( 'if-access', array( 'if_done' => 'deleted' ) ) );
	exit;
}

/* ================= Page « Suivi » ================= */

function if_page_progress() {
	if ( ! current_user_can( if_cap() ) ) {
		return;
	}
	$f_user   = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0; // phpcs:ignore
	$f_notion = isset( $_GET['notion'] ) ? absint( $_GET['notion'] ) : 0; // phpcs:ignore
	echo '<div class="wrap if-admin"><h1>' . esc_html__( 'Suivi des apprenants', 'catalogue-formations' ) . '</h1>';

	// Détail d'un apprenant sur une notion.
	if ( $f_user && $f_notion ) {
		$completed = if_completed_keys( $f_user, $f_notion );
		$subs      = if_user_submissions( $f_user, $f_notion );
		$p         = if_progress( $f_user, $f_notion );
		echo '<p><a href="' . esc_url( if_admin_url( 'if-progress' ) ) . '">← ' . esc_html__( 'Retour au suivi', 'catalogue-formations' ) . '</a></p>';
		echo '<h2>' . esc_html( if_user_name( $f_user ) . ' — ' . get_the_title( $f_notion ) ) . '</h2>';
		echo '<p><strong>' . esc_html( sprintf( __( 'Avancement : %1$d %% (%2$d / %3$d)', 'catalogue-formations' ), $p['percent'], $p['done'], $p['total'] ) ) . '</strong></p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Élément', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'État', 'catalogue-formations' ) . '</th></tr></thead><tbody>';
		foreach ( if_get_items( $f_notion ) as $it ) {
			$done = if_item_done( $it, $completed, $subs );
			$etat = $done ? '✔ ' . __( 'Terminé', 'catalogue-formations' ) : '○ ' . __( 'Pas encore', 'catalogue-formations' );
			if ( 'h5p' === $it['type'] && ( $row = if_progress_row( $f_user, $f_notion, $it['key'] ) ) ) { // phpcs:ignore
				$etat = ( (int) $row->passed ? '✔ ' . __( 'Réussie', 'catalogue-formations' ) : '○ ' . __( 'Pas encore validée', 'catalogue-formations' ) ) . ( null !== $row->max_score ? sprintf( ' — %d / %d', $row->score, $row->max_score ) : '' );
			}
			if ( 'exercise' === $it['type'] && isset( $subs[ $it['key'] ] ) ) {
				$s    = $subs[ $it['key'] ];
				$etat = 'corrected' === $s->status ? '✅ ' . __( 'Corrigé', 'catalogue-formations' ) : '⏳ <a href="' . esc_url( if_admin_url( 'if-corrections', array( 'submission' => $s->id ) ) ) . '">' . __( 'À corriger', 'catalogue-formations' ) . '</a>';
			}
			echo '<tr><td>' . esc_html( if_type_label( $it['type'] ) ) . '</td><td>' . esc_html( $it['title'] ) . '</td><td>' . wp_kses_post( $etat ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
		return;
	}

	// Vue d'ensemble : une ligne par apprenant, une colonne par notion.
	global $wpdb;
	$t        = if_table( 'access' );
	$user_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM $t WHERE status = 'active'" ); // phpcs:ignore
	$notions  = get_posts( array( 'post_type' => 'if_notion', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );

	if ( ! $user_ids || ! $notions ) {
		echo '<p>' . esc_html__( 'Aucun accès accordé pour le moment.', 'catalogue-formations' ) . '</p></div>';
		return;
	}
	echo '<p class="description">' . esc_html__( 'Pourcentage d\'avancement par apprenant et par notion. Cliquez sur un pourcentage pour voir le détail.', 'catalogue-formations' ) . '</p>';
	echo '<div class="if-scroll"><table class="widefat striped if-matrix"><thead><tr><th>' . esc_html__( 'Apprenant', 'catalogue-formations' ) . '</th>';
	foreach ( $notions as $n ) {
		echo '<th>' . esc_html( $n->post_title ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $user_ids as $uid ) {
		echo '<tr><td>' . esc_html( if_user_name( $uid ) ) . '</td>';
		foreach ( $notions as $n ) {
			$a = if_user_access( $uid, $n->ID );
			if ( in_array( $a['state'], array( 'active', 'upcoming', 'expired' ), true ) ) {
				$p = if_progress( $uid, $n->ID );
				echo '<td><a href="' . esc_url( if_admin_url( 'if-progress', array( 'user' => $uid, 'notion' => $n->ID ) ) ) . '">' . (int) $p['percent'] . ' %</a><br><small class="if-state if-state--' . esc_attr( $a['state'] ) . '">' . esc_html( if_state_label( $a['state'] ) ) . '</small></td>';
			} else {
				echo '<td class="if-empty">—</td>';
			}
		}
		echo '</tr>';
	}
	echo '</tbody></table></div></div>';
}

/* ================= Page « Corrections » ================= */

function if_page_corrections() {
	if ( ! current_user_can( if_cap() ) ) {
		return;
	}
	$sub_id = isset( $_GET['submission'] ) ? absint( $_GET['submission'] ) : 0; // phpcs:ignore
	echo '<div class="wrap if-admin"><h1>' . esc_html__( 'Corrections', 'catalogue-formations' ) . '</h1>';
	if_admin_notice();

	if ( $sub_id && ( $s = if_get_submission( $sub_id ) ) ) { // phpcs:ignore
		$ex = if_find_item( $s->notion_id, $s->exercise_key, 'exercise' );
		echo '<p><a href="' . esc_url( if_admin_url( 'if-corrections' ) ) . '">← ' . esc_html__( 'Retour à la liste', 'catalogue-formations' ) . '</a></p>';
		echo '<h2>' . esc_html( get_the_title( $s->notion_id ) . ' — ' . ( $ex ? $ex['title'] : '' ) ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Apprenant', 'catalogue-formations' ) . ' :</strong> ' . esc_html( if_user_name( $s->user_id ) ) . ' · ' . esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $s->submitted_at ) ) . '</p>';
		if ( $ex ) {
			echo '<div class="if-card-admin"><h3>' . esc_html__( 'Consigne', 'catalogue-formations' ) . '</h3>' . wp_kses_post( wpautop( $ex['instructions'] ) ) . '</div>';
		}
		echo '<div class="if-card-admin"><h3>' . esc_html__( 'Réponse', 'catalogue-formations' ) . '</h3>' . wp_kses_post( wpautop( $s->answer ) );
		if ( $s->file_id ) {
			echo '<p>📎 <a href="' . esc_url( wp_get_attachment_url( $s->file_id ) ) . '" target="_blank" rel="noopener">' . esc_html( basename( (string) get_attached_file( $s->file_id ) ) ) . '</a></p>';
		}
		echo '</div>';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="if-card-admin">
			<input type="hidden" name="action" value="if_admin_correct">
			<input type="hidden" name="submission_id" value="<?php echo (int) $s->id; ?>">
			<?php wp_nonce_field( 'if_admin_correct' ); ?>
			<h3><?php esc_html_e( 'Ma correction', 'catalogue-formations' ); ?></h3>
			<?php wp_editor( (string) $s->feedback, 'if_feedback', array( 'textarea_name' => 'feedback', 'textarea_rows' => 8, 'media_buttons' => false ) ); ?>
			<p><button class="button button-primary"><?php echo 'corrected' === $s->status ? esc_html__( 'Mettre à jour la correction', 'catalogue-formations' ) : esc_html__( 'Envoyer la correction', 'catalogue-formations' ); ?></button></p>
		</form>
		<?php
		echo '</div>';
		return;
	}

	foreach ( array( 'submitted' => __( 'À corriger', 'catalogue-formations' ), 'corrected' => __( 'Déjà corrigés', 'catalogue-formations' ) ) as $status => $title ) {
		$list = if_get_submissions( $status );
		echo '<h2>' . esc_html( $title ) . ' (' . count( $list ) . ')</h2>';
		if ( ! $list ) {
			echo '<p>' . esc_html__( 'Rien pour le moment.', 'catalogue-formations' ) . '</p>';
			continue;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Apprenant', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Notion', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Exercice', 'catalogue-formations' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $list as $s ) {
			$ex = if_find_item( $s->notion_id, $s->exercise_key, 'exercise' );
			echo '<tr><td>' . esc_html( mysql2date( get_option( 'date_format' ), $s->submitted_at ) ) . '</td><td>' . esc_html( if_user_name( $s->user_id ) ) . '</td><td>' . esc_html( get_the_title( $s->notion_id ) ) . '</td><td>' . esc_html( $ex ? $ex['title'] : '—' ) . '</td>';
			echo '<td><a class="button" href="' . esc_url( if_admin_url( 'if-corrections', array( 'submission' => $s->id ) ) ) . '">' . ( 'submitted' === $status ? esc_html__( 'Corriger', 'catalogue-formations' ) : esc_html__( 'Voir', 'catalogue-formations' ) ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

function if_admin_handle_correct() {
	if ( ! current_user_can( if_cap() ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_admin_correct' );
	$id       = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
	$feedback = isset( $_POST['feedback'] ) ? wp_kses_post( wp_unslash( $_POST['feedback'] ) ) : '';
	if_correct_submission( $id, $feedback );
	wp_safe_redirect( if_admin_url( 'if-corrections', array( 'if_done' => 'corrected' ) ) );
	exit;
}

/* ================= Page « Réglages » ================= */

function if_page_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap if-admin">
		<h1><?php esc_html_e( 'Réglages de la formation', 'catalogue-formations' ); ?></h1>
		<?php if_admin_notice(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="if_admin_settings">
			<?php wp_nonce_field( 'if_admin_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="if_alert_email"><?php esc_html_e( 'E-mail des alertes', 'catalogue-formations' ); ?></label></th>
					<td><input type="email" class="regular-text" id="if_alert_email" name="if_alert_email" value="<?php echo esc_attr( get_option( 'if_alert_email', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Reçoit les nouvelles demandes et les exercices à corriger. Vide = e-mail de l\'administrateur.', 'catalogue-formations' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Page catalogue', 'catalogue-formations' ); ?></th>
					<td><?php wp_dropdown_pages( array( 'name' => 'if_catalogue_page', 'selected' => (int) get_option( 'if_catalogue_page', 0 ), 'show_option_none' => __( '— Aucune —', 'catalogue-formations' ), 'option_none_value' => 0 ) ); ?>
					<p class="description"><?php esc_html_e( 'Page qui contient le shortcode [if_catalogue].', 'catalogue-formations' ); ?></p></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Page tableau de bord', 'catalogue-formations' ); ?></th>
					<td><?php wp_dropdown_pages( array( 'name' => 'if_dashboard_page', 'selected' => (int) get_option( 'if_dashboard_page', 0 ), 'show_option_none' => __( '— Aucune —', 'catalogue-formations' ), 'option_none_value' => 0 ) ); ?>
					<p class="description"><?php esc_html_e( 'Page qui contient le shortcode [if_tableau_de_bord].', 'catalogue-formations' ); ?></p></td>
				</tr>
				<?php $cs = if_catalogue_settings(); ?>
				<tr>
					<th><?php esc_html_e( 'Filtres du catalogue', 'catalogue-formations' ); ?></th>
					<td>
						<fieldset>
							<?php
							$labels = array(
								'gniveau'  => __( 'Groupe de niveaux (ex. Collège, Cycle 3)', 'catalogue-formations' ),
								'niveau'   => __( 'Niveau (ex. 6ème)', 'catalogue-formations' ),
								'gmatiere' => __( 'Groupe de matières (ex. Langues)', 'catalogue-formations' ),
								'matiere'  => __( 'Matière (ex. Anglais)', 'catalogue-formations' ),
							);
							foreach ( $labels as $k => $l ) {
								echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="if_filters[' . esc_attr( $k ) . ']" value="1" ' . checked( ! empty( $cs['filters'][ $k ] ), true, false ) . '> ' . esc_html( $l ) . '</label>';
							}
							?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Listes déroulantes proposées aux visiteurs en haut du catalogue. Un filtre sans élément n\'est pas affiché.', 'catalogue-formations' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Rangement du catalogue', 'catalogue-formations' ); ?></th>
					<td>
						<label style="display:block;margin:4px 0"><input type="radio" name="if_sections" value="niveau" <?php checked( $cs['sections'], 'niveau' ); ?>> <?php esc_html_e( 'Par niveau, puis par matière', 'catalogue-formations' ); ?></label>
						<label style="display:block;margin:4px 0"><input type="radio" name="if_sections" value="matiere" <?php checked( $cs['sections'], 'matiere' ); ?>> <?php esc_html_e( 'Par matière, puis par niveau', 'catalogue-formations' ); ?></label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Enregistrer', 'catalogue-formations' ) ); ?>
		</form>
	</div>
	<?php
}

function if_admin_handle_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'catalogue-formations' ) );
	}
	check_admin_referer( 'if_admin_settings' );
	update_option( 'if_alert_email', isset( $_POST['if_alert_email'] ) ? sanitize_email( wp_unslash( $_POST['if_alert_email'] ) ) : '' );
	update_option( 'if_catalogue_page', isset( $_POST['if_catalogue_page'] ) ? absint( $_POST['if_catalogue_page'] ) : 0 );
	update_option( 'if_dashboard_page', isset( $_POST['if_dashboard_page'] ) ? absint( $_POST['if_dashboard_page'] ) : 0 );
	$filters = array();
	foreach ( array( 'niveau', 'gniveau', 'matiere', 'gmatiere' ) as $k ) {
		$filters[ $k ] = empty( $_POST['if_filters'][ $k ] ) ? 0 : 1;
	}
	update_option( 'if_filters', $filters );
	update_option( 'if_sections', isset( $_POST['if_sections'] ) && 'matiere' === $_POST['if_sections'] ? 'matiere' : 'niveau' );
	wp_safe_redirect( if_admin_url( 'if-settings', array( 'if_done' => 'saved' ) ) );
	exit;
}
