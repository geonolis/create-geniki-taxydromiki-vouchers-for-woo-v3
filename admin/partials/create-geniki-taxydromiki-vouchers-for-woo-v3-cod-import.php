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
