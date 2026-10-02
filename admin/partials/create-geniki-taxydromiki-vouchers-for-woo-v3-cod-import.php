<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin view for Geniki Taxydromiki COD Payments Import.
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin/partials
 */

$action_step = 'upload';
$notice      = null;
$records     = array();
$processed   = array();

// 0. Handle Secret Key Regeneration
if ( isset( $_POST['gt_cod_regenerate_secret'] ) && check_admin_referer( 'gt_cod_regen_secret_nonce', 'gt_cod_regen_secret_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$new_secret = wp_generate_password( 32, false );
		update_option( 'gtvfw_cod_webhook_secret', $new_secret );
		$notice = array(
			'type'    => 'success',
			'message' => __( 'Δημιουργήθηκε νέο Secret Key για το Webhook επιτυχώς!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	}
}

// 0b. Handle Automation Settings Save
if ( isset( $_POST['gt_cod_save_auto_settings'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$method = isset( $_POST['gt_cod_auto_method'] ) ? sanitize_text_field( $_POST['gt_cod_auto_method'] ) : 'webhook';
		update_option( 'gtvfw_cod_auto_method', $method );

		$existing_imap = GT_COD_Importer::get_imap_settings();
		$imap_settings = array(
			'host'          => isset( $_POST['imap_host'] ) ? sanitize_text_field( $_POST['imap_host'] ) : $existing_imap['host'],
			'port'          => isset( $_POST['imap_port'] ) ? intval( $_POST['imap_port'] ) : $existing_imap['port'],
			'encryption'    => isset( $_POST['imap_encryption'] ) ? sanitize_text_field( $_POST['imap_encryption'] ) : $existing_imap['encryption'],
			'username'      => isset( $_POST['imap_username'] ) ? sanitize_text_field( $_POST['imap_username'] ) : $existing_imap['username'],
			'password'      => ! empty( $_POST['imap_password'] ) ? sanitize_text_field( $_POST['imap_password'] ) : $existing_imap['password'],
			'folder'        => isset( $_POST['imap_folder'] ) ? sanitize_text_field( $_POST['imap_folder'] ) : $existing_imap['folder'],
			'sender_filter' => isset( $_POST['imap_sender_filter'] ) ? sanitize_text_field( $_POST['imap_sender_filter'] ) : $existing_imap['sender_filter'],
			'schedule'      => isset( $_POST['imap_schedule'] ) ? sanitize_text_field( $_POST['imap_schedule'] ) : $existing_imap['schedule'],
		);
		GT_COD_Importer::save_imap_settings( $imap_settings );

		$notice = array(
			'type'    => 'success',
			'message' => __( 'Οι ρυθμίσεις αυτόματης εισαγωγής αποθηκεύτηκαν επιτυχώς!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	}
}

// 0c. Handle IMAP Connection Test
if ( isset( $_POST['gt_cod_test_imap'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$existing_imap = GT_COD_Importer::get_imap_settings();
		$temp_settings = array(
			'host'          => isset( $_POST['imap_host'] ) ? sanitize_text_field( $_POST['imap_host'] ) : $existing_imap['host'],
			'port'          => isset( $_POST['imap_port'] ) ? intval( $_POST['imap_port'] ) : $existing_imap['port'],
			'encryption'    => isset( $_POST['imap_encryption'] ) ? sanitize_text_field( $_POST['imap_encryption'] ) : $existing_imap['encryption'],
			'username'      => isset( $_POST['imap_username'] ) ? sanitize_text_field( $_POST['imap_username'] ) : $existing_imap['username'],
			'password'      => ! empty( $_POST['imap_password'] ) ? sanitize_text_field( $_POST['imap_password'] ) : $existing_imap['password'],
			'folder'        => isset( $_POST['imap_folder'] ) ? sanitize_text_field( $_POST['imap_folder'] ) : $existing_imap['folder'],
		);
		$res = GT_COD_Importer::test_imap_connection( $temp_settings );
		if ( is_wp_error( $res ) ) {
			$notice = array(
				'type'    => 'error',
				'message' => $res->get_error_message(),
			);
		} else {
			$notice = array(
				'type'    => 'success',
				'message' => $res['message'],
			);
		}
	}
}

// 0d. Handle Manual IMAP Check & Sync Now
if ( isset( $_POST['gt_cod_check_imap_now'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$res = GT_COD_Importer::fetch_and_process_imap_emails();
		if ( is_wp_error( $res ) ) {
			$notice = array(
				'type'    => 'error',
				'message' => sprintf( __( 'Σφάλμα ανάγνωσης IMAP: %s', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $res->get_error_message() ),
			);
		} else {
			$notice = array(
				'type'    => 'success',
				'message' => sprintf(
					__( 'Ολοκληρώθηκε ο έλεγχος IMAP! Επεξεργάστηκαν %1$d μηνύματα (%2$d αρχεία) και ενημερώθηκαν %3$d παραγγελίες (Σύνολο: %4$s €).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					(int) $res['messages_processed'],
					(int) $res['files_processed'],
					(int) $res['updated_orders'],
					number_format( (float) $res['total_amount'], 2, ',', '.' )
				),
			);
		}
	}
}

// 1. Process Order Updates Confirmation
if ( isset( $_POST['gt_cod_confirm_import'] ) && check_admin_referer( 'gt_cod_confirm_nonce', 'gt_cod_confirm_nonce_field' ) ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
		wp_die( esc_html__( 'Δεν έχετε δικαίωμα εκτέλεσης αυτής της ενέργειας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
	}

	$selected_rows = isset( $_POST['selected_rows'] ) ? (array) $_POST['selected_rows'] : array();
	$raw_data      = isset( $_POST['records_payload'] ) ? wp_unslash( $_POST['records_payload'] ) : '';
	$all_records   = json_decode( $raw_data, true );

	if ( ! empty( $all_records ) && is_array( $all_records ) ) {
		$action_step = 'completed';
		$updated_count = 0;
		$skipped_count = 0;

		foreach ( $all_records as $idx => $record ) {
			if ( in_array( (string) $idx, $selected_rows, true ) && ! empty( $record['order_id'] ) ) {
				$res = GT_COD_Importer::mark_order_cod_paid( $record['order_id'], $record );
				if ( true === $res ) {
					$updated_count++;
					$processed[] = array(
						'order_id'   => $record['order_id'],
						'voucher'    => $record['voucher'],
						'amount'     => $record['amount'],
						'status'     => 'success',
						'message'    => __( 'Ενημερώθηκε επιτυχώς', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					);
				} else {
					$skipped_count++;
					$processed[] = array(
						'order_id'   => $record['order_id'],
						'voucher'    => $record['voucher'],
						'amount'     => $record['amount'],
						'status'     => 'error',
						'message'    => is_wp_error( $res ) ? $res->get_error_message() : __( 'Σφάλμα ενημέρωσης', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					);
				}
			}
		}

		$notice = array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: %d: number of orders updated */
				__( 'Ολοκληρώθηκε η διαδικασία! Ενημερώθηκαν επιτυχώς %d παραγγελίες.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				$updated_count
			),
		);
	}
}

// 2. Process File Upload & Preview
elseif ( isset( $_POST['gt_cod_upload_file'] ) && check_admin_referer( 'gt_cod_upload_nonce', 'gt_cod_upload_nonce_field' ) ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
		wp_die( esc_html__( 'Δεν έχετε δικαίωμα εκτέλεσης αυτής της ενέργειας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
	}

	if ( empty( $_FILES['cod_csv_file']['tmp_name'] ) ) {
		$notice = array(
			'type'    => 'error',
			'message' => __( 'Παρακαλούμε επιλέξτε ένα έγκυρο αρχείο CSV.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	} else {
		$file_tmp = $_FILES['cod_csv_file']['tmp_name'];
		$content  = GT_COD_Importer::read_file_content( $file_tmp );

		if ( is_wp_error( $content ) ) {
			$notice = array(
				'type'    => 'error',
				'message' => $content->get_error_message(),
			);
		} else {
			$raw_records = GT_COD_Importer::parse_csv_content( $content );
			if ( is_wp_error( $raw_records ) ) {
				$notice = array(
					'type'    => 'error',
					'message' => $raw_records->get_error_message(),
				);
			} elseif ( empty( $raw_records ) ) {
				$notice = array(
					'type'    => 'error',
					'message' => __( 'Δεν βρέθηκαν γραμμές αποστολών στο αρχείο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				);
			} else {
				$records     = GT_COD_Importer::match_records( $raw_records );
				$action_step = 'preview';
			}
		}
	}
}

$settings_url   = admin_url( 'admin.php?page=gtvfw_settings' );
$cod_import_url = admin_url( 'admin.php?page=gtvfw_cod_import' );
?>

<div class="wrap gtvfw-admin-wrapper">
	<h1><?php esc_html_e( 'Γενική Ταχυδρομική - Διαχείριση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
		<a href="<?php echo esc_url( $settings_url ); ?>" class="nav-tab">
			<?php esc_html_e( 'Ρυθμίσεις Σύνδεσης', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
		<a href="<?php echo esc_url( $cod_import_url ); ?>" class="nav-tab nav-tab-active">
			<?php esc_html_e( 'Εισαγωγή Πληρωμών Αντικαταβολής (COD)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
	</nav>

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><strong><?php echo esc_html( $notice['message'] ); ?></strong></p>
		</div>
	<?php endif; ?>

	<!-- Step 1: Upload Form -->
	<?php if ( 'upload' === $action_step ) : ?>
		<div class="card" style="max-width: 800px; padding: 25px; margin-top: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h2 style="margin-top: 0; color: #005aa4; font-size: 1.3em;">
				<span class="dashicons dashicons-upload" style="font-size: 26px; vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( 'Μεταφόρτωση Αρχείου Αντικαταβολών Γενικής Ταχυδρομικής', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>
			<p style="font-size: 14px; line-height: 1.6; color: #555;">
				<?php esc_html_e( 'Επιλέξτε το αρχείο CSV που σας αποστέλλει περιοδικά η Γενική Ταχυδρομική μέσω email με τις εκκαθαρίσεις των αντικαταβολών σας (π.χ. TAXYDR...csv).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</p>

			<div style="background: #f0f7fc; border-left: 4px solid #005aa4; padding: 12px 16px; margin: 15px 0; border-radius: 2px;">
				<p style="margin: 0; font-size: 13px;">
					<strong><?php esc_html_e( 'Υποστηριζόμενα Χαρακτηριστικά:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
					<ul style="margin: 5px 0 0 18px; font-size: 13px; list-style-type: disc;">
						<li><?php esc_html_e( 'Αυτόματη αναγνώριση κωδικοποίησης (UTF-16LE, Windows-1253, UTF-8 με BOM).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
						<li><?php esc_html_e( 'Αναζήτηση παραγγελίας βάσει Αριθμού Voucher (Αποδεικτικό) ή Αριθμού Παραγγελίας (#Order ID).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
						<li><?php esc_html_e( 'Προεπισκόπηση και έλεγχος ποσών πριν την εφαρμογή.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
						<li><?php esc_html_e( 'Καταχώρηση εσωτερικής σημείωσης & metadata για ασφαλή ιχνηλασιμότητα.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
					</ul>
				</p>
			</div>

			<form method="POST" enctype="multipart/form-data" action="<?php echo esc_url( $cod_import_url ); ?>" style="margin-top: 20px;">
				<?php wp_nonce_field( 'gt_cod_upload_nonce', 'gt_cod_upload_nonce_field' ); ?>
				
				<div style="padding: 20px; border: 2px dashed #b4b9be; background: #fafafa; text-align: center; border-radius: 4px; margin-bottom: 20px;">
					<label for="cod_csv_file" style="display: block; font-weight: 600; margin-bottom: 10px; font-size: 14px;">
						<?php esc_html_e( 'Επιλογή αρχείου (.csv, .txt):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</label>
					<input type="file" name="cod_csv_file" id="cod_csv_file" accept=".csv,.txt,.tsv" required style="font-size: 14px;" />
				</div>

				<button type="submit" name="gt_cod_upload_file" class="button button-primary button-hero" style="background: #005aa4; border-color: #00457d;">
					<span class="dashicons dashicons-search" style="vertical-align: middle; margin-top: -2px;"></span>
					<?php esc_html_e( 'Ανάλυση & Προεπισκόπηση Αρχείου', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</button>
			</form>
		</div>

		<!-- Card 2: Automated Email Ingestion (Choice between Webhook & IMAP) -->
		<?php
		$auto_method      = GT_COD_Importer::get_auto_method();
		$webhook_url      = rest_url( 'gtvfw/v1/cod-webhook' );
		$webhook_secret   = GT_COD_Importer::get_webhook_secret();
		$last_webhook_log = get_option( 'gtvfw_cod_webhook_last_log' );
		$imap_settings    = GT_COD_Importer::get_imap_settings();
		$last_imap_log    = get_option( 'gtvfw_cod_imap_last_log' );
		$imap_next_cron   = wp_next_scheduled( 'gtvfw_cod_imap_cron_check' );
		?>
		<div class="card" style="max-width: 820px; padding: 25px; margin-top: 25px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h2 style="margin-top: 0; color: #135e96; font-size: 1.3em;">
				<span class="dashicons dashicons-email-alt" style="font-size: 26px; vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( 'Αυτόματη Εισαγωγή Εκκαθαρίσεων Αντικαταβολών από Email', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>

			<p style="font-size: 14px; line-height: 1.6; color: #555;">
				<?php esc_html_e( 'Επιλέξτε τη μέθοδο αυτοματισμού που προτιμάτε για την παραλαβή και αυτόματη ενημέρωση των πληρωμών αντικαταβολής από τη Γενική Ταχυδρομική.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</p>

			<form method="POST" action="<?php echo esc_url( $cod_import_url ); ?>" id="gt_cod_auto_form">
				<?php wp_nonce_field( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ); ?>

				<!-- Method Selector Radios -->
				<div style="background: #f0f7fc; border: 1px solid #b8daff; border-radius: 5px; padding: 15px 20px; margin: 15px 0 20px 0;">
					<label style="font-weight: 600; display: block; margin-bottom: 10px; font-size: 13px; color: #005aa4;">
						<?php esc_html_e( 'Επιλογή Μεθόδου Αυτοματισμού:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</label>
					<div style="display: flex; gap: 20px; flex-wrap: wrap;">
						<label style="display: flex; align-items: center; gap: 7px; font-size: 13px; cursor: pointer;">
							<input type="radio" name="gt_cod_auto_method" value="webhook" <?php checked( $auto_method, 'webhook' ); ?> onchange="gtToggleAutoMethod('webhook');" />
							<strong><?php esc_html_e( 'Google Apps Script & Webhook (Προτεινόμενο για Gmail / Google Workspace)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
						</label>
						<label style="display: flex; align-items: center; gap: 7px; font-size: 13px; cursor: pointer;">
							<input type="radio" name="gt_cod_auto_method" value="imap" <?php checked( $auto_method, 'imap' ); ?> onchange="gtToggleAutoMethod('imap');" />
							<strong><?php esc_html_e( 'Διακομιστής Αλληλογραφίας IMAP (Απευθείας ανάγνωση)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
						</label>
						<label style="display: flex; align-items: center; gap: 7px; font-size: 13px; cursor: pointer;">
							<input type="radio" name="gt_cod_auto_method" value="disabled" <?php checked( $auto_method, 'disabled' ); ?> onchange="gtToggleAutoMethod('disabled');" />
							<span><?php esc_html_e( 'Απενεργοποιημένο (Μόνο Χειροκίνητη Φόρτωση)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></span>
						</label>
					</div>
				</div>

				<!-- Panel 1: Google Apps Script & Webhook -->
				<div id="gt_panel_webhook" style="<?php echo 'webhook' === $auto_method ? '' : 'display:none;'; ?>">
					<!-- Webhook Credentials Box -->
					<div style="background: #fdfdfd; border: 1px solid #ccd0d4; padding: 18px; border-radius: 4px; margin: 15px 0;">
						<div style="margin-bottom: 15px;">
							<label for="gt_webhook_url" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">
								<?php esc_html_e( 'Webhook Endpoint URL:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</label>
							<div style="display: flex; gap: 8px;">
								<input type="text" id="gt_webhook_url" readonly value="<?php echo esc_url( $webhook_url ); ?>" style="width: 100%; font-family: monospace; font-size: 13px; background: #f0f0f1;" />
								<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('gt_webhook_url').value); alert('Το Webhook URL αντιγράφηκε!');">
									<span class="dashicons dashicons-admin-page" style="vertical-align: middle; margin-top: -2px;"></span>
									<?php esc_html_e( 'Αντιγραφή', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
							</div>
						</div>

						<div style="margin-bottom: 10px;">
							<label for="gt_webhook_secret" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">
								<?php esc_html_e( 'Secret Key (Μυστικό Κλειδί Ασφαλείας):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</label>
							<div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
								<input type="password" id="gt_webhook_secret" readonly value="<?php echo esc_attr( $webhook_secret ); ?>" style="flex: 1; min-width: 250px; font-family: monospace; font-size: 13px; background: #f0f0f1;" />
								<button type="button" class="button" id="gt-btn-toggle-secret" onclick="var inp = document.getElementById('gt_webhook_secret'); if (inp.type === 'password') { inp.type = 'text'; this.innerText = 'Απόκρυψη'; } else { inp.type = 'password'; this.innerText = 'Εμφάνιση'; }">
									<?php esc_html_e( 'Εμφάνιση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
								<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('gt_webhook_secret').value); alert('Το Secret Key αντιγράφηκε!');">
									<span class="dashicons dashicons-admin-page" style="vertical-align: middle; margin-top: -2px;"></span>
									<?php esc_html_e( 'Αντιγραφή', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
								<button type="submit" form="gt_regen_secret_form" name="gt_cod_regenerate_secret" class="button button-link-delete" style="font-size: 12px; margin-left: 5px;" onclick="return confirm('Είστε σίγουροι ότι θέλετε να δημιουργήσετε νέο Secret Key; Θα πρέπει να το ενημερώσετε και στο Google Apps Script.');">
									<?php esc_html_e( 'Δημιουργία Νέου Κλειδιού', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Last Execution Status -->
					<div style="padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; <?php echo $last_webhook_log ? 'background: #e7f5ea; border-left: 4px solid #46b450;' : 'background: #f6f7f7; border-left: 4px solid #b4b9be;'; ?>">
						<strong style="font-size: 13px;"><?php esc_html_e( 'Κατάσταση Τελευταίας Αυτόματης Εισαγωγής (Webhook):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
						<?php if ( $last_webhook_log ) : ?>
							<p style="margin: 5px 0 0 0; font-size: 13px;">
								<?php
								printf(
									/* translators: 1: date, 2: filename, 3: updated orders, 4: total amount */
									esc_html__( 'Ημερομηνία: %1$s | Αρχείο: %2$s | Ενημερώθηκαν: %3$d παραγγελίες (Σύνολο: %4$s €).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
									esc_html( $last_webhook_log['timestamp'] ),
									esc_html( $last_webhook_log['filename'] ),
									(int) $last_webhook_log['updated_orders'],
									esc_html( number_format( (float) $last_webhook_log['total_amount'], 2, ',', '.' ) )
								);
								?>
								<?php if ( ! empty( $last_webhook_log['already_paid'] ) ) : ?>
									<br><span style="color: #666; font-size: 12px;"><?php printf( esc_html__( 'Παραλείφθηκαν %d ήδη εξοφλημένες εγγραφές.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), (int) $last_webhook_log['already_paid'] ); ?></span>
								<?php endif; ?>
							</p>
						<?php else : ?>
							<p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
								<?php esc_html_e( 'Δεν έχει καταγραφεί ακόμα αυτόματη εκτέλεση webhook.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<!-- Collapsible Google Apps Script Instructions -->
					<details style="background: #f9f9f9; padding: 15px 20px; border-radius: 4px; border: 1px solid #ccd0d4; margin-top: 15px;" open>
						<summary style="font-weight: 600; cursor: pointer; color: #005aa4; font-size: 14px; outline: none;">
							<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 4px;"></span>
							<?php esc_html_e( 'Οδηγίες Ρύθμισης στο Google Workspace (script.google.com)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</summary>

						<div style="margin-top: 15px; font-size: 13px; line-height: 1.6;">
							<ol style="margin-left: 20px;">
								<li><?php echo sprintf( __( 'Συνδεθείτε στο λογαριασμό σας <strong>info@odosermou.gr</strong> και ανοίξτε το <a href="%s" target="_blank">Google Apps Script (script.google.com)</a>.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), 'https://script.google.com/' ); ?></li>
								<li><?php esc_html_e( 'Κάντε κλικ στο κουμπί "Νέο έργο" (New project) και ονομάστε το: "Geniki Taxydromiki COD Sync".', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
								<li><?php esc_html_e( 'Αντικαταστήστε όλο το περιεχόμενο του αρχείου Code.gs με τον παρακάτω προ-ρυθμισμένο κώδικα:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
							</ol>

							<?php
							$script_template = 'function syncGenikiCODToWooCommerce() {
  const WEBHOOK_URL = "' . esc_url_raw( $webhook_url ) . '";
  const SECRET_KEY  = "' . esc_js( $webhook_secret ) . '";

  // Search emails from cod@taxydromiki.gr with attachments that haven\'t been tagged with COD-Processed yet
  const searchQuery = "from:cod@taxydromiki.gr has:attachment -label:COD-Processed";
  const threads = GmailApp.search(searchQuery, 0, 10);

  let processedCount = 0;
  let label = GmailApp.getUserLabelByName("COD-Processed");
  if (!label) {
    label = GmailApp.createLabel("COD-Processed");
  }

  for (let t = 0; t < threads.length; t++) {
    const thread = threads[t];
    const messages = thread.getMessages();
    let threadUpdated = false;

    for (let m = 0; m < messages.length; m++) {
      const message = messages[m];

      // Ensure sender is specifically cod@taxydromiki.gr (ignore invoices from apostoli_timologion@taxydromiki.gr)
      const from = message.getFrom().toLowerCase();
      if (!from.includes("cod@taxydromiki.gr")) {
        Logger.log("Skipping non-COD email from: " + message.getFrom());
        continue;
      }

      const attachments = message.getAttachments();
      for (let a = 0; a < attachments.length; a++) {
        const attachment = attachments[a];
        const name = attachment.getName().toLowerCase();

        // Skip invoice files (ΤΠΥ) and ensure valid extension
        if (name.includes("τπυ") || name.includes("tpy") || name.includes("timolog") || name.includes("invoice")) {
          Logger.log("Skipping invoice attachment: " + attachment.getName());
          continue;
        }

        if (name.endsWith(".csv") || name.endsWith(".txt") || name.endsWith(".tsv")) {
          Logger.log("Processing COD file: " + attachment.getName());

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
            headers: {
              "X-GT-Secret": SECRET_KEY
            },
            payload: JSON.stringify(payload),
            muteHttpExceptions: true
          };

          const response = UrlFetchApp.fetch(WEBHOOK_URL, options);
          const code = response.getResponseCode();
          const text = response.getContentText();

          Logger.log("Response (" + code + "): " + text);

          if (code === 200) {
            const res = JSON.parse(text);
            if (res.success) {
              message.markRead();
              threadUpdated = true;
              processedCount++;
            }
          }
        }
      }
    }

    // Tag thread as processed so it is never checked again
    if (threadUpdated) {
      thread.addLabel(label);
    }
  }
  Logger.log("Finished. Processed " + processedCount + " files.");
}';
							?>

							<div style="position: relative; margin: 15px 0;">
								<textarea id="gt_gas_code" readonly style="width: 100%; height: 260px; font-family: Consolas, Monaco, monospace; font-size: 12px; background: #282c34; color: #abb2bf; padding: 12px; border-radius: 4px; box-sizing: border-box;"><?php echo esc_textarea( $script_template ); ?></textarea>
								<button type="button" class="button button-primary" style="margin-top: 6px;" onclick="navigator.clipboard.writeText(document.getElementById('gt_gas_code').value); alert('Ο κώδικας Google Apps Script αντιγράφηκε στο πρόχειρο!');">
									<span class="dashicons dashicons-clipboard" style="vertical-align: middle; margin-top: -2px;"></span>
									<?php esc_html_e( 'Αντιγραφή Κώδικα Google Apps Script', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
								</button>
							</div>

							<ol start="4" style="margin-left: 20px;">
								<li><?php esc_html_e( 'Κάντε κλικ στο "Αποθήκευση" (Save) και πατήστε "Εκτέλεση" (Run) μία φορά για να δώσετε έγκριση ανάγνωσης των μηνυμάτων Gmail.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
								<li><?php esc_html_e( 'Στο αριστερό μενού επιλέξτε "Ενάρξεις" (Triggers - εικονίδιο ρολογιού) > "Προσθήκη έναρξης" (Add Trigger):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
									<ul style="margin: 4px 0 0 15px; list-style-type: disc;">
										<li><?php esc_html_e( 'Επιλέξτε συνάρτηση: syncGenikiCODToWooCommerce', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
										<li><?php esc_html_e( 'Πηγή συμβάντος: Βάσει χρόνου (Time-driven)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
										<li><?php esc_html_e( 'Τύπος: Χρονόμετρο λεπτών ή ωρών (π.χ. Κάθε 15 ή 30 λεπτά)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
									</ul>
								</li>
							</ol>
						</div>
					</details>

					<div style="margin-top: 20px;">
						<button type="submit" name="gt_cod_save_auto_settings" class="button button-primary button-hero" style="background: #005aa4; border-color: #00457d;">
							<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span>
							<?php esc_html_e( 'Αποθήκευση Επιλογής Μεθόδου', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</button>
					</div>
				</div>

				<!-- Panel 2: Direct IMAP Mail Server -->
				<div id="gt_panel_imap" style="<?php echo 'imap' === $auto_method ? '' : 'display:none;'; ?>">
					<div style="background: #fafafa; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
						<h3 style="margin-top: 0; color: #1d2327; font-size: 14px; border-bottom: 1px solid #e2e4e7; padding-bottom: 8px;">
							<?php esc_html_e( 'Στοιχεία Σύνδεσης Διακομιστή IMAP', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</h3>

						<table class="form-table" style="margin-top: 0;">
							<tr>
								<th scope="row" style="width: 220px;">
									<label for="imap_host"><?php esc_html_e( 'Διακομιστής IMAP (Host):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label>
								</th>
								<td>
									<input type="text" id="imap_host" name="imap_host" class="regular-text" value="<?php echo esc_attr( $imap_settings['host'] ); ?>" placeholder="imap.gmail.com" />
									<p class="description"><?php esc_html_e( 'Για Gmail / Google Workspace: imap.gmail.com', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="imap_port"><?php esc_html_e( 'Θύρα (Port) & Κρυπτογράφηση:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label>
								</th>
								<td>
									<input type="number" id="imap_port" name="imap_port" style="width: 80px;" value="<?php echo esc_attr( $imap_settings['port'] ); ?>" />
									<select name="imap_encryption" style="margin-left: 10px;">
										<option value="ssl" <?php selected( $imap_settings['encryption'], 'ssl' ); ?>>SSL / TLS (θύρα 993)</option>
										<option value="tls" <?php selected( $imap_settings['encryption'], 'tls' ); ?>>STARTTLS (θύρα 143)</option>
										<option value="none" <?php selected( $imap_settings['encryption'], 'none' ); ?>><?php esc_html_e( 'Χωρίς Κρυπτογράφηση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="imap_username"><?php esc_html_e( 'Όνομα Χρήστη / Email:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label>
								</th>
								<td>
									<input type="text" id="imap_username" name="imap_username" class="regular-text" value="<?php echo esc_attr( $imap_settings['username'] ); ?>" placeholder="info@odosermou.gr" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="imap_password"><?php esc_html_e( 'Κωδικός Πρόσβασης:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label>
								</th>
								<td>
									<div style="display: flex; gap: 8px; align-items: center; max-width: 350px;">
										<input type="password" id="imap_password" name="imap_password" class="regular-text" value="<?php echo esc_attr( $imap_settings['password'] ); ?>" placeholder="<?php echo ! empty( $imap_settings['password'] ) ? '••••••••••••••••' : ''; ?>" />
										<button type="button" class="button" onclick="var p = document.getElementById('imap_password'); p.type = (p.type === 'password') ? 'text' : 'password'; this.innerText = (p.type === 'password') ? 'Εμφάνιση' : 'Απόκρυψη';">
											<?php esc_html_e( 'Εμφάνιση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
										</button>
									</div>
									<p class="description">
										<?php esc_html_e( 'Για Google Workspace / Gmail με 2-Step Verification, χρησιμοποιήστε έναν "Κωδικό πρόσβασης εφαρμογής" (Google App Password).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="imap_folder"><?php esc_html_e( 'Φάκελος Αλληλογραφίας:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label>
								</th>
								<td>
									<input type="text" id="imap_folder" name="imap_folder" class="regular-text" value="<?php echo esc_attr( $imap_settings['folder'] ); ?>" placeholder="INBOX" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="imap_sender_filter"><?php esc_html_e( 'Φίλτρο Αποστολέα:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label>
								</th>
								<td>
									<input type="text" id="imap_sender_filter" name="imap_sender_filter" class="regular-text" value="<?php echo esc_attr( $imap_settings['sender_filter'] ); ?>" placeholder="cod@taxydromiki.gr" />
									<p class="description">
										<?php esc_html_e( 'Επεξεργάζονται μόνο μηνύματα από αυτόν τον αποστολέα. Μηνύματα τιμολογίων (apostoli_timologion@taxydromiki.gr) αγνοούνται αυτόματα.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="imap_schedule"><?php esc_html_e( 'Συχνότητα Ελέγχου (WP-Cron):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></label>
								</th>
								<td>
									<select id="imap_schedule" name="imap_schedule">
										<option value="hourly" <?php selected( $imap_settings['schedule'], 'hourly' ); ?>><?php esc_html_e( 'Κάθε ώρα (Hourly)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
										<option value="twicedaily" <?php selected( $imap_settings['schedule'], 'twicedaily' ); ?>><?php esc_html_e( 'Δύο φορές την ημέρα (Twice Daily)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
										<option value="daily" <?php selected( $imap_settings['schedule'], 'daily' ); ?>><?php esc_html_e( 'Μία φορά την ημέρα (Daily)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
										<option value="manual" <?php selected( $imap_settings['schedule'], 'manual' ); ?>><?php esc_html_e( 'Μόνο Χειροκίνητος Έλεγχος (Manual)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
									</select>
									<?php if ( $imap_next_cron && 'manual' !== $imap_settings['schedule'] ) : ?>
										<span style="margin-left: 10px; font-size: 12px; color: #2271b1;">
											<span class="dashicons dashicons-clock" style="vertical-align: middle; font-size: 16px;"></span>
											<?php printf( esc_html__( 'Επόμενη προγραμματισμένη εκτέλεση: %s', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), esc_html( date_i18n( 'd/m/Y H:i', $imap_next_cron ) ) ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<div style="margin-top: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
							<button type="submit" name="gt_cod_save_auto_settings" class="button button-primary button-hero" style="background: #005aa4; border-color: #00457d;">
								<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span>
								<?php esc_html_e( 'Αποθήκευση Ρυθμίσεων IMAP', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</button>

							<button type="submit" name="gt_cod_test_imap" class="button button-secondary">
								<span class="dashicons dashicons-admin-plugins" style="vertical-align: middle; margin-top: -2px;"></span>
								<?php esc_html_e( 'Δοκιμή Σύνδεσης IMAP', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</button>

							<button type="submit" name="gt_cod_check_imap_now" class="button button-secondary" onclick="return confirm('Θέλετε να ελέγξετε άμεσα το γραμματοκιβώτιο για νέα αρχεία εκκαθάρισης;');">
								<span class="dashicons dashicons-update" style="vertical-align: middle; margin-top: -2px;"></span>
								<?php esc_html_e( 'Έλεγχος & Επεξεργασία Τώρα', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</button>
						</div>
					</div>

					<!-- Last IMAP Execution Log -->
					<div style="padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; <?php echo $last_imap_log ? 'background: #e7f5ea; border-left: 4px solid #46b450;' : 'background: #f6f7f7; border-left: 4px solid #b4b9be;'; ?>">
						<strong style="font-size: 13px;"><?php esc_html_e( 'Κατάσταση Τελευταίου Ελέγχου IMAP:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
						<?php if ( $last_imap_log ) : ?>
							<p style="margin: 5px 0 0 0; font-size: 13px;">
								<?php
								printf(
									esc_html__( 'Ημερομηνία: %1$s | Επεξεργάστηκαν: %2$d μηνύματα (%3$d αρχεία) | Ενημερώθηκαν: %4$d παραγγελίες (Σύνολο: %5$s €).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
									esc_html( $last_imap_log['timestamp'] ),
									(int) $last_imap_log['messages_processed'],
									(int) $last_imap_log['files_processed'],
									(int) $last_imap_log['updated_orders'],
									esc_html( number_format( (float) $last_imap_log['total_amount'], 2, ',', '.' ) )
								);
								?>
								<?php if ( ! empty( $last_imap_log['errors'] ) && is_array( $last_imap_log['errors'] ) ) : ?>
									<br><span style="color: #d63638; font-size: 12px;"><?php esc_html_e( 'Σφάλματα: ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?><?php echo esc_html( implode( ' | ', $last_imap_log['errors'] ) ); ?></span>
								<?php endif; ?>
							</p>
						<?php else : ?>
							<p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">
								<?php esc_html_e( 'Δεν έχει καταγραφεί ακόμα εκτέλεση ελέγχου IMAP.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<div style="background: #e7f3fe; border-left: 4px solid #2271b1; padding: 12px 16px; border-radius: 2px;">
						<p style="margin: 0; font-size: 13px; line-height: 1.5;">
							<strong><?php esc_html_e( 'Σημείωση για λογαριασμούς Google Workspace / Gmail:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong><br>
							<?php esc_html_e( 'Εάν έχετε ενεργοποιημένη την επαλήθευση 2 βημάτων (2-Factor Authentication), η Google απαιτεί τη χρήση "Κωδικού πρόσβασης εφαρμογής" (App Password) αντί για τον κύριο κωδικό σας. Μπορείτε να δημιουργήσετε έναν από το λογαριασμό σας Google > Ασφάλεια > Επαλήθευση σε 2 βήματα > Κωδικοί πρόσβασης εφαρμογών.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</p>
					</div>
				</div>

				<!-- Panel 3: Disabled -->
				<div id="gt_panel_disabled" style="<?php echo 'disabled' === $auto_method ? '' : 'display:none;'; ?>">
					<p style="font-size: 13px; color: #666; font-style: italic; margin-bottom: 15px;">
						<?php esc_html_e( 'Η αυτόματη εισαγωγή είναι απενεργοποιημένη. Μπορείτε να φορτώνετε τα αρχεία εκκαθάρισης αντικαταβολών χειροκίνητα όποτε επιθυμείτε από την παραπάνω φόρμα.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</p>
					<button type="submit" name="gt_cod_save_auto_settings" class="button button-primary">
						<?php esc_html_e( 'Αποθήκευση Επιλογής', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</button>
				</div>
			</form>

			<!-- Form for Secret Key Regeneration -->
			<form method="POST" action="<?php echo esc_url( $cod_import_url ); ?>" id="gt_regen_secret_form" style="display:none;">
				<?php wp_nonce_field( 'gt_cod_regen_secret_nonce', 'gt_cod_regen_secret_nonce_field' ); ?>
			</form>
		</div>

		<script>
		function gtToggleAutoMethod(method) {
			var pWebhook = document.getElementById('gt_panel_webhook');
			var pImap = document.getElementById('gt_panel_imap');
			var pDisabled = document.getElementById('gt_panel_disabled');
			if (pWebhook) pWebhook.style.display = (method === 'webhook') ? 'block' : 'none';
			if (pImap) pImap.style.display = (method === 'imap') ? 'block' : 'none';
			if (pDisabled) pDisabled.style.display = (method === 'disabled') ? 'block' : 'none';
		}
		</script>

	<!-- Step 2: Preview & Confirmation -->
	<?php elseif ( 'preview' === $action_step ) : ?>
		<?php
		$total_rows   = count( $records );
		$ready_count  = 0;
		$paid_count   = 0;
		$error_count  = 0;

		foreach ( $records as $r ) {
			if ( 'ready' === $r['status_code'] || 'amount_mismatch' === $r['status_code'] ) {
				$ready_count++;
			} elseif ( 'already_paid' === $r['status_code'] ) {
				$paid_count++;
			} else {
				$error_count++;
			}
		}
		?>

		<div style="display: flex; gap: 15px; margin: 15px 0 20px 0; flex-wrap: wrap;">
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #2271b1; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Σύνολο Εγγραφών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $total_rows ); ?></div>
			</div>
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #46b450; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Προς Ενημέρωση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo esc_html( $ready_count ); ?></div>
			</div>
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #dba617; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Ήδη Εξοφλημένες', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo esc_html( $paid_count ); ?></div>
			</div>
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #dc3232; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Μη Ευρεθείσες', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #dc3232;"><?php echo esc_html( $error_count ); ?></div>
			</div>
		</div>

		<form method="POST" action="<?php echo esc_url( $cod_import_url ); ?>" id="gt_cod_preview_form">
			<?php wp_nonce_field( 'gt_cod_confirm_nonce', 'gt_cod_confirm_nonce_field' ); ?>
			<input type="hidden" name="records_payload" value="<?php echo esc_attr( wp_json_encode( $records ) ); ?>" />

			<div style="background: #fff; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
				<div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
					<h3 style="margin: 0;"><?php esc_html_e( 'Προεπισκόπηση Αποτελεσμάτων & Αντιστοίχιση Παραγγελιών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></h3>
					<div>
						<button type="button" class="button" id="gt-btn-select-all"><?php esc_html_e( 'Επιλογή Όλων των Ετοίμων', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></button>
						<button type="button" class="button" id="gt-btn-deselect-all"><?php esc_html_e( 'Αποεπιλογή Όλων', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></button>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
					<thead>
						<tr>
							<td id="cb" class="manage-column column-cb check-column" style="width: 38px;">
								<input id="cb-select-all-1" type="checkbox" />
							</td>
							<th style="width: 110px;"><?php esc_html_e( 'Voucher (ΓΤ)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
							<th style="width: 90px;"><?php esc_html_e( 'Κωδ. Αρχείου', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
							<th><?php esc_html_e( 'Παραλήπτης / Προορισμός (ΓΤ)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
							<th style="width: 140px;"><?php esc_html_e( 'Ημ. Παράδοσης', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
							<th style="width: 85px; text-align: right;"><?php esc_html_e( 'Ποσό ΓΤ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
							<th><?php esc_html_e( 'Αντίστοιχη Παραγγελία WooCommerce', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
							<th style="width: 180px;"><?php esc_html_e( 'Κατάσταση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $records as $index => $row ) : ?>
							<?php
							$badge_bg    = '#e2e4e7';
							$badge_color = '#333';
							if ( 'ready' === $row['status_code'] ) {
								$badge_bg    = '#d4edda';
								$badge_color = '#155724';
							} elseif ( 'already_paid' === $row['status_code'] ) {
								$badge_bg    = '#fff3cd';
								$badge_color = '#856404';
							} elseif ( 'amount_mismatch' === $row['status_code'] ) {
								$badge_bg    = '#fff3cd';
								$badge_color = '#d63638';
							} elseif ( 'not_found' === $row['status_code'] ) {
								$badge_bg    = '#f8d7da';
								$badge_color = '#721c24';
							}
							?>
							<tr>
								<th scope="row" class="check-column">
									<?php if ( $row['is_selectable'] ) : ?>
										<input type="checkbox" name="selected_rows[]" value="<?php echo esc_attr( $index ); ?>" <?php checked( $row['default_checked'], true ); ?> class="gt-row-cb" data-ready="<?php echo ( 'ready' === $row['status_code'] || 'amount_mismatch' === $row['status_code'] ) ? '1' : '0'; ?>" />
									<?php else : ?>
										<input type="checkbox" disabled />
									<?php endif; ?>
								</th>
								<td>
									<strong>
										<a href="https://www.taxydromiki.com/track/<?php echo esc_attr( $row['voucher'] ); ?>" target="_blank" title="<?php esc_attr_e( 'Άνοιγμα tracking', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>">
											<?php echo esc_html( $row['voucher'] ); ?>
										</a>
									</strong>
								</td>
								<td><?php echo esc_html( $row['client_ref'] ? $row['client_ref'] : '-' ); ?></td>
								<td>
									<strong><?php echo esc_html( $row['recipient'] ); ?></strong>
									<?php if ( ! empty( $row['destination'] ) ) : ?>
										<br><span style="color: #666; font-size: 12px;"><?php echo esc_html( $row['destination'] ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row['delivery_date'] ); ?></td>
								<td style="text-align: right; font-weight: bold;">
									<?php echo esc_html( number_format( $row['amount'], 2, ',', '.' ) ); ?> €
								</td>
								<td>
									<?php if ( $row['order_id'] ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $row['order_id'] ) ? get_edit_post_link( $row['order_id'] ) : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $row['order_id'] ) ); ?>" target="_blank" style="font-weight: 600;">
											#<?php echo esc_html( $row['order_id'] ); ?>
										</a>
										- <?php echo esc_html( $row['customer_name'] ); ?>
										<br>
										<span style="color: #666; font-size: 12px;">
											<?php
											/* translators: 1: status name, 2: total amount */
											printf( esc_html__( 'Κατάσταση: %1$s | Σύνολο: %2$s €', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), esc_html( $row['order_status'] ), esc_html( number_format( $row['order_total'], 2, ',', '.' ) ) );
											?>
										</span>
									<?php else : ?>
										<span style="color: #999;">-</span>
									<?php endif; ?>
								</td>
								<td>
									<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_color ); ?>;">
										<?php echo esc_html( $row['status_label'] ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
					<button type="submit" name="gt_cod_confirm_import" class="button button-primary button-hero" style="background: #46b450; border-color: #389441;">
						<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-top: -2px;"></span>
						<?php esc_html_e( 'Επιβεβαίωση & Ενημέρωση Επιλεγμένων Παραγγελιών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</button>

					<a href="<?php echo esc_url( $cod_import_url ); ?>" class="button button-secondary button-hero">
						<?php esc_html_e( 'Ακύρωση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</a>
				</div>
			</div>
		</form>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var checkAllHeader = document.getElementById('cb-select-all-1');
			var rowCheckboxes  = document.querySelectorAll('.gt-row-cb');
			var btnSelectAll   = document.getElementById('gt-btn-select-all');
			var btnDeselectAll = document.getElementById('gt-btn-deselect-all');

			if (checkAllHeader) {
				checkAllHeader.addEventListener('change', function() {
					rowCheckboxes.forEach(function(cb) {
						if (!cb.disabled) cb.checked = checkAllHeader.checked;
					});
				});
			}

			if (btnSelectAll) {
				btnSelectAll.addEventListener('click', function(e) {
					e.preventDefault();
					rowCheckboxes.forEach(function(cb) {
						if (!cb.disabled && cb.dataset.ready === '1') {
							cb.checked = true;
						}
					});
				});
			}

			if (btnDeselectAll) {
				btnDeselectAll.addEventListener('click', function(e) {
					e.preventDefault();
					rowCheckboxes.forEach(function(cb) {
						cb.checked = false;
					});
					if (checkAllHeader) checkAllHeader.checked = false;
				});
			}
		});
		</script>

	<!-- Step 3: Completed / Results View -->
	<?php elseif ( 'completed' === $action_step ) : ?>
		<div class="card" style="max-width: 900px; padding: 25px; margin-top: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h2 style="color: #46b450; margin-top: 0;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 28px; vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( 'Η ενημέρωση των παραγγελιών ολοκληρώθηκε!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>

			<p style="font-size: 14px;">
				<?php esc_html_e( 'Οι επιλεγμένες παραγγελίες ενημερώθηκαν με σημείωση είσπραξης αντικαταβολής και καταχωρήθηκε το αντίστοιχο metadata.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</p>

			<table class="wp-list-table widefat striped" style="margin: 20px 0;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Παραγγελία', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Voucher', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Ποσό', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Κατάσταση Ενέργειας', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $processed as $item ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $item['order_id'] ) ? get_edit_post_link( $item['order_id'] ) : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $item['order_id'] ) ); ?>" target="_blank" style="font-weight: 600;">
									#<?php echo esc_html( $item['order_id'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $item['voucher'] ); ?></td>
							<td><?php echo esc_html( number_format( $item['amount'], 2, ',', '.' ) ); ?> €</td>
							<td>
								<?php if ( 'success' === $item['status'] ) : ?>
									<span style="color: #155724; background: #d4edda; padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px;">
										✓ <?php echo esc_html( $item['message'] ); ?>
									</span>
								<?php else : ?>
									<span style="color: #721c24; background: #f8d7da; padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px;">
										✗ <?php echo esc_html( $item['message'] ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div style="margin-top: 25px;">
				<a href="<?php echo esc_url( $cod_import_url ); ?>" class="button button-primary button-hero" style="background: #005aa4; border-color: #00457d;">
					<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span>
					<?php esc_html_e( 'Εισαγωγή Νέου Αρχείου Αντικαταβολών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>
</div>
