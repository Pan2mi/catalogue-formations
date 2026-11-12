<?php
/**
 * Apprenants et inscrits.
 *
 * - Formation → Apprenants : liste des apprenants, puis fiche d'un apprenant
 *   (ses notions, dates, avancement ; ajouter / modifier / retirer une notion).
 * - Formation → Inscrits : pour une notion, la liste des apprenants inscrits
 *   (ajouter / modifier / retirer un apprenant).
 * - Raccourcis : liste des notions (colonne et lien « Inscrits »), éditeur de notion,
 *   liste des comptes WordPress (lien « Formation »).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'if_people_menu', 20 );
add_filter( 'manage_if_notion_posts_columns', 'if_notion_enrolled_column' );
add_action( 'manage_if_notion_posts_custom_column', 'if_notion_enrolled_column_value', 10, 2 );
add_filter( 'post_row_actions', 'if_notion_row_action', 10, 2 );
add_filter( 'user_row_actions', 'if_user_row_action', 10, 2 );
add_action( 'add_meta_boxes_if_notion', 'if_enrolled_metabox' );

function if_people_menu() {
	$parent = 'edit.php?post_type=if_notion';
	add_submenu_page( $parent, __( 'Apprenants', 'catalogue-formations' ), __( 'Apprenants', 'catalogue-formations' ), if_cap(), 'if-learners', 'if_page_learners' );
	add_submenu_page( $parent, __( 'Inscrits à une notion', 'catalogue-formations' ), __( 'Inscrits', 'catalogue-formations' ), if_cap(), 'if-enrolled', 'if_page_enrolled' );
}

function if_learner_url( $user_id ) {
	return if_admin_url( 'if-learners', array( 'user' => (int) $user_id ) );
}

function if_enrolled_url( $notion_id ) {
	return if_admin_url( 'if-enrolled', array( 'notion' => (int) $notion_id ) );
}

/** URL de la page en cours (pour revenir ici après une action). */
function if_current_admin_url() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore
	return remove_query_arg( 'if_done', admin_url( preg_replace( '#^.*?/wp-admin/#', '', $uri ) ) );
}

/** Champs cachés communs aux formulaires d'accès. */
function if_access_hidden( $action, $nonce ) {
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	echo '<input type="hidden" name="back" value="' . esc_attr( if_current_admin_url() ) . '">';
	wp_nonce_field( $nonce );
}

/** Une ligne d'accès avec modification des dates sur place. */
function if_access_row_cells( $r, $show ) {
	$state = if_row_state( $r );
	$p     = if_progress( $r->user_id, $r->notion_id );
	$back  = if_current_admin_url();
	ob_start();
	if ( 'user' === $show ) {
		echo '<td><a href="' . esc_url( if_learner_url( $r->user_id ) ) . '"><strong>' . esc_html( if_user_name( $r->user_id ) ) . '</strong></a></td>';
	} else {
		echo '<td><a href="' . esc_url( if_enrolled_url( $r->notion_id ) ) . '"><strong>' . esc_html( get_the_title( $r->notion_id ) ) . '</strong></a><br><small>' . esc_html( if_terms_text( $r->notion_id, 'if_niveau' ) . ' · ' . if_terms_text( $r->notion_id, 'if_matiere' ) ) . '</small></td>';
	}
	echo '<td><span class="if-state if-state--' . esc_attr( $state ) . '">' . esc_html( if_state_label( $state ) ) . '</span></td>';
	echo '<td>';
	if ( 'pending' === $r->status || 'refused' === $r->status ) {
		$label = 'pending' === $r->status ? __( 'Valider', 'catalogue-formations' ) : __( 'Ouvrir l\'accès', 'catalogue-formations' );
	} else {
		$label = __( 'Enregistrer', 'catalogue-formations' );
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="if-inline">
		<?php if_access_hidden( 'if_admin_grant', 'if_admin_grant' ); ?>
		<input type="hidden" name="access_id" value="<?php echo (int) $r->id; ?>">
		<input type="hidden" name="user_id" value="<?php echo (int) $r->user_id; ?>">
		<input type="hidden" name="notion_id" value="<?php echo (int) $r->notion_id; ?>">
		<input type="date" name="date_start" required value="<?php echo esc_attr( $r->date_start ? $r->date_start : if_today() ); ?>" aria-label="<?php esc_attr_e( 'Du', 'catalogue-formations' ); ?>">
		→ <input type="date" name="date_end" required value="<?php echo esc_attr( $r->date_end ? $r->date_end : gmdate( 'Y-m-d', strtotime( '+1 year', strtotime( if_today() ) ) ) ); ?>" aria-label="<?php esc_attr_e( 'Au', 'catalogue-formations' ); ?>">
		<button class="button <?php echo 'pending' === $r->status ? 'button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
	if ( $r->message ) {
		echo '<small class="if-msg">💬 ' . esc_html( $r->message ) . '</small>';
	}
	echo '</td>';
	echo '<td>' . ( in_array( $state, array( 'pending', 'refused' ), true ) ? '—' : '<a href="' . esc_url( if_admin_url( 'if-progress', array( 'user' => $r->user_id, 'notion' => $r->notion_id ) ) ) . '">' . (int) $p['percent'] . ' %</a>' ) . '</td>';
	echo '<td>';
	if ( 'pending' === $r->status ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
			<?php if_access_hidden( 'if_admin_refuse', 'if_admin_refuse' ); ?>
			<input type="hidden" name="access_id" value="<?php echo (int) $r->id; ?>">
			<button class="button-link"><?php esc_html_e( 'Refuser', 'catalogue-formations' ); ?></button>
		</form> |
		<?php
	}
	$del = wp_nonce_url( add_query_arg( array( 'action' => 'if_admin_delete', 'access_id' => $r->id, 'back' => rawurlencode( $back ) ), admin_url( 'admin-post.php' ) ), 'if_admin_delete' );
	echo '<a class="if-danger" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Retirer cet accès ?', 'catalogue-formations' ) ) . '\');">' . esc_html__( 'Retirer', 'catalogue-formations' ) . '</a>';
	echo '</td>';
	return ob_get_clean();
}

/** Tableau des accès (d'un apprenant ou d'une notion). */
function if_access_table( $rows, $show ) {
	$first = 'user' === $show ? __( 'Apprenant', 'catalogue-formations' ) : __( 'Notion', 'catalogue-formations' );
	echo '<table class="widefat striped if-people"><thead><tr><th>' . esc_html( $first ) . '</th><th>' . esc_html__( 'État', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Période d\'accès (modifiable)', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Avancement', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Actions', 'catalogue-formations' ) . '</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="5">' . esc_html__( 'Aucun pour le moment.', 'catalogue-formations' ) . '</td></tr>';
	}
	foreach ( $rows as $r ) {
		echo '<tr>' . if_access_row_cells( $r, $show ) . '</tr>'; // phpcs:ignore
	}
	echo '</tbody></table>';
}

function if_people_styles() {
	echo '<style>.if-people td{vertical-align:middle}.if-people input[type=date]{max-width:150px}.if-msg{display:block;color:#50575e;margin-top:4px}.if-add-box{background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 16px;margin:16px 0;max-width:900px}.if-add-box .if-inline{gap:8px}.if-head{display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin:8px 0 16px}.if-head .if-stat{background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:8px 14px}.if-head .if-stat strong{display:block;font-size:20px}.if-learner-search{margin:10px 0}</style>';
}

/* ================= Formation → Apprenants ================= */

/** Apprenants : abonnés + toute personne ayant au moins un accès ou une demande. */
function if_learner_ids( $search = '' ) {
	global $wpdb;
	$t   = if_table( 'access' );
	$ids = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT user_id FROM $t" ) ); // phpcs:ignore
	$sub = get_users( array( 'role' => 'subscriber', 'fields' => 'ID' ) );
	$ids = array_unique( array_merge( $ids, array_map( 'intval', $sub ) ) );
	if ( $search ) {
		$found = get_users( array( 'search' => '*' . $search . '*', 'search_columns' => array( 'user_login', 'user_email', 'display_name' ), 'fields' => 'ID' ) );
		$ids   = array_values( array_intersect( $ids, array_map( 'intval', $found ) ) );
	}
	return $ids;
}

function if_page_learners() {
	if ( ! current_user_can( if_cap() ) ) {
		return;
	}
	$uid = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0; // phpcs:ignore
	if_people_styles();
	echo '<div class="wrap if-admin">';
	if ( $uid && get_userdata( $uid ) ) {
		if_learner_profile( $uid );
		echo '</div>';
		return;
	}

	$q = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
	echo '<h1>' . esc_html__( 'Apprenants', 'catalogue-formations' ) . '</h1>';
	if_admin_notice();
	?>
	<form method="get" class="if-learner-search if-inline">
		<input type="hidden" name="post_type" value="if_notion">
		<input type="hidden" name="page" value="if-learners">
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Nom, identifiant ou e-mail', 'catalogue-formations' ); ?>">
		<button class="button"><?php esc_html_e( 'Rechercher', 'catalogue-formations' ); ?></button>
		<a class="button" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">+ <?php esc_html_e( 'Créer un compte apprenant', 'catalogue-formations' ); ?></a>
	</form>
	<?php
	$ids = if_learner_ids( $q );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Apprenant', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Notions ouvertes', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Demandes en attente', 'catalogue-formations' ) . '</th><th>' . esc_html__( 'Avancement moyen', 'catalogue-formations' ) . '</th><th></th></tr></thead><tbody>';
	if ( ! $ids ) {
		echo '<tr><td colspan="5">' . esc_html__( 'Aucun apprenant.', 'catalogue-formations' ) . '</td></tr>';
	}
	foreach ( $ids as $id ) {
		$rows    = if_get_access_rows( array( 'user_id' => $id ) );
		$open    = 0;
		$pending = 0;
		$sum     = 0;
		$n       = 0;
		foreach ( $rows as $r ) {
			$st = if_row_state( $r );
			if ( 'active' === $st ) {
				$open++;
			}
			if ( 'pending' === $st ) {
				$pending++;
			}
			if ( in_array( $st, array( 'active', 'expired', 'upcoming' ), true ) ) {
				$sum += if_progress( $id, $r->notion_id )['percent'];
				$n++;
			}
		}
		echo '<tr><td><a href="' . esc_url( if_learner_url( $id ) ) . '"><strong>' . esc_html( if_user_name( $id ) ) . '</strong></a></td>';
		echo '<td>' . (int) $open . '</td><td>' . ( $pending ? '<span class="awaiting-mod">' . (int) $pending . '</span>' : '0' ) . '</td>';
		echo '<td>' . ( $n ? (int) round( $sum / $n ) . ' %' : '—' ) . '</td>';
		echo '<td><a class="button" href="' . esc_url( if_learner_url( $id ) ) . '">' . esc_html__( 'Voir la fiche', 'catalogue-formations' ) . '</a></td></tr>';
	}
	echo '</tbody></table>';
	echo '</div>';
}

function if_learner_profile( $uid ) {
	$u    = get_userdata( $uid );
	$rows = if_get_access_rows( array( 'user_id' => $uid ) );
	global $wpdb;
	$t    = if_table( 'submissions' );
	$todo = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE user_id = %d AND status = 'submitted'", $uid ) ); // phpcs:ignore

	echo '<p><a href="' . esc_url( if_admin_url( 'if-learners' ) ) . '">← ' . esc_html__( 'Tous les apprenants', 'catalogue-formations' ) . '</a></p>';
	echo '<h1>' . esc_html( $u->display_name ) . '</h1>';
	if_admin_notice();
	$open = count( array_filter( $rows, function ( $r ) { return 'active' === if_row_state( $r ); } ) );
	echo '<div class="if-head">';
	echo '<span class="if-stat"><strong>' . (int) $open . '</strong>' . esc_html__( 'notion(s) ouverte(s)', 'catalogue-formations' ) . '</span>';
	echo '<span class="if-stat"><strong>' . (int) $todo . '</strong>' . esc_html__( 'exercice(s) à corriger', 'catalogue-formations' ) . '</span>';
	echo '<span>✉️ ' . esc_html( $u->user_email ) . ' · ' . esc_html__( 'Identifiant :', 'catalogue-formations' ) . ' ' . esc_html( $u->user_login ) . '</span>';
	echo '<a class="button" href="' . esc_url( get_edit_user_link( $uid ) ) . '">' . esc_html__( 'Modifier le compte', 'catalogue-formations' ) . '</a>';
	if ( $todo ) {
		echo '<a class="button" href="' . esc_url( if_admin_url( 'if-corrections' ) ) . '">' . esc_html__( 'Voir les corrections', 'catalogue-formations' ) . '</a>';
	}
	echo '</div>';

	// Ajouter une notion à cet apprenant.
	$has = wp_list_pluck( $rows, 'notion_id' );
	?>
	<div class="if-add-box">
		<h2 style="margin-top:0">+ <?php esc_html_e( 'Ajouter une notion', 'catalogue-formations' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="if-inline">
			<?php if_access_hidden( 'if_admin_grant', 'if_admin_grant' ); ?>
			<input type="hidden" name="user_id" value="<?php echo (int) $uid; ?>">
			<select name="notion_id" required>
				<option value=""><?php esc_html_e( '— Choisir une notion —', 'catalogue-formations' ); ?></option>
				<?php foreach ( get_posts( array( 'post_type' => 'if_notion', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $n ) : ?>
					<option value="<?php echo (int) $n->ID; ?>"><?php echo esc_html( $n->post_title . ' — ' . if_terms_text( $n->ID, 'if_niveau' ) . ( in_array( (string) $n->ID, array_map( 'strval', $has ), true ) ? ' ✓' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><?php esc_html_e( 'du', 'catalogue-formations' ); ?> <input type="date" name="date_start" required value="<?php echo esc_attr( if_today() ); ?>"></label>
			<label><?php esc_html_e( 'au', 'catalogue-formations' ); ?> <input type="date" name="date_end" required value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 year', strtotime( if_today() ) ) ) ); ?>"></label>
			<button class="button button-primary"><?php esc_html_e( 'Ajouter', 'catalogue-formations' ); ?></button>
		</form>
		<p class="description">✓ = <?php esc_html_e( 'déjà attribuée (les dates seront mises à jour).', 'catalogue-formations' ); ?></p>
	</div>
	<h2><?php esc_html_e( 'Ses notions', 'catalogue-formations' ); ?></h2>
	<?php
	if_access_table( $rows, 'notion' );
}

/* ================= Formation → Inscrits (par notion) ================= */

function if_page_enrolled() {
	if ( ! current_user_can( if_cap() ) ) {
		return;
	}
	$nid = isset( $_GET['notion'] ) ? absint( $_GET['notion'] ) : 0; // phpcs:ignore
	if_people_styles();
	echo '<div class="wrap if-admin"><h1>' . esc_html__( 'Inscrits à une notion', 'catalogue-formations' ) . '</h1>';
	if_admin_notice();
	?>
	<form method="get" class="if-inline" style="margin:10px 0">
		<input type="hidden" name="post_type" value="if_notion">
		<input type="hidden" name="page" value="if-enrolled">
		<select name="notion" onchange="this.form.submit()">
			<option value="0"><?php esc_html_e( '— Choisir une notion —', 'catalogue-formations' ); ?></option>
			<?php echo if_notion_options( $nid ); // phpcs:ignore ?>
		</select>
		<noscript><button class="button"><?php esc_html_e( 'Afficher', 'catalogue-formations' ); ?></button></noscript>
	</form>
	<?php
	if ( ! $nid || 'if_notion' !== get_post_type( $nid ) ) {
		echo '</div>';
		return;
	}
	$rows = if_get_access_rows( array( 'notion_id' => $nid ) );
	$open = count( array_filter( $rows, function ( $r ) { return 'active' === if_row_state( $r ); } ) );
	$pend = count( array_filter( $rows, function ( $r ) { return 'pending' === $r->status; } ) );
	echo '<div class="if-head"><h2 style="margin:0">' . esc_html( get_the_title( $nid ) ) . '</h2>';
	echo '<span class="if-stat"><strong>' . (int) $open . '</strong>' . esc_html__( 'accès ouvert(s)', 'catalogue-formations' ) . '</span>';
	echo '<span class="if-stat"><strong>' . (int) $pend . '</strong>' . esc_html__( 'demande(s) en attente', 'catalogue-formations' ) . '</span>';
	echo '<a class="button" href="' . esc_url( get_edit_post_link( $nid ) ) . '">' . esc_html__( 'Modifier la notion', 'catalogue-formations' ) . '</a>';
	echo '<a class="button" href="' . esc_url( get_permalink( $nid ) ) . '" target="_blank">' . esc_html__( 'Voir la notion', 'catalogue-formations' ) . '</a></div>';

	$has = wp_list_pluck( $rows, 'user_id' );
	?>
	<div class="if-add-box">
		<h2 style="margin-top:0">+ <?php esc_html_e( 'Inscrire un apprenant', 'catalogue-formations' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="if-inline">
			<?php if_access_hidden( 'if_admin_grant', 'if_admin_grant' ); ?>
			<input type="hidden" name="notion_id" value="<?php echo (int) $nid; ?>">
			<select name="user_id" required>
				<option value=""><?php esc_html_e( '— Choisir un apprenant —', 'catalogue-formations' ); ?></option>
				<?php foreach ( if_learner_ids() as $id ) : ?>
					<option value="<?php echo (int) $id; ?>"><?php echo esc_html( if_user_name( $id ) . ( in_array( (string) $id, array_map( 'strval', $has ), true ) ? ' ✓' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<label><?php esc_html_e( 'du', 'catalogue-formations' ); ?> <input type="date" name="date_start" required value="<?php echo esc_attr( if_today() ); ?>"></label>
			<label><?php esc_html_e( 'au', 'catalogue-formations' ); ?> <input type="date" name="date_end" required value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 year', strtotime( if_today() ) ) ) ); ?>"></label>
			<button class="button button-primary"><?php esc_html_e( 'Inscrire', 'catalogue-formations' ); ?></button>
		</form>
		<p class="description">✓ = <?php esc_html_e( 'déjà inscrit (les dates seront mises à jour).', 'catalogue-formations' ); ?></p>
	</div>
	<h2><?php esc_html_e( 'Apprenants inscrits', 'catalogue-formations' ); ?></h2>
	<?php
	if_access_table( $rows, 'user' );
	echo '</div>';
}

/* ================= Raccourcis ================= */

function if_notion_enrolled_column( $cols ) {
	$cols['if_enrolled'] = __( 'Inscrits', 'catalogue-formations' );
	return $cols;
}

function if_notion_enrolled_column_value( $col, $post_id ) {
	if ( 'if_enrolled' !== $col ) {
		return;
	}
	$rows = if_get_access_rows( array( 'notion_id' => $post_id ) );
	$open = count( array_filter( $rows, function ( $r ) { return 'active' === if_row_state( $r ); } ) );
	$pend = count( array_filter( $rows, function ( $r ) { return 'pending' === $r->status; } ) );
	echo '<a href="' . esc_url( if_enrolled_url( $post_id ) ) . '">' . (int) $open . ' ' . esc_html__( 'ouvert(s)', 'catalogue-formations' ) . '</a>';
	if ( $pend ) {
		echo ' <span class="awaiting-mod" title="' . esc_attr__( 'Demandes en attente', 'catalogue-formations' ) . '">' . (int) $pend . '</span>';
	}
}

function if_notion_row_action( $actions, $post ) {
	if ( 'if_notion' === $post->post_type && current_user_can( if_cap() ) ) {
		$actions['if_enrolled'] = '<a href="' . esc_url( if_enrolled_url( $post->ID ) ) . '">' . esc_html__( 'Inscrits', 'catalogue-formations' ) . '</a>';
	}
	return $actions;
}

function if_user_row_action( $actions, $user ) {
	if ( current_user_can( if_cap() ) ) {
		$actions['if_learner'] = '<a href="' . esc_url( if_learner_url( $user->ID ) ) . '">' . esc_html__( 'Formation', 'catalogue-formations' ) . '</a>';
	}
	return $actions;
}

/** Éditeur de notion : petit encadré « Inscrits » dans la colonne de droite. */
function if_enrolled_metabox() {
	add_meta_box( 'if_enrolled_box', __( 'Inscrits', 'catalogue-formations' ), 'if_enrolled_metabox_render', 'if_notion', 'side', 'default' );
}

function if_enrolled_metabox_render( $post ) {
	$rows = if_get_access_rows( array( 'notion_id' => $post->ID ) );
	$open = array_filter( $rows, function ( $r ) { return 'active' === if_row_state( $r ); } );
	$pend = count( array_filter( $rows, function ( $r ) { return 'pending' === $r->status; } ) );
	echo '<p><strong>' . count( $open ) . '</strong> ' . esc_html__( 'accès ouvert(s)', 'catalogue-formations' ) . ( $pend ? ' · <strong>' . (int) $pend . '</strong> ' . esc_html__( 'demande(s)', 'catalogue-formations' ) : '' ) . '</p>';
	if ( $open ) {
		echo '<ul style="margin:0 0 8px">';
		foreach ( array_slice( $open, 0, 5 ) as $r ) {
			echo '<li><a href="' . esc_url( if_learner_url( $r->user_id ) ) . '">' . esc_html( get_userdata( $r->user_id ) ? get_userdata( $r->user_id )->display_name : '#' . $r->user_id ) . '</a></li>';
		}
		echo '</ul>';
	}
	echo '<a class="button" href="' . esc_url( if_enrolled_url( $post->ID ) ) . '">' . esc_html__( 'Gérer les inscrits', 'catalogue-formations' ) . '</a>';
}
