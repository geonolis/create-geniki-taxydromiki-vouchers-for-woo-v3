<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin view for Geniki Taxydromiki Invoices (ΤΠΥ) Import & Shipping Cost Comparison.
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin/partials
 */

$gt_action_step = 'upload';
$gt_notice      = null;
$gt_records     = array();
$gt_processed   = array();

// 1. Process Order Costs Confirmation
if ( isset( $_POST['gt_invoice_confirm_import'] ) && check_admin_referer( 'gt_invoice_confirm_nonce', 'gt_invoice_confirm_nonce_field' ) ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
		wp_die( esc_html__( 'Δεν έχετε δικαίωμα εκτέλεσης αυτής της ενέργειας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
	}

	$gt_selected_rows = isset( $_POST['selected_rows'] ) && is_array( $_POST['selected_rows'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_rows'] ) ) : array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via json_decode.
	$gt_raw_data      = isset( $_POST['records_payload'] ) ? wp_unslash( $_POST['records_payload'] ) : '';
	$gt_all_records   = json_decode( $gt_raw_data, true );

	if ( ! empty( $gt_all_records ) && is_array( $gt_all_records ) ) {
		$gt_action_step   = 'completed';
		$gt_updated_count = 0;
		$gt_skipped_count = 0;
		$gt_total_courier = 0.0;
		$gt_total_client  = 0.0;

		foreach ( $gt_all_records as $gt_idx => $gt_record ) {
			if ( in_array( (string) $gt_idx, $gt_selected_rows, true ) && ! empty( $gt_record['order_id'] ) ) {
				$gt_res = GT_Invoice_Importer::save_order_invoice_cost( $gt_record['order_id'], $gt_record );
				if ( true === $gt_res ) {
					$gt_updated_count++;
					$gt_total_courier += (float) $gt_record['total_cost'];
					$gt_total_client  += (float) $gt_record['client_total_charged'];
					$gt_processed[]    = array(
						'order_id'     => $gt_record['order_id'],
						'voucher'      => $gt_record['voucher'],
						'courier_cost' => $gt_record['total_cost'],
						'client_total' => $gt_record['client_total_charged'],
						'difference'   => $gt_record['difference'],
						'status'       => 'success',
						'message'      => __( 'Ενημερώθηκε επιτυχώς', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					);
				} else {
					$gt_skipped_count++;
					$gt_processed[] = array(
						'order_id'   => $gt_record['order_id'],
						'voucher'    => $gt_record['voucher'],
						'status'     => 'error',
						'message'    => is_wp_error( $gt_res ) ? $gt_res->get_error_message() : __( 'Σφάλμα ενημέρωσης', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					);
				}
			}
		}

		$gt_diff_total = $gt_total_client - $gt_total_courier;
		$gt_diff_sign  = $gt_diff_total >= 0 ? '+' : '';

		update_option( 'gtvfw_invoice_last_log', array(
			'timestamp'        => current_time( 'mysql' ),
			'source'           => 'manual',
			'filename'         => isset( $_POST['gt_invoice_filename'] ) ? sanitize_file_name( wp_unslash( $_POST['gt_invoice_filename'] ) ) : 'manual_invoice.csv',
			'total_rows'       => count( $gt_all_records ),
			'updated_orders'   => $gt_updated_count,
			'already_imported' => 0,
			'not_found'        => 0,
			'errors'           => $gt_skipped_count,
			'total_courier'    => $gt_total_courier,
			'total_client'     => $gt_total_client,
			'net_difference'   => $gt_diff_total,
		) );

		$gt_notice = array(
			'type'    => 'success',
			'message' => sprintf(
				/* translators: 1: count, 2: courier cost, 3: client charged, 4: difference sign, 5: difference */
				__( 'Ολοκληρώθηκε η διαδικασία! Ενημερώθηκαν %1$d παραγγελίες. Συνολικό κόστος ΓΤ: %2$s € | Είσπραξη από πελάτες: %3$s € | Διαφορά: %4$s%5$s €.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				$gt_updated_count,
				number_format( $gt_total_courier, 2, ',', '.' ),
				number_format( $gt_total_client, 2, ',', '.' ),
				$gt_diff_sign,
				number_format( $gt_diff_total, 2, ',', '.' )
			),
		);
	}
}

// 2. Process File Upload & Preview
elseif ( isset( $_POST['gt_invoice_upload_file'] ) && check_admin_referer( 'gt_invoice_upload_nonce', 'gt_invoice_upload_nonce_field' ) ) {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'administrator' ) ) {
		wp_die( esc_html__( 'Δεν έχετε δικαίωμα εκτέλεσης αυτής της ενέργειας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
	}

	if ( empty( $_FILES['invoice_csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['invoice_csv_file']['tmp_name'] ) ) {
		$gt_notice = array(
			'type'    => 'error',
			'message' => __( 'Παρακαλούμε επιλέξτε ένα έγκυρο αρχείο CSV τιμολογίου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
		);
	} else {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Temporary file path validated with is_uploaded_file.
		$gt_file_tmp          = sanitize_text_field( wp_unslash( $_FILES['invoice_csv_file']['tmp_name'] ) );
		$gt_uploaded_filename = isset( $_FILES['invoice_csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['invoice_csv_file']['name'] ) ) : '';
		$gt_content           = GT_Invoice_Importer::read_file_content( $gt_file_tmp );

		if ( is_wp_error( $gt_content ) ) {
			$gt_notice = array(
				'type'    => 'error',
				'message' => $gt_content->get_error_message(),
			);
		} else {
			$gt_raw_records = GT_Invoice_Importer::parse_invoice_content( $gt_content );
			if ( is_wp_error( $gt_raw_records ) ) {
				$gt_notice = array(
					'type'    => 'error',
					'message' => $gt_raw_records->get_error_message(),
				);
			} elseif ( empty( $gt_raw_records ) ) {
				$gt_notice = array(
					'type'    => 'error',
					'message' => __( 'Δεν βρέθηκαν γραμμές αποστολών στο αρχείο τιμολογίου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				);
			} else {
				$gt_records     = GT_Invoice_Importer::match_records( $gt_raw_records );
				$gt_action_step = 'preview';
			}
		}
	}
}

$gt_settings_url       = admin_url( 'admin.php?page=gtvfw_settings' );
$gt_cod_import_url     = admin_url( 'admin.php?page=gtvfw_cod_import' );
$gt_invoice_import_url = admin_url( 'admin.php?page=gtvfw_invoice_import' );
$gt_webhook_url        = rest_url( 'gtvfw/v1/invoice-webhook' );
$gt_webhook_secret     = GT_COD_Importer::get_webhook_secret();
$gt_last_invoice_log   = get_option( 'gtvfw_invoice_last_log' );
?>

<div class="wrap gtvfw-admin-wrapper">
	<h1><?php esc_html_e( 'Γενική Ταχυδρομική - Διαχείριση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></h1>

	<!-- Tab Navigation -->
	<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom: 20px;">
		<a href="<?php echo esc_url( $gt_settings_url ); ?>" class="nav-tab">
			<?php esc_html_e( 'Ρυθμίσεις & Αυτοματισμός (Hub)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
		<a href="<?php echo esc_url( $gt_cod_import_url ); ?>" class="nav-tab">
			<?php esc_html_e( 'Αντικαταβολές (COD)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
		<a href="<?php echo esc_url( $gt_invoice_import_url ); ?>" class="nav-tab nav-tab-active">
			<?php esc_html_e( 'Τιμολόγια & Έλεγχος Κόστους (P&L)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
		</a>
	</nav>

	<?php if ( $gt_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $gt_notice['type'] ); ?> is-dismissible">
			<p><strong><?php echo esc_html( $gt_notice['message'] ); ?></strong></p>
		</div>
	<?php endif; ?>

	<!-- Step 1: Upload Form -->
	<?php if ( 'upload' === $gt_action_step ) : ?>
		<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start;">
			<div class="card" style="flex: 1; min-width: 320px; max-width: 750px; padding: 25px; margin: 0; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h2 style="margin-top: 0; color: #005aa4; font-size: 1.3em;">
					<span class="dashicons dashicons-media-spreadsheet" style="font-size: 26px; vertical-align: middle; margin-right: 5px;"></span>
					<?php esc_html_e( 'Εισαγωγή Τιμολογίου Εξόδων Μεταφορικών (ΤΠΥ)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</h2>
				<p style="font-size: 14px; line-height: 1.6; color: #555;">
					<?php esc_html_e( 'Επιλέξτε το αρχείο CSV τιμολογίου που σας αποστέλλει ανά 15ήμερο η Γενική Ταχυδρομική από το apostoli_timologion@taxydromiki.gr (π.χ. ΤΠΥ-GR-Β.-0000189976.csv).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</p>

				<div style="background: #f0f7fc; border-left: 4px solid #005aa4; padding: 12px 16px; margin: 15px 0; border-radius: 2px;">
					<p style="margin: 0; font-size: 13px;">
						<strong><?php esc_html_e( 'Τι υπολογίζεται αυτόματα:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
						<ul style="margin: 5px 0 0 18px; font-size: 13px; list-style-type: disc;">
							<li><strong><?php esc_html_e( 'Καθαρό Κόστος Courier:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong> <?php esc_html_e( 'Στήλη ΣΥΝΟΛ.ΑΞΙΑ (άνευ ΦΠΑ).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
							<li><strong><?php esc_html_e( 'Πρόσθετες Υπηρεσίες:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong> <?php esc_html_e( 'ΑΜ (Αντικαταβολή), ΖΒ (Ζώνη Β), ΔΠ (Δυσπρόσιτα), Β2 (Επιπλέον Βάρος), ΔΧ (Δυσπρόσιτα Χωρίς Χρέωση).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
							<li><strong><?php esc_html_e( 'Σύγκριση Κέρδους / Ζημίας (P&L):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong> <?php esc_html_e( 'Σύγκριση της συνολικής χρέωσης του πελάτη (Μεταφορικά + Αντικαταβολή) έναντι του κόστους της Γενικής Ταχυδρομικής.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
							<li><strong><?php esc_html_e( 'Ενημέρωση Παραγγελίας:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong> <?php esc_html_e( 'Καταχώρηση order metadata και αυτόματης σημείωσης ελέγχου κόστους.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></li>
						</ul>
					</p>
				</div>

				<form method="POST" enctype="multipart/form-data" action="<?php echo esc_url( $gt_invoice_import_url ); ?>" style="margin-top: 20px;">
					<?php wp_nonce_field( 'gt_invoice_upload_nonce', 'gt_invoice_upload_nonce_field' ); ?>
					
					<div style="padding: 20px; border: 2px dashed #b4b9be; background: #fafafa; text-align: center; border-radius: 4px; margin-bottom: 20px;">
						<label for="invoice_csv_file" style="display: block; font-weight: 600; margin-bottom: 10px; font-size: 14px;">
							<?php esc_html_e( 'Επιλογή αρχείου τιμολογίου (.csv):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
						</label>
						<input type="file" name="invoice_csv_file" id="invoice_csv_file" accept=".csv,.txt" required style="font-size: 13px;" />
					</div>

					<button type="submit" name="gt_invoice_upload_file" class="button button-primary button-large" style="background: #005aa4; border-color: #004580;">
						<span class="dashicons dashicons-search" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Ανάγνωση & Προεπισκόπηση Κόστους', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</button>
				</form>
			</div>

			<!-- Last Import Log Card -->
			<div class="card" style="flex: 1; min-width: 320px; max-width: 500px; padding: 25px; margin: 0; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
				<h2 style="margin-top: 0; color: #23282d; font-size: 1.2em;">
					<span class="dashicons dashicons-backup" style="font-size: 22px; vertical-align: middle; margin-right: 5px;"></span>
					<?php esc_html_e( 'Τελευταία Ενημέρωση Τιμολογίου', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</h2>

				<?php if ( $gt_last_invoice_log ) : ?>
					<?php
					$gt_net_diff   = (float) $gt_last_invoice_log['net_difference'];
					$gt_is_prof    = $gt_net_diff >= 0;
					$gt_badge_bg   = $gt_is_prof ? '#e8f5e9' : '#ffebee';
					$gt_badge_fg   = $gt_is_prof ? '#2e7d32' : '#c62828';
					$gt_diff_sign  = $gt_is_prof ? '+' : '';
					?>
					<table class="widefat striped" style="margin-top: 15px; border-radius: 4px;">
						<tbody>
							<tr>
								<td style="font-weight: 600; width: 45%;"><?php esc_html_e( 'Ημερομηνία:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
								<td><?php echo esc_html( $gt_last_invoice_log['timestamp'] ); ?></td>
							</tr>
							<tr>
								<td style="font-weight: 600;"><?php esc_html_e( 'Αρχείο:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
								<td><code><?php echo esc_html( $gt_last_invoice_log['filename'] ); ?></code></td>
							</tr>
							<tr>
								<td style="font-weight: 600;"><?php esc_html_e( 'Παραγγελίες που ενημερώθηκαν:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
								<td><strong style="color: #005aa4;"><?php echo (int) $gt_last_invoice_log['updated_orders']; ?></strong></td>
							</tr>
							<tr>
								<td style="font-weight: 600;"><?php esc_html_e( 'Κόστος Γεν. Ταχυδρομικής:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
								<td><strong><?php echo esc_html( number_format( (float) $gt_last_invoice_log['total_courier'], 2, ',', '.' ) ); ?> €</strong></td>
							</tr>
							<tr>
								<td style="font-weight: 600;"><?php esc_html_e( 'Χρέωση Πελατών:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
								<td><strong><?php echo esc_html( number_format( (float) $gt_last_invoice_log['total_client'], 2, ',', '.' ) ); ?> €</strong></td>
							</tr>
							<tr>
								<td style="font-weight: 600;"><?php esc_html_e( 'Συνολικό Αποτέλεσμα (P&L):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
								<td>
									<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: bold; background: <?php echo esc_attr( $gt_badge_bg ); ?>; color: <?php echo esc_attr( $gt_badge_fg ); ?>;">
										<?php echo esc_html( $gt_diff_sign . number_format( $gt_net_diff, 2, ',', '.' ) . ' €' ); ?>
										(<?php echo $gt_is_prof ? esc_html__( 'Κέρδος', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) : esc_html__( 'Ζημία', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>)
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				<?php else : ?>
					<div style="padding: 15px; background: #f6f7f7; border-left: 4px solid #b4b9be; margin-top: 15px; border-radius: 3px;">
						<?php esc_html_e( 'Δεν έχει καταγραφεί ακόμα προηγούμενη εισαγωγή τιμολογίου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</div>
				<?php endif; ?>

				<!-- Hub Automation Navigation Box -->
				<div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
					<h3 style="margin: 0 0 8px 0; font-size: 14px;">
						<span class="dashicons dashicons-admin-generic" style="vertical-align: middle; color: #666;"></span>
						<?php esc_html_e( 'Αυτοματοποίηση μέσω Email (Hub)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</h3>
					<p style="font-size: 12px; color: #666; margin: 0 0 12px 0; line-height: 1.5;">
						<?php esc_html_e( 'Μπορείτε να λαμβάνετε και να καταχωρείτε αυτόματα τα έξοδα τιμολογίων απευθείας από το Gmail μέσω του Ενιαίου Google Apps Script.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</p>
					<a href="<?php echo esc_url( $gt_settings_url ); ?>" class="button button-secondary">
						<span class="dashicons dashicons-external" style="vertical-align: middle; margin-right: 3px;"></span>
						<?php esc_html_e( 'Μετάβαση στο Hub Ρυθμίσεων & Αυτοματισμού', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</a>
				</div>

			</div>
		</div>

	<!-- Step 2: Preview -->
	<?php elseif ( 'preview' === $gt_action_step ) : ?>
		<?php
		$gt_total_rows   = count( $gt_records );
		$gt_ready_count  = 0;
		$gt_already_cnt  = 0;
		$gt_not_fnd_cnt  = 0;
		$gt_courier_sum  = 0.0;
		$gt_client_sum   = 0.0;

		foreach ( $gt_records as $gt_r ) {
			if ( 'ready' === $gt_r['status_code'] ) {
				$gt_ready_count++;
				$gt_courier_sum += (float) $gt_r['total_cost'];
				$gt_client_sum  += (float) $gt_r['client_total_charged'];
			} elseif ( 'already_imported' === $gt_r['status_code'] ) {
				$gt_already_cnt++;
			} else {
				$gt_not_fnd_cnt++;
			}
		}

		$gt_diff_sum    = $gt_client_sum - $gt_courier_sum;
		$gt_is_prof_sum = $gt_diff_sum >= 0;
		$gt_sum_bg      = $gt_is_prof_sum ? '#e8f5e9' : '#ffebee';
		$gt_sum_fg      = $gt_is_prof_sum ? '#2e7d32' : '#c62828';
		$gt_diff_sign   = $gt_is_prof_sum ? '+' : '';
		?>

		<div class="card" style="margin-bottom: 20px; padding: 15px 20px; border-radius: 6px;">
			<h2 style="margin-top: 0; color: #005aa4;">
				<span class="dashicons dashicons-analytics" style="vertical-align: middle;"></span>
				<?php esc_html_e( 'Προεπισκόπηση & Οικονομικός Έλεγχος Τιμολογίου', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>

			<div style="display: flex; flex-wrap: wrap; gap: 15px; margin: 15px 0;">
				<div style="flex: 1; min-width: 140px; padding: 12px; background: #f0f7fc; border-radius: 4px; text-align: center;">
					<div style="font-size: 11px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'Γραμμές Αρχείου', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
					<div style="font-size: 20px; font-weight: bold; color: #005aa4;"><?php echo (int) $gt_total_rows; ?></div>
				</div>
				<div style="flex: 1; min-width: 140px; padding: 12px; background: #e7f5ea; border-radius: 4px; text-align: center;">
					<div style="font-size: 11px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'Έτοιμες προς Ενημέρωση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
					<div style="font-size: 20px; font-weight: bold; color: #28a745;"><?php echo (int) $gt_ready_count; ?></div>
				</div>
				<div style="flex: 1; min-width: 140px; padding: 12px; background: #fdf7e7; border-radius: 4px; text-align: center;">
					<div style="font-size: 11px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'Κόστος Γεν. Ταχυδρομικής', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
					<div style="font-size: 20px; font-weight: bold; color: #856404;"><?php echo esc_html( number_format( $gt_courier_sum, 2, ',', '.' ) ); ?> €</div>
				</div>
				<div style="flex: 1; min-width: 140px; padding: 12px; background: #eef2f5; border-radius: 4px; text-align: center;">
					<div style="font-size: 11px; text-transform: uppercase; color: #666;"><?php esc_html_e( 'Είσπραξη από Πελάτες', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
					<div style="font-size: 20px; font-weight: bold; color: #333;"><?php echo esc_html( number_format( $gt_client_sum, 2, ',', '.' ) ); ?> €</div>
				</div>
				<div style="flex: 1; min-width: 160px; padding: 12px; background: <?php echo esc_attr( $gt_sum_bg ); ?>; border-radius: 4px; text-align: center;">
					<div style="font-size: 11px; text-transform: uppercase; color: <?php echo esc_attr( $gt_sum_fg ); ?>; font-weight: 600;"><?php esc_html_e( 'Καθαρό Αποτέλεσμα (P&L)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></div>
					<div style="font-size: 20px; font-weight: bold; color: <?php echo esc_attr( $gt_sum_fg ); ?>;"><?php echo esc_html( $gt_diff_sign . number_format( $gt_diff_sum, 2, ',', '.' ) ); ?> €</div>
				</div>
			</div>
		</div>

		<form method="POST" action="<?php echo esc_url( $gt_invoice_import_url ); ?>">
			<?php wp_nonce_field( 'gt_invoice_confirm_nonce', 'gt_invoice_confirm_nonce_field' ); ?>
			<input type="hidden" name="records_payload" value="<?php echo esc_attr( wp_json_encode( $gt_records ) ); ?>" />
			<input type="hidden" name="gt_invoice_filename" value="<?php echo esc_attr( $gt_uploaded_filename ?? '' ); ?>" />

			<div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
				<div>
					<button type="submit" name="gt_invoice_confirm_import" class="button button-primary button-large" style="background: #005aa4; border-color: #004580;">
						<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-right: 4px;"></span>
						<?php esc_html_e( 'Αποθήκευση & Ενημέρωση Επιλεγμένων Παραγγελιών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</button>
					<a href="<?php echo esc_url( $gt_invoice_import_url ); ?>" class="button button-secondary" style="margin-left: 8px;">
						<?php esc_html_e( 'Ακύρωση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
					</a>
				</div>
				<div style="font-size: 13px; color: #555;">
					<label>
						<input type="checkbox" id="gt_invoice_toggle_all" checked onchange="document.querySelectorAll('.gt-invoice-row-cb').forEach(cb => { if(!cb.disabled) cb.checked = this.checked; });" />
						<strong><?php esc_html_e( 'Επιλογή όλων των έτοιμων εγγραφών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></strong>
					</label>
				</div>
			</div>

			<table class="widefat striped" style="border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-size: 12px;">
				<thead>
					<tr>
						<th style="width: 35px; text-align: center;"><input type="checkbox" checked onchange="document.getElementById('gt_invoice_toggle_all').click();" /></th>
						<th><?php esc_html_e( 'Παραγγελία / Voucher', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Παραλήπτης / Προορισμός', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Βάρος', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Κόστος ΓΤ (Ανάλυση)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Χρέωση Πελάτη (Μεταφ + Α/Κ)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Διαφορά (P&L)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
						<th><?php esc_html_e( 'Κατάσταση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $gt_records as $gt_idx => $gt_r ) : ?>
						<?php
						$gt_is_ready    = ( 'ready' === $gt_r['status_code'] );
						$gt_is_already  = ( 'already_imported' === $gt_r['status_code'] );
						$gt_row_bg      = $gt_is_ready ? '' : ( $gt_is_already ? '#fcfcfc' : '#fff9f9' );
						$gt_diff_val    = (float) $gt_r['difference'];
						$gt_is_profit   = ( $gt_diff_val >= 0 );
						$gt_diff_prefix = $gt_is_profit ? '+' : '';
						?>
						<tr style="<?php echo $gt_row_bg ? 'background: ' . esc_attr( $gt_row_bg ) . ';' : ''; ?>">
							<td style="text-align: center;">
								<input type="checkbox" name="selected_rows[]" value="<?php echo esc_attr( $gt_idx ); ?>" class="gt-invoice-row-cb" <?php echo $gt_is_ready ? 'checked' : ( $gt_is_already ? 'disabled' : 'disabled' ); ?> />
							</td>
							<td>
								<?php if ( ! empty( $gt_r['order_id'] ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $gt_r['order_id'] . '&action=edit' ) ); ?>" target="_blank" style="font-weight: 600;">
										#<?php echo esc_html( $gt_r['order_id'] ); ?>
									</a>
								<?php else : ?>
									<span style="color: #999;">-</span>
								<?php endif; ?>
								<br>
								<code style="font-size: 11px;"><?php echo esc_html( $gt_r['voucher'] ); ?></code>
								<?php if ( ! empty( $gt_r['full_invoice'] ) ) : ?>
									<br><span style="font-size: 10px; color: #888;"><?php echo esc_html( $gt_r['full_invoice'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( $gt_r['recipient'] ? $gt_r['recipient'] : '-' ); ?></strong>
								<br><span style="color: #666;"><?php echo esc_html( $gt_r['destination'] ? $gt_r['destination'] : '-' ); ?></span>
							</td>
							<td>
								<?php echo esc_html( number_format( (float) $gt_r['weight'], 1, ',', '.' ) ); ?> kg
							</td>
							<td>
								<strong><?php echo esc_html( number_format( (float) $gt_r['total_cost'], 2, ',', '.' ) ); ?> €</strong>
								<span style="color: #666; font-size: 11px;">(Αξία Μεταφ.: <?php echo esc_html( number_format( (float) $gt_r['base_cost'], 2, ',', '.' ) ); ?>€)</span>
								<?php if ( ! empty( $gt_r['extra_services'] ) ) : ?>
									<div style="margin-top: 3px;">
										<?php foreach ( $gt_r['extra_services'] as $gt_code => $gt_amt ) : ?>
											<?php
											$gt_label     = isset( GT_Invoice_Importer::SERVICE_LABELS[ $gt_code ] ) ? GT_Invoice_Importer::SERVICE_LABELS[ $gt_code ] : $gt_code;
											$gt_amt_float = (float) $gt_amt;
											if ( $gt_amt_float > 0 ) {
												$gt_badge_txt   = $gt_code . ' (+' . number_format( $gt_amt_float, 2, ',', '.' ) . '€)';
												$gt_badge_title = $gt_label . ': +' . number_format( $gt_amt_float, 2, ',', '.' ) . ' € (+ ΦΠΑ)';
												$gt_badge_style = 'background: #d1ecf1; color: #0c5460; font-weight: 600;';
											} else {
												$gt_badge_txt   = $gt_code;
												$gt_badge_title = $gt_label . ( 'ΑΜ' === $gt_code || 'AM' === $gt_code ? ' (συμπεριλαμβάνεται στη χρέωση)' : '' );
												$gt_badge_style = 'background: #e2e4e7; color: #333333;';
											}
											?>
											<span style="display: inline-block; padding: 1px 5px; font-size: 10px; border-radius: 3px; <?php echo esc_attr( $gt_badge_style ); ?> margin-right: 2px;" title="<?php echo esc_attr( $gt_badge_title ); ?>">
												<?php echo esc_html( $gt_badge_txt ); ?>
											</span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $gt_r['order_id'] ) ) : ?>
									<strong><?php echo esc_html( number_format( (float) $gt_r['client_total_charged'], 2, ',', '.' ) ); ?> €</strong>
									<br>
									<span style="color: #666; font-size: 11px;">
										(Μεταφ: <?php echo esc_html( number_format( (float) $gt_r['client_shipping_net'], 2, ',', '.' ) ); ?>€
										<?php if ( (float) $gt_r['client_cod_fee'] > 0 ) : ?>
											+ Α/Κ: <?php echo esc_html( number_format( (float) $gt_r['client_cod_fee'], 2, ',', '.' ) ); ?>€
										<?php endif; ?>)
									</span>
								<?php else : ?>
									<span style="color: #999;">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $gt_r['order_id'] ) ) : ?>
									<?php
									$gt_pill_bg = $gt_is_profit ? '#e8f5e9' : '#ffebee';
									$gt_pill_fg = $gt_is_profit ? '#2e7d32' : '#c62828';
									?>
									<span style="display: inline-block; padding: 2px 7px; border-radius: 4px; font-weight: bold; font-size: 11px; background: <?php echo esc_attr( $gt_pill_bg ); ?>; color: <?php echo esc_attr( $gt_pill_fg ); ?>;">
										<?php echo esc_html( $gt_diff_prefix . number_format( $gt_diff_val, 2, ',', '.' ) . ' €' ); ?>
									</span>
								<?php else : ?>
									<span style="color: #999;">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $gt_is_ready ) : ?>
									<span style="display: inline-block; padding: 2px 6px; font-size: 11px; background: #e7f5ea; color: #28a745; border-radius: 3px; font-weight: 600;">
										✓ <?php esc_html_e( 'Έτοιμο', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
									</span>
								<?php elseif ( $gt_is_already ) : ?>
									<span style="display: inline-block; padding: 2px 6px; font-size: 11px; background: #f0f0f1; color: #666; border-radius: 3px;">
										<?php esc_html_e( 'Καταχωρημένο', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
									</span>
								<?php else : ?>
									<span style="display: inline-block; padding: 2px 6px; font-size: 11px; background: #f8d7da; color: #721c24; border-radius: 3px;">
										<?php esc_html_e( 'Μη διαθέσιμο', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div style="margin-top: 15px;">
				<button type="submit" name="gt_invoice_confirm_import" class="button button-primary button-large" style="background: #005aa4; border-color: #004580;">
					<span class="dashicons dashicons-saved" style="vertical-align: middle; margin-right: 4px;"></span>
					<?php esc_html_e( 'Αποθήκευση & Ενημέρωση Επιλεγμένων Παραγγελιών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</button>
			</div>
		</form>

	<!-- Step 3: Completed -->
	<?php elseif ( 'completed' === $gt_action_step ) : ?>
		<div class="card" style="padding: 20px; border-radius: 6px;">
			<h2 style="margin-top: 0; color: #28a745;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 26px; vertical-align: middle;"></span>
				<?php esc_html_e( 'Η καταχώρηση των κοστών τιμολογίου ολοκληρώθηκε επιτυχώς!', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</h2>

			<p style="font-size: 14px;">
				<?php esc_html_e( 'Τα κόστη μεταφορικών και ο διαχωρισμός πρόσθετων υπηρεσιών (Αντικαταβολή, Ζώνη Β, Δυσπρόσιτα κλπ) αποθηκεύτηκαν στα metadata των παραγγελιών και προστέθηκαν αντίστοιχες σημειώσεις ελέγχου κέρδους/ζημίας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
			</p>

			<div style="margin-top: 20px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Μετάβαση στη Λίστα Παραγγελιών', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</a>
				<a href="<?php echo esc_url( $gt_invoice_import_url ); ?>" class="button button-secondary" style="margin-left: 10px;">
					<?php esc_html_e( 'Νέα Εισαγωγή Τιμολογίου', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?>
				</a>
			</div>
		</div>
	<?php endif; ?>

</div>
