<?php
/**
 * Admin screens: leads, conversations, teaching and settings. Shop managers only.
 * Everything shoppers typed is printed as plain text, so nothing typed into the chat can run
 * as code in the admin's browser.
 *
 * @package dlight-assistant
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . dla_lead_table() . " WHERE status = 'new'" );
		$badge = $new ? ' <span class="awaiting-mod">' . $new . '</span>' : '';
		add_menu_page( 'Dlight AI', 'Dlight AI' . $badge, 'manage_woocommerce', 'dla-leads', 'dla_leads_page', 'dashicons-format-chat', 56 );
		add_submenu_page( 'dla-leads', 'Leads', 'Leads' . $badge, 'manage_woocommerce', 'dla-leads', 'dla_leads_page' );
		add_submenu_page( 'dla-leads', 'Conversations', 'Conversations', 'manage_woocommerce', 'dla-chats', 'dla_chats_page' );
		add_submenu_page( 'dla-leads', 'Teach & settings', 'Teach & settings', 'manage_woocommerce', 'dlight-assistant', 'dla_admin_page' );
	}
);

add_action( 'admin_post_dla_save', 'dla_admin_save' );

/**
 * Save the admin forms.
 */
function dla_admin_save() {
	global $wpdb;
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'dla_save' );
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.DB.DirectDatabaseQuery
	$do   = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
	$back = 'dlight-assistant';

	if ( 'settings' === $do ) {
		$notify = sanitize_email( wp_unslash( $_POST['notify'] ?? '' ) );
		update_option(
			'dla_options',
			array(
				'enabled'  => empty( $_POST['enabled'] ) ? 0 : 1,
				'greeting' => sanitize_textarea_field( wp_unslash( $_POST['greeting'] ?? '' ) ),
				'whatsapp' => preg_replace( '/\D/', '', wp_unslash( $_POST['whatsapp'] ?? '' ) ),
				'notify'   => $notify && is_email( $notify ) ? $notify : '',
			)
		);
	}
	if ( 'answer' === $do ) {
		$match = array_filter( array_map( 'trim', explode( ',', strtolower( sanitize_text_field( wp_unslash( $_POST['match'] ?? '' ) ) ) ) ) );
		$text  = sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) );
		if ( $match && $text ) {
			$answers   = dla_custom_answers();
			$answers[] = array(
				'id' => 'custom_' . wp_generate_password( 6, false ),
				'm'  => array_values( $match ),
				'a'  => $text,
			);
			update_option( 'dla_custom_answers', $answers, false );
		}
	}
	if ( 'delete_answer' === $do ) {
		$id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		update_option( 'dla_custom_answers', array_values( array_filter( dla_custom_answers(), static fn( $a ) => $a['id'] !== $id ) ), false );
	}
	if ( 'synonym' === $do ) {
		$word  = strtolower( sanitize_text_field( wp_unslash( $_POST['word'] ?? '' ) ) );
		$means = strtolower( sanitize_text_field( wp_unslash( $_POST['means'] ?? '' ) ) );
		if ( $word && $means ) {
			$synonyms          = (array) get_option( 'dla_synonyms', array() );
			$synonyms[ $word ] = $means;
			update_option( 'dla_synonyms', $synonyms, false );
		}
	}
	if ( 'delete_synonym' === $do ) {
		$synonyms = (array) get_option( 'dla_synonyms', array() );
		unset( $synonyms[ strtolower( sanitize_text_field( wp_unslash( $_POST['word'] ?? '' ) ) ) ] );
		update_option( 'dla_synonyms', $synonyms, false );
	}
	if ( 'lead_status' === $do ) {
		$status = sanitize_key( $_POST['status'] ?? '' );
		if ( in_array( $status, array( 'new', 'contacted', 'sold', 'closed' ), true ) ) {
			$wpdb->update( dla_lead_table(), array( 'status' => $status ), array( 'id' => absint( $_POST['id'] ?? 0 ) ) );
		}
		$back = 'dla-leads';
	}
	if ( 'lead_delete' === $do ) {
		$wpdb->delete( dla_lead_table(), array( 'id' => absint( $_POST['id'] ?? 0 ) ) );
		$back = 'dla-leads';
	}
	if ( 'chat_delete' === $do ) {
		$session = dla_session( wp_unslash( $_POST['session'] ?? '' ) );
		if ( $session ) {
			$wpdb->delete( dla_log_table(), array( 'session' => $session ) );
		}
		$back = 'dla-chats';
	}
	// phpcs:enable

	if ( 'dlight-assistant' === $back ) {
		dla_build_index();
	}
	wp_safe_redirect( admin_url( 'admin.php?page=' . $back . '&saved=1' ) );
	exit;
}

/**
 * Print a form's opening tags.
 *
 * @param string $do Action name.
 */
function dla_form( $do ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
	echo '<input type="hidden" name="action" value="dla_save"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	wp_nonce_field( 'dla_save' );
}

/**
 * Show a time in the shop's timezone.
 *
 * @param string $utc MySQL UTC time.
 * @return string
 */
function dla_when( $utc ) {
	return get_date_from_gmt( $utc, 'j M Y, H:i' );
}

/**
 * Leads screen.
 */
function dla_leads_page() {
	global $wpdb;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'open';
	// phpcs:enable
	$where = 'open' === $status ? "status IN ('new','contacted')" : $wpdb->prepare( 'status = %s', $status );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$leads = (array) $wpdb->get_results( 'SELECT * FROM ' . dla_lead_table() . " WHERE {$where} ORDER BY created DESC LIMIT 200" );
	?>
	<div class="wrap">
		<h1>Dlight AI leads</h1>
		<p>People who left their number in the chat so the team can contact them. Leads are deleted automatically after <?php echo (int) DLA_LEAD_DAYS; ?> days.</p>
		<p>
			<?php
			foreach ( array( 'open' => 'To follow up', 'sold' => 'Sold', 'closed' => 'Closed' ) as $key => $label ) {
				printf( '<a class="button%s" href="%s">%s</a> ', $key === $status ? ' button-primary' : '', esc_url( admin_url( 'admin.php?page=dla-leads&status=' . $key ) ), esc_html( $label ) );
			}
			?>
		</p>
		<table class="widefat striped">
			<thead><tr><th>When</th><th>Phone</th><th>Name</th><th>Location</th><th>Looking for</th><th>Interested in</th><th>Status</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $leads ) : ?>
				<tr><td colspan="8">No leads here yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $leads as $lead ) : ?>
				<tr>
					<td><?php echo esc_html( dla_when( $lead->created ) ); ?><?php echo 'typed' === $lead->source ? '<br><small>typed in chat</small>' : ''; ?></td>
					<td>
						<strong><?php echo esc_html( $lead->phone ); ?></strong><br>
						<a href="<?php echo esc_url( 'tel:' . $lead->phone ); ?>">Call</a> &middot;
						<a href="<?php echo esc_url( 'https://wa.me/' . ltrim( $lead->phone, '+' ) ); ?>" target="_blank" rel="noopener">WhatsApp</a>
					</td>
					<td><?php echo esc_html( $lead->name ); ?></td>
					<td><?php echo esc_html( $lead->location ); ?></td>
					<td><?php echo esc_html( $lead->note ); ?></td>
					<td>
						<?php
						foreach ( array_filter( explode( ',', $lead->products ) ) as $id ) {
							$product = wc_get_product( (int) $id );
							if ( $product ) {
								printf( '<a href="%s" target="_blank">%s</a> (%s)<br>', esc_url( $product->get_permalink() ), esc_html( $product->get_name() ), wp_kses_post( wc_price( $product->get_price() ) ) );
							}
						}
						if ( $lead->session ) {
							printf( '<a href="%s">Read the chat</a>', esc_url( admin_url( 'admin.php?page=dla-chats&session=' . $lead->session ) ) );
						}
						?>
					</td>
					<td>
						<?php dla_form( 'lead_status' ); ?>
							<input type="hidden" name="id" value="<?php echo (int) $lead->id; ?>">
							<select name="status" onchange="this.form.submit()">
								<?php
								foreach ( array( 'new' => 'New', 'contacted' => 'Contacted', 'sold' => 'Sold', 'closed' => 'Closed' ) as $key => $label ) {
									printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $lead->status, $key, false ), esc_html( $label ) );
								}
								?>
							</select>
						</form>
					</td>
					<td>
						<?php dla_form( 'lead_delete' ); ?>
							<input type="hidden" name="id" value="<?php echo (int) $lead->id; ?>">
							<button class="button-link button-link-delete" onclick="return confirm('Delete this lead?')">Delete</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Conversations screen.
 */
function dla_chats_page() {
	global $wpdb;
	$table = dla_log_table();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$session = isset( $_GET['session'] ) ? dla_session( wp_unslash( $_GET['session'] ) ) : '';
	echo '<div class="wrap"><h1>Dlight AI conversations</h1>';
	echo '<p>Phone numbers and emails typed into the chat are hidden here; when someone leaves a number for us, it appears under Leads. Conversations are deleted automatically after ' . (int) DLA_LOG_DAYS . ' days.</p>';

	if ( $session ) {
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE session = %s AND question <> '' ORDER BY id ASC LIMIT 300", $session ) );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=dla-chats' ) ) . '">&larr; All conversations</a></p>';
		echo '<div style="max-width:760px">';
		foreach ( $rows as $row ) {
			printf(
				'<div style="margin:10px 0 2px;text-align:right"><span style="display:inline-block;background:#000;color:#fff;padding:7px 11px;border-radius:12px;max-width:80%%;text-align:left">%s</span><br><small>%s</small></div>',
				esc_html( $row->question ),
				esc_html( dla_when( $row->created ) )
			);
			if ( $row->reply ) {
				printf(
					'<div style="margin:4px 0 10px"><span style="display:inline-block;background:#fff;border:1px solid #ddd;padding:7px 11px;border-radius:12px;max-width:80%%">%s</span>%s</div>',
					esc_html( $row->reply ),
					$row->answered ? '' : ' <small style="color:#b32d2e">not understood</small>'
				);
			}
		}
		echo '</div>';
		dla_form( 'chat_delete' );
		echo '<input type="hidden" name="session" value="' . esc_attr( $session ) . '"><p><button class="button button-link-delete" onclick="return confirm(\'Delete this conversation?\')">Delete this conversation</button></p></form>';
		echo '</div>';
		return;
	}

	$chats = (array) $wpdb->get_results(
		"SELECT l.session, MIN(l.created) started, COUNT(*) turns, SUM(l.answered = 0) missed,
			SUBSTRING_INDEX(GROUP_CONCAT(l.question ORDER BY l.id SEPARATOR '\n'), '\n', 1) first_q,
			(SELECT COUNT(*) FROM " . dla_lead_table() . " d WHERE d.session = l.session) leads
		FROM {$table} l WHERE l.session <> '' AND l.question <> '' GROUP BY l.session ORDER BY started DESC LIMIT 200"
	);
	$missed = (array) $wpdb->get_results( "SELECT question, COUNT(*) c FROM {$table} WHERE answered = 0 AND question <> '' GROUP BY question ORDER BY c DESC LIMIT 15" );
	// phpcs:enable
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Started</th><th>First question</th><th>Messages</th><th>Not understood</th><th>Left a number</th></tr></thead><tbody>';
	if ( ! $chats ) {
		echo '<tr><td colspan="5">No conversations yet.</td></tr>';
	}
	foreach ( $chats as $chat ) {
		printf(
			'<tr><td>%s</td><td><a href="%s">%s</a></td><td>%d</td><td>%d</td><td>%s</td></tr>',
			esc_html( dla_when( $chat->started ) ),
			esc_url( admin_url( 'admin.php?page=dla-chats&session=' . $chat->session ) ),
			esc_html( $chat->first_q ),
			(int) $chat->turns,
			(int) $chat->missed,
			$chat->leads ? '<strong>Yes</strong>' : '-'
		);
	}
	echo '</tbody></table>';
	if ( $missed ) {
		echo '<h2>Questions it could not answer</h2><p>Teach it these under Teach &amp; settings.</p><ul>';
		foreach ( $missed as $row ) {
			echo '<li>' . esc_html( $row->question ) . ' (' . (int) $row->c . ')</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

/**
 * Teaching and settings screen.
 */
function dla_admin_page() {
	$options = dla_options();
	?>
	<div class="wrap">
		<h1>Teach Dlight AI</h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success"><p>Saved, and Dlight AI has been updated.</p></div>
		<?php endif; ?>
		<p>It knows <strong><?php echo esc_html( number_format_i18n( (int) get_option( 'dla_index_count', 0 ) ) ); ?></strong> in-stock products and updates by itself when products change. Questions it could not answer are listed under Conversations.</p>

		<h2>Teach a new answer</h2>
		<?php dla_form( 'answer' ); ?>
			<p><label><strong>When someone mentions</strong> (separate with commas)<br><input class="large-text" name="match" placeholder="opening hours, what time do you open" required></label></p>
			<p><label><strong>Reply with</strong><br><textarea class="large-text" rows="3" name="answer" required></textarea></label></p>
			<?php submit_button( 'Add answer', 'primary', 'submit', false ); ?>
		</form>
		<?php foreach ( dla_custom_answers() as $answer ) : ?>
			<div style="max-width:900px;margin:8px 0">
				<strong><?php echo esc_html( implode( ', ', $answer['m'] ) ); ?>:</strong> <?php echo esc_html( $answer['a'] ); ?>
				<?php dla_form( 'delete_answer' ); ?><input type="hidden" name="id" value="<?php echo esc_attr( $answer['id'] ); ?>"> <button class="button-link button-link-delete">Remove</button></form>
			</div>
		<?php endforeach; ?>

		<h2>Teach a word</h2>
		<p>For example, teach it that <em>ndume</em> means <em>men</em>, or <em>saa ya dhahabu</em> means <em>gold watch</em>.</p>
		<?php dla_form( 'synonym' ); ?>
			<input name="word" placeholder="Word customers use" required> means <input name="means" placeholder="Word Dlight AI knows" required>
			<?php submit_button( 'Add word', 'secondary', 'submit', false ); ?>
		</form>
		<?php foreach ( (array) get_option( 'dla_synonyms', array() ) as $word => $means ) : ?>
			<div><?php echo esc_html( $word . ' = ' . $means ); ?> <?php dla_form( 'delete_synonym' ); ?><input type="hidden" name="word" value="<?php echo esc_attr( $word ); ?>"> <button class="button-link button-link-delete">Remove</button></form></div>
		<?php endforeach; ?>

		<h2>Settings</h2>
		<?php dla_form( 'settings' ); ?>
			<p><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>> Show Dlight AI on the website</label></p>
			<p><label><strong>Greeting</strong><br><textarea class="large-text" rows="3" name="greeting"><?php echo esc_textarea( $options['greeting'] ); ?></textarea></label></p>
			<p><label><strong>WhatsApp number for handoff</strong><br><input name="whatsapp" value="<?php echo esc_attr( $options['whatsapp'] ); ?>"></label><br>
			<span class="description">Dlight AI offers to continue on WhatsApp, with a summary of the chat, when someone asks for a person or it cannot help. Leave empty to turn this off.</span></p>
			<p><label><strong>Email new leads to</strong><br><input type="email" name="notify" value="<?php echo esc_attr( $options['notify'] ); ?>"></label></p>
			<?php submit_button( 'Save settings' ); ?>
		</form>
	</div>
	<?php
}
