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

$gt_action_step = 'upload';
$gt_notice      = null;
$gt_records     = array();
$gt_processed   = array();

// 0. Handle Secret Key Regeneration
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

// 0b. Handle Automation Settings Save
if ( isset( $_POST['gt_cod_save_auto_settings'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$gt_method = isset( $_POST['gt_cod_auto_method'] ) ? sanitize_text_field( wp_unslash( $_POST['gt_cod_auto_method'] ) ) : 'webhook';
		update_option( 'gtvfw_cod_auto_method', $gt_method );

		$gt_existing_imap = GT_COD_Importer::get_imap_settings();
		$gt_imap_settings = array(
			'host'          => isset( $_POST['imap_host'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_host'] ) ) : $gt_existing_imap['host'],
			'port'          => isset( $_POST['imap_port'] ) ? absint( wp_unslash( $_POST['imap_port'] ) ) : $gt_existing_imap['port'],
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
			'message' => __( 'Οι ρυθμίσεις αυτόματης εισαγωγής αποθηκεύτηκαν επιτυχώς!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	}
}

// 0c. Handle IMAP Connection Test
if ( isset( $_POST['gt_cod_test_imap'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$gt_existing_imap = GT_COD_Importer::get_imap_settings();
		$gt_temp_settings = array(
			'host'          => isset( $_POST['imap_host'] ) ? sanitize_text_field( wp_unslash( $_POST['imap_host'] ) ) : $gt_existing_imap['host'],
			'port'          => isset( $_POST['imap_port'] ) ? absint( wp_unslash( $_POST['imap_port'] ) ) : $gt_existing_imap['port'],
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

// 0d. Handle Manual IMAP Check & Sync Now
if ( isset( $_POST['gt_cod_check_imap_now'] ) && check_admin_referer( 'gt_cod_auto_settings_nonce', 'gt_cod_auto_settings_nonce_field' ) ) {
	if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'administrator' ) ) {
		$gt_res = GT_COD_Importer::fetch_and_process_imap_emails();
		if ( is_wp_error( $gt_res ) ) {
			$gt_notice = array(
				'type'    => 'error',
				/* translators: %s: IMAP error message */
				'message' => sprintf( __( 'Σφάλμα ανάγνωσης IMAP: %s', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $gt_res->get_error_message() ),
			);
		} else {
			$gt_notice = array(
				'type'    => 'success',
				'message' => sprintf(
					/* translators: 1: number of messages, 2: number of files, 3: updated orders count, 4: total amount in euros */
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

// 1. Process Order Updates Confirmation
if ( isset( $_POST['gt_cod_confirm_import'] ) && check_admin_referer( 'gt_cod_confirm_nonce', 'gt_cod_confirm_nonce_field' ) ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
		wp_die( esc_html__( 'Δεν έχετε δικαίωμα εκτέλεσης αυτής της ενέργειας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
	}

	$gt_selected_rows = isset( $_POST['selected_rows'] ) && is_array( $_POST['selected_rows'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_rows'] ) ) : array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via json_decode.
	$gt_raw_data      = isset( $_POST['records_payload'] ) ? wp_unslash( $_POST['records_payload'] ) : '';
	$gt_all_records   = json_decode( $gt_raw_data, true );

	if ( ! empty( $gt_all_records ) && is_array( $gt_all_records ) ) {
		$gt_action_step          = 'completed';
		$gt_updated_count        = 0;
		$gt_skipped_count        = 0;
		$gt_total_amount_updated = 0.0;

		foreach ( $gt_all_records as $gt_idx => $gt_record ) {
			if ( in_array( (string) $gt_idx, $gt_selected_rows, true ) && ! empty( $gt_record['order_id'] ) ) {
				$gt_res = GT_COD_Importer::mark_order_cod_paid( $gt_record['order_id'], $gt_record );
				if ( true === $gt_res ) {
					$gt_updated_count++;
					$gt_total_amount_updated += (float) ( $gt_record['amount'] ?? 0 );
					$gt_processed[] = array(
						'order_id'   => $gt_record['order_id'],
						'voucher'    => $gt_record['voucher'],
						'amount'     => $gt_record['amount'],
						'status'     => 'success',
						'message'    => __( 'Ενημερώθηκε επιτυχώς', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					);
				} else {
					$gt_skipped_count++;
					$gt_processed[] = array(
						'order_id'   => $gt_record['order_id'],
						'voucher'    => $gt_record['voucher'],
						'amount'     => $gt_record['amount'],
						'status'     => 'error',
						'message'    => is_wp_error( $gt_res ) ? $gt_res->get_error_message() : __( 'Σφάλμα ενημέρωσης', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					);
				}
			}
		}

		update_option( 'gtvfw_cod_last_clearance_info', array(
			'timestamp'      => current_time( 'mysql' ),
			'source'         => 'manual',
			'filename'       => isset( $_POST['gt_cod_filename'] ) ? sanitize_file_name( wp_unslash( $_POST['gt_cod_filename'] ) ) : 'manual_upload.csv',
			'updated_orders' => $gt_updated_count,
			'skipped_orders' => $gt_skipped_count,
			'total_amount'   => $gt_total_amount_updated,
		) );

		$gt_notice = array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: %d: number of orders updated */
				__( 'Ολοκληρώθηκε η διαδικασία! Ενημερώθηκαν επιτυχώς %d παραγγελίες.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				$gt_updated_count
			),
		);
	}
}

// 2. Process File Upload & Preview
elseif ( isset( $_POST['gt_cod_upload_file'] ) && check_admin_referer( 'gt_cod_upload_nonce', 'gt_cod_upload_nonce_field' ) ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
		wp_die( esc_html__( 'Δεν έχετε δικαίωμα εκτέλεσης αυτής της ενέργειας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
	}

	if ( empty( $_FILES['cod_csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['cod_csv_file']['tmp_name'] ) ) {
		$gt_notice = array(
			'type'    => 'error',
			'message' => __( 'Παρακαλούμε επιλέξτε ένα έγκυρο αρχείο CSV.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	} else {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Temporary file path validated with is_uploaded_file.
		$gt_file_tmp          = sanitize_text_field( wp_unslash( $_FILES['cod_csv_file']['tmp_name'] ) );
		$gt_uploaded_filename = isset( $_FILES['cod_csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['cod_csv_file']['name'] ) ) : '';
		$gt_content           = GT_COD_Importer::read_file_content( $gt_file_tmp );

		if ( is_wp_error( $gt_content ) ) {
			$gt_notice = array(
				'type'    => 'error',
				'message' => $gt_content->get_error_message(),
			);
		} else {
			$gt_raw_records = GT_COD_Importer::parse_csv_content( $gt_content );
			if ( is_wp_error( $gt_raw_records ) ) {
				$gt_notice = array(
					'type'    => 'error',
					'message' => $gt_raw_records->get_error_message(),
				);
			} elseif ( empty( $gt_raw_records ) ) {
				$gt_notice = array(
					'type'    => 'error',
					'message' => __( 'Δεν βρέθηκαν γραμμές αποστολών στο αρχείο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				);
			} else {
				$gt_records     = GT_COD_Importer::match_records( $gt_raw_records );
				$gt_action_step = 'preview';
			}
		}
	}
}

$gt_settings_url       = admin_url( 'admin.php?page=gtvfw_settings' );
$gt_cod_import_url     = admin_url( 'admin.php?page=gtvfw_cod_import' );
$gt_invoice_import_url = admin_url( 'admin.php?page=gtvfw_invoice_import' );
?>

<div class="wrap gtvfw-admin-wrapper">
	<h1><?php esc_html_e( 'Γενική Ταχυδρομική - Διαχείριση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
		<a href="<?php echo esc_url( $gt_settings_url ); ?>" class="nav-tab">
			<?php esc_html_e( 'Ρυθμίσεις & Αυτοματισμός (Hub)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
		<a href="<?php echo esc_url( $gt_cod_import_url ); ?>" class="nav-tab nav-tab-active">
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

	<!-- Step 1: Upload Form & History -->
	<?php if ( 'upload' === $gt_action_step ) : ?>
		<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; margin-top: 15px;">
			<div class="card" style="flex: 1; min-width: 320px; max-width: 720px; padding: 25px; margin: 0; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h2 style="margin-top: 0; color: #005aa4; font-size: 1.3em;">
					<span class="dashicons dashicons-upload" style="font-size: 26px; vertical-align: middle; margin-right: 5px;"></span>
					<?php esc_html_e( 'Χειροκίνητη Εισαγωγή Αρχείου Αντικαταβολών (CSV)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</h2>
				<p style="font-size: 14px; line-height: 1.6; color: #555;">
					<?php esc_html_e( 'Επιλέξτε το αρχείο CSV αποδόσεων αντικαταβολής που λαμβάνετε από τη Γενική Ταχυδρομική (π.χ. TAXYDROMIKI_20260901.csv).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</p>

				<form method="POST" enctype="multipart/form-data" action="<?php echo esc_url( $gt_cod_import_url ); ?>" style="margin-top: 20px;">
					<?php wp_nonce_field( 'gt_cod_upload_nonce', 'gt_cod_upload_nonce_field' ); ?>

					<div style="padding: 20px; border: 2px dashed #b4b9be; background: #fafafa; text-align: center; border-radius: 4px; margin-bottom: 20px;">
						<label for="cod_csv_file" style="display: block; font-weight: 600; margin-bottom: 10px; font-size: 14px;">
							<?php esc_html_e( 'Επιλογή αρχείου CSV:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</label>
						<input type="file" name="cod_csv_file" id="cod_csv_file" accept=".csv,.txt" required style="font-size: 13px;" />
					</div>

					<button type="submit" name="gt_cod_upload_file" class="button button-primary button-large" style="background: #005aa4; border-color: #00457d;">
						<span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Ανάλυση & Προεπισκόπηση Εισπράξεων', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</button>
				</form>
			</div>

		<!-- Card 2: Last Clearance History Card -->
		<?php
		$gt_last_clearance_info = get_option( 'gtvfw_cod_last_clearance_info' );
		$gt_last_webhook_log    = get_option( 'gtvfw_cod_webhook_last_log' );
		$gt_last_imap_log       = get_option( 'gtvfw_cod_imap_last_log' );

		// Determine most recent clearance across manual, webhook, or IMAP
		$gt_latest_clearance = null;
		$gt_clearance_source = '';

		if ( ! empty( $gt_last_clearance_info['timestamp'] ) ) {
			$gt_latest_clearance = $gt_last_clearance_info;
			$gt_clearance_source = __( 'Χειροκίνητο CSV', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
		}
		if ( ! empty( $gt_last_webhook_log['timestamp'] ) ) {
			if ( ! $gt_latest_clearance || strcmp( $gt_last_webhook_log['timestamp'], $gt_latest_clearance['timestamp'] ) > 0 ) {
				$gt_latest_clearance = $gt_last_webhook_log;
				$gt_clearance_source = __( 'Email Webhook (GAS)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
			}
		}
		if ( ! empty( $gt_last_imap_log['timestamp'] ) ) {
			if ( ! $gt_latest_clearance || strcmp( $gt_last_imap_log['timestamp'], $gt_latest_clearance['timestamp'] ) > 0 ) {
				$gt_latest_clearance = $gt_last_imap_log;
				$gt_clearance_source = __( 'Email IMAP', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
			}
		}
		?>
		<div class="card" style="flex: 1; min-width: 320px; max-width: 500px; padding: 25px; margin: 0; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
			<h2 style="margin-top: 0; color: #23282d; font-size: 1.2em;">
				<span class="dashicons dashicons-backup" style="font-size: 22px; vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( 'Ιστορικό Τελευταίας Εκκαθάρισης', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>

			<?php if ( $gt_latest_clearance ) : ?>
				<table class="widefat striped" style="margin-top: 15px; border-radius: 4px;">
					<tbody>
						<tr>
							<td style="font-weight: 600; width: 45%;"><?php esc_html_e( 'Ημερομηνία:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
							<td><?php echo esc_html( $gt_latest_clearance['timestamp'] ); ?></td>
						</tr>
						<tr>
							<td style="font-weight: 600;"><?php esc_html_e( 'Προέλευση:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
							<td>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 16px; vertical-align: text-top;"></span>
								<strong><?php echo esc_html( $gt_clearance_source ); ?></strong>
							</td>
						</tr>
						<tr>
							<td style="font-weight: 600;"><?php esc_html_e( 'Αρχείο:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
							<td><code><?php echo esc_html( $gt_latest_clearance['filename'] ?? 'clearance.csv' ); ?></code></td>
						</tr>
						<tr>
							<td style="font-weight: 600;"><?php esc_html_e( 'Ενημερωμένες Παραγγελίες:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
							<td><strong style="color: #005aa4; font-size: 14px;"><?php echo (int) ( $gt_latest_clearance['updated_orders'] ?? 0 ); ?></strong></td>
						</tr>
						<?php if ( isset( $gt_latest_clearance['total_amount'] ) ) : ?>
						<tr>
							<td style="font-weight: 600;"><?php esc_html_e( 'Συνολικό Ποσό Είσπραξης:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
							<td>
								<strong style="color: #2e7d32; font-size: 14px;">
									<?php echo esc_html( number_format( (float) $gt_latest_clearance['total_amount'], 2, ',', '.' ) ); ?> €
								</strong>
							</td>
						</tr>
						<?php endif; ?>
						<?php if ( ! empty( $gt_latest_clearance['already_paid'] ) ) : ?>
						<tr>
							<td style="font-weight: 600;"><?php esc_html_e( 'Ήδη εξοφλημένες:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
							<td><?php echo (int) $gt_latest_clearance['already_paid']; ?></td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div style="padding: 15px; background: #f6f7f7; border-left: 4px solid #b4b9be; margin-top: 15px; border-radius: 3px;">
					<?php esc_html_e( 'Δεν έχει καταγραφεί ακόμα προηγούμενη εκκαθάριση αντικαταβολών.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</div>
			<?php endif; ?>

			<!-- Hub Automation Navigation Box -->
			<div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
				<h3 style="margin: 0 0 8px 0; font-size: 14px;">
					<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; color: #666;"></span>
					<?php esc_html_e( 'Αυτοματοποίηση Εκκαθαρίσεων (Hub)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</h3>
				<p style="font-size: 12px; color: #666; margin: 0 0 12px 0; line-height: 1.5;">
					<?php esc_html_e( 'Θέλετε να λαμβάνετε και να ενημερώνετε αυτόματα τις αντικαταβολές μόλις φτάνει το email από τη Γενική Ταχυδρομική χωρίς χειροκίνητα αρχεία;', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</p>
				<a href="<?php echo esc_url( $gt_settings_url ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-external" style="vertical-align: middle; margin-right: 3px;"></span>
					<?php esc_html_e( 'Μετάβαση στο Hub Ρυθμίσεων & Αυτοματισμού', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Step 2: Preview & Confirmation -->
	<?php elseif ( 'preview' === $gt_action_step ) : ?>
		<?php
		$gt_total_rows   = count( $gt_records );
		$gt_ready_count  = 0;
		$gt_paid_count   = 0;
		$gt_error_count  = 0;

		foreach ( $gt_records as $gt_r ) {
			if ( 'ready' === $gt_r['status_code'] || 'amount_mismatch' === $gt_r['status_code'] ) {
				$gt_ready_count++;
			} elseif ( 'already_paid' === $gt_r['status_code'] ) {
				$gt_paid_count++;
			} else {
				$gt_error_count++;
			}
		}
		?>

		<div style="display: flex; gap: 15px; margin: 15px 0 20px 0; flex-wrap: wrap;">
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #2271b1; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Σύνολο Εγγραφών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $gt_total_rows ); ?></div>
			</div>
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #46b450; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Προς Ενημέρωση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo esc_html( $gt_ready_count ); ?></div>
			</div>
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #dba617; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Ήδη Εξοφλημένες', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo esc_html( $gt_paid_count ); ?></div>
			</div>
			<div style="background: #fff; padding: 15px 20px; border-left: 4px solid #dc3232; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); min-width: 140px;">
				<div style="font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e( 'Μη Ευρεθείσες', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
				<div style="font-size: 24px; font-weight: bold; color: #dc3232;"><?php echo esc_html( $gt_error_count ); ?></div>
			</div>
		</div>

		<form method="POST" action="<?php echo esc_url( $gt_cod_import_url ); ?>" id="gt_cod_preview_form">
			<?php wp_nonce_field( 'gt_cod_confirm_nonce', 'gt_cod_confirm_nonce_field' ); ?>
			<input type="hidden" name="records_payload" value="<?php echo esc_attr( wp_json_encode( $gt_records ) ); ?>" />
			<input type="hidden" name="gt_cod_filename" value="<?php echo esc_attr( $gt_uploaded_filename ?? '' ); ?>" />

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
						<?php foreach ( $gt_records as $gt_index => $gt_row ) : ?>
							<?php
							$gt_badge_bg    = '#e2e4e7';
							$gt_badge_color = '#333';
							if ( 'ready' === $gt_row['status_code'] ) {
								$gt_badge_bg    = '#d4edda';
								$gt_badge_color = '#155724';
							} elseif ( 'already_paid' === $gt_row['status_code'] ) {
								$gt_badge_bg    = '#fff3cd';
								$gt_badge_color = '#856404';
							} elseif ( 'amount_mismatch' === $gt_row['status_code'] ) {
								$gt_badge_bg    = '#fff3cd';
								$gt_badge_color = '#d63638';
							} elseif ( 'not_found' === $gt_row['status_code'] ) {
								$gt_badge_bg    = '#f8d7da';
								$gt_badge_color = '#721c24';
							}
							?>
							<tr>
								<th scope="row" class="check-column">
									<?php if ( $gt_row['is_selectable'] ) : ?>
										<input type="checkbox" name="selected_rows[]" value="<?php echo esc_attr( $gt_index ); ?>" <?php checked( $gt_row['default_checked'], true ); ?> class="gt-row-cb" data-ready="<?php echo ( 'ready' === $gt_row['status_code'] || 'amount_mismatch' === $gt_row['status_code'] ) ? '1' : '0'; ?>" />
									<?php else : ?>
										<input type="checkbox" disabled />
									<?php endif; ?>
								</th>
								<td>
									<strong>
										<a href="https://www.taxydromiki.com/track/<?php echo esc_attr( $gt_row['voucher'] ); ?>" target="_blank" title="<?php esc_attr_e( 'Άνοιγμα tracking', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>">
											<?php echo esc_html( $gt_row['voucher'] ); ?>
										</a>
									</strong>
								</td>
								<td><?php echo esc_html( $gt_row['client_ref'] ? $gt_row['client_ref'] : '-' ); ?></td>
								<td>
									<strong><?php echo esc_html( $gt_row['recipient'] ); ?></strong>
									<?php if ( ! empty( $gt_row['destination'] ) ) : ?>
										<br><span style="color: #666; font-size: 12px;"><?php echo esc_html( $gt_row['destination'] ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $gt_row['delivery_date'] ); ?></td>
								<td style="text-align: right; font-weight: bold;">
									<?php echo esc_html( number_format( $gt_row['amount'], 2, ',', '.' ) ); ?> €
								</td>
								<td>
									<?php if ( $gt_row['order_id'] ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $gt_row['order_id'] ) ? get_edit_post_link( $gt_row['order_id'] ) : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $gt_row['order_id'] ) ); ?>" target="_blank" style="font-weight: 600;">
											#<?php echo esc_html( $gt_row['order_id'] ); ?>
										</a>
										- <?php echo esc_html( $gt_row['customer_name'] ); ?>
										<br>
										<span style="color: #666; font-size: 12px;">
											<?php
											/* translators: 1: status name, 2: total amount */
											printf( esc_html__( 'Κατάσταση: %1$s | Σύνολο: %2$s €', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), esc_html( $gt_row['order_status'] ), esc_html( number_format( $gt_row['order_total'], 2, ',', '.' ) ) );
											?>
										</span>
									<?php else : ?>
										<span style="color: #999;">-</span>
									<?php endif; ?>
								</td>
								<td>
									<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: <?php echo esc_attr( $gt_badge_bg ); ?>; color: <?php echo esc_attr( $gt_badge_color ); ?>;">
										<?php echo esc_html( $gt_row['status_label'] ); ?>
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

					<a href="<?php echo esc_url( $gt_cod_import_url ); ?>" class="button button-secondary button-hero">
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
	<?php elseif ( 'completed' === $gt_action_step ) : ?>
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
					<?php foreach ( $gt_processed as $gt_item ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $gt_item['order_id'] ) ? get_edit_post_link( $gt_item['order_id'] ) : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $gt_item['order_id'] ) ); ?>" target="_blank" style="font-weight: 600;">
									#<?php echo esc_html( $gt_item['order_id'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $gt_item['voucher'] ); ?></td>
							<td><?php echo esc_html( number_format( $gt_item['amount'], 2, ',', '.' ) ); ?> €</td>
							<td>
								<?php if ( 'success' === $gt_item['status'] ) : ?>
									<span style="color: #155724; background: #d4edda; padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px;">
										✓ <?php echo esc_html( $gt_item['message'] ); ?>
									</span>
								<?php else : ?>
									<span style="color: #721c24; background: #f8d7da; padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px;">
										✗ <?php echo esc_html( $gt_item['message'] ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div style="margin-top: 25px;">
				<a href="<?php echo esc_url( $gt_cod_import_url ); ?>" class="button button-primary button-hero" style="background: #005aa4; border-color: #00457d;">
					<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span>
					<?php esc_html_e( 'Εισαγωγή Νέου Αρχείου Αντικαταβολών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>
</div>
