<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

/**
 * Admin view for Geniki Taxydromiki Settings & Automation Hub (Tab 1).
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin/partials
 */

$gt_notice = null;

// 1. Handle Secret Key Regeneration
if ( isset( $_POST['gt_cod_regenerate_secret'] ) && check_admin_referer( 'gt_cod_regen_secret_nonce', 'gt_cod_regen_secret_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$gt_new_secret = wp_generate_password( 32, false );
		update_option( 'gtvfw_cod_webhook_secret', $gt_new_secret );
		$gt_notice = array(
			'type'    => 'success',
			'message' => __( 'Δημιουργήθηκε νέο Secret Key για το Webhook επιτυχώς!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	}
}

// 2. Handle Automation & IMAP Settings Save
if ( isset( $_POST['gt_cod_save_auto_settings'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$gt_method = isset( $_POST['gt_cod_auto_method'] ) ? sanitize_text_field( wp_unslash( $_POST['gt_cod_auto_method'] ) ) : 'webhook';
		update_option( 'gtvfw_cod_auto_method', $gt_method );

		$gt_existing_imap = GT_COD_Importer::get_imap_settings();
		$gt_imap_settings = array(
			'host'          => isset( $_POST['imap_host'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_host'] ) ) : $gt_existing_imap['host'],
			'port'          => isset( $_POST['imap_port'] ) ? intval( wp_unslash( $_POST['imap_port'] ) ) : $gt_existing_imap['port'],
			'encryption'    => isset( $_POST['imap_encryption'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_encryption'] ) ) : $gt_existing_imap['encryption'],
			'username'      => isset( $_POST['imap_username'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_username'] ) ) : $gt_existing_imap['username'],
			'password'      => ! empty( $_POST['imap_password'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_password'] ) ) : $gt_existing_imap['password'],
			'folder'        => isset( $_POST['imap_folder'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_folder'] ) ) : $gt_existing_imap['folder'],
			'sender_filter' => isset( $_POST['imap_sender_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_sender_filter'] ) ) : $gt_existing_imap['sender_filter'],
			'schedule'      => isset( $_POST['imap_schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_schedule'] ) ) : $gt_existing_imap['schedule'],
		);
		GT_COD_Importer::save_imap_settings( $gt_imap_settings );

		$gt_notice = array(
			'type'    => 'success',
			'message' => __( 'Οι ρυθμίσεις αυτοματισμού αποθηκεύτηκαν επιτυχώς!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	}
}

// 3. Handle IMAP Connection Test
if ( isset( $_POST['gt_cod_test_imap'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$gt_existing_imap = GT_COD_Importer::get_imap_settings();
		$gt_temp_settings = array(
			'host'          => isset( $_POST['imap_host'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_host'] ) ) : $gt_existing_imap['host'],
			'port'          => isset( $_POST['imap_port'] ) ? intval( wp_unslash( $_POST['imap_port'] ) ) : $gt_existing_imap['port'],
			'encryption'    => isset( $_POST['imap_encryption'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_encryption'] ) ) : $gt_existing_imap['encryption'],
			'username'      => isset( $_POST['imap_username'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_username'] ) ) : $gt_existing_imap['username'],
			'password'      => ! empty( $_POST['imap_password'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_password'] ) ) : $gt_existing_imap['password'],
			'folder'        => isset( $_POST['imap_folder'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_folder'] ) ) : $gt_existing_imap['folder'],
		);
		$gt_res = GT_COD_Importer::test_imap_connection( $gt_temp_settings );
		if ( is_wp_error( $gt_res ) ) {
			$gt_notice = array(
				'type'    => 'error',
				'message' => $gt_res->get_error_message(),
			);
		} else {
			$gt_notice = array(
				'type'    => 'success',
				'message' => $gt_res['message'],
			);
		}
	}
}

// 4. Handle Manual IMAP Check & Sync Now
if ( isset( $_POST['gt_cod_check_imap_now'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$gt_res = GT_COD_Importer::fetch_and_process_imap_emails();
		if ( is_wp_error( $gt_res ) ) {
			$gt_notice = array(
				'type'    => 'error',
				'message' => sprintf(
					/* translators: %s: IMAP error message */
					__( 'Σφάλμα ανάγνωσης IMAP: %s', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					$gt_res->get_error_message()
				),
			);
		} else {
			$gt_notice = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: 1: messages processed, 2: files processed, 3: updated orders, 4: total amount */
					__( 'Ολοκληρώθηκε ο έλεγχος IMAP! Επεξεργάστηκαν %1$d μηνύματα (%2$d αρχεία) και ενημερώθηκαν %3$d παραγγελίες (Σύνολο: %4$s €).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					(int) $gt_res['messages_processed'],
					(int) $gt_res['files_processed'],
					(int) $gt_res['updated_orders'],
					number_format( (float) $gt_res['total_amount'], 2, ',', '.' )
				),
			);
		}
	}
}

$gt_settings_url       = admin_url( 'admin.php?page=gtvfw_settings' );
$gt_cod_import_url     = admin_url( 'admin.php?page=gtvfw_cod_import' );
$gt_invoice_import_url = admin_url( 'admin.php?page=gtvfw_invoice_import' );

$gt_webhook_url        = rest_url( 'gtvfw/v1/cod-webhook' );
$gt_webhook_secret     = GT_COD_Importer::get_webhook_secret();
$gt_auto_method        = GT_COD_Importer::get_auto_method();
$gt_last_webhook_log   = get_option( 'gtvfw_cod_webhook_last_log' );
$gt_last_invoice_log   = get_option( 'gtvfw_invoice_last_log' );
$gt_imap_settings      = GT_COD_Importer::get_imap_settings();
$gt_last_imap_log      = get_option( 'gtvfw_cod_imap_last_log' );
$gt_imap_next_cron     = wp_next_scheduled( 'gtvfw_cod_imap_cron_check' );
?>

<div class="wrap gtvfw-admin-wrapper">
	<h1><?php esc_html_e( 'Γενική Ταχυδρομική - Διαχείριση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
		<a href="<?php echo esc_url( $gt_settings_url ); ?>" class="nav-tab nav-tab-active">
			<?php esc_html_e( 'Ρυθμίσεις & Αυτοματισμός (Hub)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
		<a href="<?php echo esc_url( $gt_cod_import_url ); ?>" class="nav-tab">
			<?php esc_html_e( 'Αντικαταβολές (COD)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
		<a href="<?php echo esc_url( $gt_invoice_import_url ); ?>" class="nav-tab">
			<?php esc_html_e( 'Τιμολόγια & Έλεγχος Κόστους (P&L)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
	</nav>

	<?php if ( $gt_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $gt_notice['type'] ); ?> is-dismissible">
			<p><strong><?php echo esc_html( $gt_notice['message'] ); ?></strong></p>
		</div>
	<?php endif; ?>

	<!-- Hidden Form for Secret Regeneration -->
	<form method="POST" action="<?php echo esc_url( $gt_settings_url ); ?>" id="gt_regen_secret_form" style="display:none;">
		<?php wp_nonce_field( 'gt_cod_regen_secret_nonce', 'gt_cod_regen_secret_nonce_field' ); ?>
	</form>

	<div style="display: flex; flex-direction: column; gap: 25px; max-width: 1050px; width: 100%;">

		<!-- BLOCK 1: API Settings -->
		<div class="card" style="max-width: 100%; width: 100%; padding: 20px 25px; margin: 0; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); box-sizing: border-box;">
			<h2 style="margin-top: 0; color: #005aa4; font-size: 1.3em;">
				<span class="dashicons dashicons-admin-network" style="font-size: 24px; vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( '1. Στοιχεία API Γενικής Ταχυδρομικής', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>
			<p style="font-size: 13px; color: #666; margin-bottom: 15px;">
				<?php esc_html_e( 'Συμπληρώστε τα διαπιστευτήρια πρόσβασης που σας έχουν δοθεί από τη Γενική Ταχυδρομική για τη δημιουργία και διαχείριση voucher.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</p>

			<?php settings_errors(); ?>  
			<form method="POST" action="options.php">  
				<?php 
					settings_fields( 'gtvfw_settings' );
					do_settings_sections( 'gtvfw_settings' );
					submit_button( __( 'Αποθήκευση Στοιχείων API', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), 'primary' );
				?>             
			</form> 
		</div>

		<!-- BLOCK 2: Automated Email Ingestion -->
		<div class="card" style="max-width: 100%; width: 100%; padding: 20px 25px; margin: 0; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); box-sizing: border-box;">
			<h2 style="margin-top: 0; color: #135e96; font-size: 1.3em;">
				<span class="dashicons dashicons-email-alt" style="font-size: 24px; vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( '2. Αυτοματοποίηση Λήψης Email (Google Apps Script / Webhook / IMAP)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>

			<p style="font-size: 13px; line-height: 1.6; color: #555;">
				<?php esc_html_e( 'Το παρακάτω Webhook endpoint είναι ενιαίο: δέχεται αυτόματα τόσο τα αρχεία εκκαθαρίσεων αντικαταβολής (cod@taxydromiki.gr) όσο και τα τιμολόγια εξόδων (apostoli_timologion@taxydromiki.gr). Το plugin αναγνωρίζει το περιεχόμενο και δρομολογεί αυτόματα την αντίστοιχη επεξεργασία.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</p>

			<form method="POST" action="<?php echo esc_url( $gt_settings_url ); ?>" id="gt_hub_auto_form">
				<?php wp_nonce_field( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ); ?>

				<!-- Method Selector Radios -->
				<div style="background: #f0f7fc; border: 1px solid #b8daff; border-radius: 5px; padding: 12px 18px; margin: 15px 0;">
					<label style="font-weight: 600; display: block; margin-bottom: 8px; font-size: 13px; color: #005aa4;">
						<?php esc_html_e( 'Ενεργή Μέθοδος Αυτοματισμού:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</label>
					<div style="display: flex; gap: 20px; flex-wrap: wrap;">
						<label style="display: flex; align-items: center; gap: 7px; font-size: 13px; cursor: pointer;">
							<input type="radio" name="gt_cod_auto_method" value="webhook" <?php checked( $gt_auto_method, 'webhook' ); ?> onchange="gtToggleAutoHub('webhook');" />
							<strong><?php esc_html_e( 'Google Apps Script & Webhook (Προτεινόμενο για Gmail / Google Workspace)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
						</label>
						<label style="display: flex; align-items: center; gap: 7px; font-size: 13px; cursor: pointer;">
							<input type="radio" name="gt_cod_auto_method" value="imap" <?php checked( $gt_auto_method, 'imap' ); ?> onchange="gtToggleAutoHub('imap');" />
							<strong><?php esc_html_e( 'Διακομιστής Αλληλογραφίας IMAP (Απευθείας ανάγνωση)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
						</label>
						<label style="display: flex; align-items: center; gap: 7px; font-size: 13px; cursor: pointer;">
							<input type="radio" name="gt_cod_auto_method" value="disabled" <?php checked( $gt_auto_method, 'disabled' ); ?> onchange="gtToggleAutoHub('disabled');" />
							<span><?php esc_html_e( 'Απενεργοποιημένο (Μόνο Χειροκίνητη Φόρτωση)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></span>
						</label>
					</div>
				</div>

				<!-- Webhook Box -->
				<div id="gt_hub_panel_webhook" style="<?php echo 'webhook' === $gt_auto_method ? '' : 'display:none;'; ?>">
					<div style="background: #fdfdfd; border: 1px solid #ccd0d4; padding: 18px; border-radius: 4px; margin: 15px 0;">
						<div style="margin-bottom: 15px;">
							<label for="gt_webhook_url" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">
								<?php esc_html_e( 'Ενιαίο Webhook Endpoint URL:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</label>
							<div style="display: flex; gap: 8px;">
								<input type="text" id="gt_webhook_url" readonly value="<?php echo esc_url( $gt_webhook_url ); ?>" style="width: 100%; font-family: monospace; font-size: 13px; background: #f0f0f1;" />
								<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('gt_webhook_url').value); alert('Το Webhook URL αντιγράφηκε!');">
									<span class="dashicons dashicons-admin-page" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Αντιγραφή', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
							</div>
						</div>

						<div>
							<label for="gt_webhook_secret" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">
								<?php esc_html_e( 'Secret Key (Μυστικό Κλειδί Ασφαλείας):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</label>
							<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
								<input type="password" id="gt_webhook_secret" readonly value="<?php echo esc_attr( $gt_webhook_secret ); ?>" style="flex: 1; min-width: 250px; font-family: monospace; font-size: 13px; background: #f0f0f1;" />
								<button type="button" class="button" id="gt-btn-toggle-secret" onclick="var inp = document.getElementById('gt_webhook_secret'); if (inp.type === 'password') { inp.type = 'text'; this.innerText = 'Απόκρυψη'; } else { inp.type = 'password'; this.innerText = 'Εμφάνιση'; }">
									<?php esc_html_e( 'Εμφάνιση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
								<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('gt_webhook_secret').value); alert('Το Secret Key αντιγράφηκε!');">
									<span class="dashicons dashicons-admin-page" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Αντιγραφή', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
								<button type="submit" form="gt_regen_secret_form" name="gt_cod_regenerate_secret" class="button button-link-delete" style="font-size: 12px; margin-left: 5px;" onclick="return confirm('Είστε σίγουροι ότι θέλετε να δημιουργήσετε νέο Secret Key; Θα πρέπει να το ενημερώσετε και στο Google Apps Script.');">
									<?php esc_html_e( 'Δημιουργία Νέου Κλειδιού', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Last Logs Status Summary -->
					<div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px;">
						<div style="flex: 1; min-width: 260px; padding: 12px 16px; border-radius: 4px; <?php echo $gt_last_webhook_log ? 'background: #e7f5ea; border-left: 4px solid #46b450;' : 'background: #f6f7f7; border-left: 4px solid #b4b9be;'; ?>">
							<strong style="font-size: 12px;"><?php esc_html_e( 'Τελευταία Αυτόματη Αντικαταβολή (COD):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
							<?php if ( $gt_last_webhook_log ) : ?>
								<p style="margin: 4px 0 0 0; font-size: 12px;">
									<?php
									/* translators: 1: timestamp, 2: filename, 3: updated orders count, 4: total amount */
									printf( esc_html__( '%1$s | %2$s | %3$d παραγγελίες (%4$s €)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), esc_html( $gt_last_webhook_log['timestamp'] ), esc_html( $gt_last_webhook_log['filename'] ), (int) $gt_last_webhook_log['updated_orders'], esc_html( number_format( (float) $gt_last_webhook_log['total_amount'], 2, ',', '.' ) ) ); ?>
								</p>
							<?php else : ?>
								<p style="margin: 4px 0 0 0; font-size: 12px; color: #666;"><?php esc_html_e( 'Δεν υπάρχει καταγεγραμμένη εκτέλεση.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></p>
							<?php endif; ?>
						</div>
						<div style="flex: 1; min-width: 260px; padding: 12px 16px; border-radius: 4px; <?php echo $gt_last_invoice_log ? 'background: #e7f5ea; border-left: 4px solid #46b450;' : 'background: #f6f7f7; border-left: 4px solid #b4b9be;'; ?>">
							<strong style="font-size: 12px;"><?php esc_html_e( 'Τελευταίο Αυτόματο Τιμολόγιο (ΤΠΥ):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
							<?php if ( $gt_last_invoice_log ) : ?>
								<p style="margin: 4px 0 0 0; font-size: 12px;">
									<?php
									/* translators: 1: timestamp, 2: filename, 3: updated orders count, 4: total courier cost */
									printf( esc_html__( '%1$s | %2$s | %3$d παραγγελίες (Κόστος: %4$s €)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), esc_html( $gt_last_invoice_log['timestamp'] ), esc_html( $gt_last_invoice_log['filename'] ), (int) $gt_last_invoice_log['updated_orders'], esc_html( number_format( (float) $gt_last_invoice_log['total_courier'], 2, ',', '.' ) ) ); ?>
								</p>
							<?php else : ?>
								<p style="margin: 4px 0 0 0; font-size: 12px; color: #666;"><?php esc_html_e( 'Δεν υπάρχει καταγεγραμμένη εκτέλεση.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<!-- Google Apps Script Container -->
					<div style="background: #fdfdfd; border: 1px solid #ccd0d4; padding: 18px 22px; border-radius: 4px; margin: 15px 0; box-sizing: border-box;">
						<h3 style="margin-top: 0; color: #005aa4; font-size: 14px;">
							<span class="dashicons dashicons-google" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Ενιαίο Google Apps Script (Ένα copy-paste για όλα)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</h3>

						<p style="font-size: 13px; line-height: 1.5; color: #555; margin-bottom: 12px;">
							<?php esc_html_e( 'Χρησιμοποιήστε τον παρακάτω ενιαίο κώδικα στο Google Apps Script (script.google.com). Το script διαχειρίζεται αυτόματα και τους δύο τύπους email, χρησιμοποιώντας ξεχωριστές ετικέτες (labels) ώστε κάθε παραγγελία να επεξεργάζεται αξιόπιστα 2 φορές (μία για εξόφληση Α/Κ και μία για έλεγχο τιμολογίου):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</p>

						<ul style="margin: 0 0 15px 20px; font-size: 12px; list-style-type: disc; color: #444;">
							<li><strong><code>from:cod@taxydromiki.gr</code></strong> &rarr; <?php esc_html_e( 'Ετικέτα:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?> <span style="background: #e7f5ea; color: #155724; padding: 2px 6px; border-radius: 3px; font-weight: 600;">GT-COD-Processed</span></li>
							<li><strong><code>from:apostoli_timologion@taxydromiki.gr</code></strong> &rarr; <?php esc_html_e( 'Ετικέτα:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?> <span style="background: #e8f4fd; color: #005aa4; padding: 2px 6px; border-radius: 3px; font-weight: 600;">GT-Invoice-Processed</span></li>
						</ul>

						<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
								<strong style="font-size: 12px; color: #333;"><?php esc_html_e( 'Κώδικας Code.gs (Έτοιμος με το Webhook URL & Secret Key σας):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
								<button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('gt_unified_gas_code').value); alert('Ο ενιαίος κώδικας αντιγράφηκε επιτυχώς!');">
									<span class="dashicons dashicons-admin-page" style="vertical-align: middle;"></span>
									<?php esc_html_e( 'Αντιγραφή Κώδικα', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
							</div>

							<?php
							$gt_unified_gas_code = '/**
 * Ενιαίος Αυτοματισμός Γενικής Ταχυδρομικής για WooCommerce
 * Διαχειρίζεται:
 * 1. Αντικαταβολές: cod@taxydromiki.gr -> Ετικέτα: GT-COD-Processed
 * 2. Τιμολόγια: apostoli_timologion@taxydromiki.gr -> Ετικέτα: GT-Invoice-Processed
 */

const GT_CONFIG = {
  WEBHOOK_URL: "' . esc_url_raw( $gt_webhook_url ) . '",
  SECRET_KEY:  "' . esc_js( $gt_webhook_secret ) . '"
};

// Κεντρική συνάρτηση: Βάλτε Trigger να εκτελεί αυτήν ανά 1 ώρα
function syncAllGeniki() {
  syncGenikiCOD();
  syncGenikiInvoices();
}

// 1. Επεξεργασία Αντικαταβολών (απόδοση χρημάτων)
function syncGenikiCOD() {
  const searchQuery = "from:cod@taxydromiki.gr has:attachment -label:GT-COD-Processed";
  processEmailQuery(searchQuery, "GT-COD-Processed", "COD");
}

// 2. Επεξεργασία Τιμολογίων (έλεγχος κόστους μεταφορικών)
function syncGenikiInvoices() {
  const searchQuery = "from:apostoli_timologion@taxydromiki.gr has:attachment -label:GT-Invoice-Processed";
  processEmailQuery(searchQuery, "GT-Invoice-Processed", "Invoice");
}

// Βοηθητική μέθοδος ανάγνωσης & αποστολής στο WooCommerce
function processEmailQuery(query, labelName, typeName) {
  const threads = GmailApp.search(query, 0, 10);
  if (threads.length === 0) return;

  let label = GmailApp.getUserLabelByName(labelName);
  if (!label) {
    label = GmailApp.createLabel(labelName);
  }

  for (let t = 0; t < threads.length; t++) {
    const thread = threads[t];
    const messages = thread.getMessages();
    let threadHandled = false;

    for (let m = 0; m < messages.length; m++) {
      const message = messages[m];
      const attachments = message.getAttachments();

      for (let a = 0; a < attachments.length; a++) {
        const attachment = attachments[a];
        const name = attachment.getName().toLowerCase();

        if (name.endsWith(".csv") || name.endsWith(".txt")) {
          Logger.log(`[${typeName}] Αποστολή αρχείου: ${attachment.getName()}`);

          const payload = {
            filename: attachment.getName(),
            sender: message.getFrom(),
            content: Utilities.base64Encode(attachment.getBytes()),
            encoding: "base64",
            email_subject: message.getSubject(),
            email_date: message.getDate().toISOString()
          };

          const options = {
            method: "post",
            contentType: "application/json",
            headers: { "X-GT-Secret": GT_CONFIG.SECRET_KEY },
            payload: JSON.stringify(payload),
            muteHttpExceptions: true
          };

          try {
            const response = UrlFetchApp.fetch(GT_CONFIG.WEBHOOK_URL, options);
            Logger.log(`[${typeName}] Απάντηση server (${response.getResponseCode()}): ${response.getContentText()}`);
            threadHandled = true;
          } catch (err) {
            Logger.log(`[${typeName}] Σφάλμα: ${err.toString()}`);
          }
        }
      }
    }

    if (threadHandled) {
      thread.addLabel(label);
    }
  }
}';
							?>

							<textarea id="gt_unified_gas_code" readonly rows="12" style="width: 100%; font-family: monospace; font-size: 11px; background: #f0f0f1; border-radius: 4px; padding: 10px;"><?php echo esc_textarea( $gt_unified_gas_code ); ?></textarea>

							<div style="margin-top: 12px; font-size: 12px; line-height: 1.5; color: #666;">
								<strong><?php esc_html_e( 'Οδηγίες εγκατάστασης στο Google Apps Script (1 λεπτό):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
								<ol style="margin: 5px 0 0 18px;">
									<li>
									<?php
									printf(
										wp_kses(
											/* translators: %s: Google Apps Script URL */
											__( 'Ανοίξτε το <a href="%s" target="_blank">Google Apps Script (script.google.com)</a> με το Google λογαριασμό του καταστήματος.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
											array(
												'a' => array(
													'href'   => array(),
													'target' => array(),
												),
											)
										),
										'https://script.google.com/'
									);
									?>
								</li>
									<li><?php esc_html_e( 'Δημιουργήστε ένα "Νέο έργο" (New Project) με όνομα "Geniki Taxydromiki Sync".', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
									<li><?php esc_html_e( 'Επικολλήστε τον παραπάνω κώδικα στο αρχείο Code.gs και πατήστε Αποθήκευση (Ctrl+S).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
									<li><?php esc_html_e( 'Κάντε κλικ στο εικονίδιο Triggers (Ρολόι αριστερά) &rarr; "Προσθήκη Trigger" &rarr; Επιλέξτε συνάρτηση "syncAllGeniki" &rarr; Επιλέξτε "Time-driven" (ανά 1 ώρα). Αυτό ήταν!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
								</ol>
							</div>
						</div>
					</div>
				</div>

				<!-- IMAP Settings Panel -->
				<div id="gt_hub_panel_imap" style="<?php echo 'imap' === $gt_auto_method ? '' : 'display:none;'; ?>">
					<div style="background: #fdfdfd; border: 1px solid #ccd0d4; padding: 18px 22px; border-radius: 4px; margin: 15px 0; box-sizing: border-box;">
						<h3 style="margin-top: 0; color: #23282d; font-size: 14px;">
							<span class="dashicons dashicons-email" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Στοιχεία Σύνδεσης IMAP Mail Server', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</h3>

						<table class="form-table" style="margin-top: 0; width: 100%; table-layout: auto;">
							<tbody>
								<tr>
									<th scope="row" style="width: 260px; min-width: 230px;"><label for="imap_host"><?php esc_html_e( 'Διακομιστής (Host):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label></th>
									<td><input type="text" id="imap_host" name="imap_host" value="<?php echo esc_attr( $gt_imap_settings['host'] ); ?>" class="regular-text" style="width: 100%; max-width: 440px;" placeholder="imap.gmail.com" /></td>
								</tr>
								<tr>
									<th scope="row" style="width: 260px; min-width: 230px;"><label for="imap_port"><?php esc_html_e( 'Θύρα (Port) & Κρυπτογράφηση:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label></th>
									<td>
										<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
											<input type="number" id="imap_port" name="imap_port" value="<?php echo esc_attr( $gt_imap_settings['port'] ); ?>" style="width: 85px;" />
											<select name="imap_encryption" id="imap_encryption" style="min-width: 160px;">
												<option value="ssl" <?php selected( $gt_imap_settings['encryption'], 'ssl' ); ?>>SSL / TLS (993)</option>
												<option value="tls" <?php selected( $gt_imap_settings['encryption'], 'tls' ); ?>>STARTTLS (143)</option>
												<option value="none" <?php selected( $gt_imap_settings['encryption'], 'none' ); ?>>None (143)</option>
											</select>
										</div>
									</td>
								</tr>
								<tr>
									<th scope="row" style="width: 260px; min-width: 230px;"><label for="imap_username"><?php esc_html_e( 'Όνομα Χρήστη / Email:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label></th>
									<td><input type="text" id="imap_username" name="imap_username" value="<?php echo esc_attr( $gt_imap_settings['username'] ); ?>" class="regular-text" style="width: 100%; max-width: 440px;" placeholder="info@example.gr" /></td>
								</tr>
								<tr>
									<th scope="row" style="width: 260px; min-width: 230px;"><label for="imap_password"><?php esc_html_e( 'Κωδικός Πρόσβασης:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label></th>
									<td>
										<input type="password" id="imap_password" name="imap_password" value="<?php echo esc_attr( $gt_imap_settings['password'] ); ?>" class="regular-text" style="width: 100%; max-width: 440px;" />
										<p class="description"><?php esc_html_e( 'Για λογαριασμούς Gmail με 2FA, χρησιμοποιήστε App Password.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row" style="width: 260px; min-width: 230px;"><label for="imap_folder"><?php esc_html_e( 'Φάκελος (Folder):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label></th>
									<td><input type="text" id="imap_folder" name="imap_folder" value="<?php echo esc_attr( $gt_imap_settings['folder'] ); ?>" class="regular-text" style="width: 100%; max-width: 440px;" placeholder="INBOX" /></td>
								</tr>
								<tr>
									<th scope="row" style="width: 260px; min-width: 230px;"><label for="imap_schedule"><?php esc_html_e( 'Συχνότητα Ελέγχου (WP-Cron):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label></th>
									<td>
										<select name="imap_schedule" id="imap_schedule" style="min-width: 220px;">
											<option value="hourly" <?php selected( $gt_imap_settings['schedule'], 'hourly' ); ?>><?php esc_html_e( 'Κάθε 1 Ώρα', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
											<option value="twicedaily" <?php selected( $gt_imap_settings['schedule'], 'twicedaily' ); ?>><?php esc_html_e( 'Δύο Φορές την Ημέρα (Κάθε 12 Ώρες)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
											<option value="daily" <?php selected( $gt_imap_settings['schedule'], 'daily' ); ?>><?php esc_html_e( 'Μία Φορά την Ημέρα (Κάθε 24 Ώρες)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
										</select>
									</td>
								</tr>
							</tbody>
						</table>

						<div style="margin-top: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
							<button type="submit" name="gt_cod_test_imap" class="button button-secondary">
								<span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Δοκιμή Σύνδεσης IMAP', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</button>
							<button type="submit" name="gt_cod_check_imap_now" class="button button-secondary">
								<span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Άμεσος Έλεγχος & Συγχρονισμός Τώρα', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</button>
						</div>
					</div>
				</div>

				<div style="margin-top: 15px;">
					<button type="submit" name="gt_cod_save_auto_settings" class="button button-primary button-large">
						<span class="dashicons dashicons-saved" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'Αποθήκευση Ρυθμίσεων Αυτοματισμού', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</button>
				</div>
			</form>
		</div>

		
	</div>
</div>

<script>
function gtToggleAutoHub(method) {
	var pWebhook = document.getElementById('gt_hub_panel_webhook');
	var pImap    = document.getElementById('gt_hub_panel_imap');
	if (pWebhook) pWebhook.style.display = (method === 'webhook') ? 'block' : 'none';
	if (pImap)    pImap.style.display    = (method === 'imap') ? 'block' : 'none';
}
</script>
