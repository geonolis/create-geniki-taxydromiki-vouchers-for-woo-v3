<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles importing and parsing Geniki Taxydromiki Invoice (ΤΠΥ) CSV files,
 * extracting shipping costs, COD charges, and comparing client shipping vs courier cost.
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin
 * @author     Γεώργιος Παπαμανώλης <geonolis@hotmail.com>
 */
class GT_Invoice_Importer {

	/**
	 * Map of Geniki Taxydromiki extra service codes to friendly Greek labels.
	 */
	const SERVICE_LABELS = array(
		'ΑΜ' => 'Αντικαταβολή (COD)',
		'AM' => 'Αντικαταβολή (COD)',
		'ΖΒ' => 'Ζώνη Β',
		'ZB' => 'Ζώνη Β',
		'ΔΠ' => 'Δυσπρόσιτη Περιοχή',
		'DP' => 'Δυσπρόσιτη Περιοχή',
		'Β2' => 'Επιπλέον Βάρος',
		'B2' => 'Επιπλέον Βάρος',
		'ΔΧ' => 'Δυσπρόσιτα Χωρίς Χρέωση',
		'DX' => 'Δυσπρόσιτα Χωρίς Χρέωση',
		'ΣΑ' => 'Σάββατο Παράδοση',
		'SA' => 'Σάββατο Παράδοση',
		'ΠΡ' => 'Πρωινή Παράδοση',
		'PR' => 'Πρωινή Παράδοση',
	);

	/**
	 * Reads and decodes an invoice CSV file.
	 *
	 * @param string $file_path Path to uploaded temporary file.
	 * @return string|WP_Error Normalized UTF-8 string or WP_Error.
	 */
	public static function read_file_content( string $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'gt_file_missing', __( 'Το αρχείο δεν βρέθηκε ή δεν είναι αναγνώσιμο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		$raw = file_get_contents( $file_path );
		if ( false === $raw || strlen( $raw ) === 0 ) {
			return new WP_Error( 'gt_file_empty', __( 'Το αρχείο είναι κενό.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		return GT_COD_Importer::normalize_content( $raw );
	}

	/**
	 * Parse Greek/European decimal string to float (e.g. "4,03" -> 4.03).
	 *
	 * @param string $val
	 * @return float
	 */
	public static function parse_amount( string $val ): float {
		$clean = trim( str_replace( array( '€', ' ' ), '', $val ) );
		if ( strpos( $clean, ',' ) !== false && strpos( $clean, '.' ) !== false ) {
			$clean = str_replace( '.', '', $clean );
			$clean = str_replace( ',', '.', $clean );
		} elseif ( strpos( $clean, ',' ) !== false ) {
			$clean = str_replace( ',', '.', $clean );
		}
		return (float) $clean;
	}

	/**
	 * Parses normalized invoice CSV content into structured row arrays.
	 *
	 * @param string $content UTF-8 CSV string.
	 * @return array|WP_Error Array of raw parsed records or WP_Error.
	 */
	public static function parse_invoice_content( string $content ) {
		$lines = preg_split( '/\r\n|\r|\n/', trim( $content ) );
		if ( empty( $lines ) ) {
			return new WP_Error( 'gt_no_lines', __( 'Δεν βρέθηκαν δεδομένα στο αρχείο τιμολογίου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), array( 'status' => 400 ) );
		}

		// Detect delimiter in first line (typically ';')
		$first_line = $lines[0];
		$delimiters = array( ';', "\t", ',' );
		$delimiter  = ';';
		$max_count  = 0;

		foreach ( $delimiters as $delim ) {
			$count = substr_count( $first_line, $delim );
			if ( $count > $max_count ) {
				$max_count = $count;
				$delimiter = $delim;
			}
		}

		// Parse header
		$raw_header = str_getcsv( $lines[0], $delimiter );
		$header     = array_map( 'trim', $raw_header );

		// Identify column indices
		$col_map = array(
			'series'        => 0,
			'invoice_no'    => 1,
			'voucher'       => 2,
			'ship_date'     => 3,
			'destination'   => 7,
			'recipient'     => 10,
			'parcels'       => 11,
			'weight'        => 12,
			'base_cost'     => 13,
			'extra1_code'   => 14,
			'extra1_cost'   => 15,
			'extra2_code'   => 16,
			'extra2_cost'   => 17,
			'extra3_code'   => 18,
			'extra3_cost'   => 19,
			'extra4_code'   => 20,
			'extra4_cost'   => 21,
			'extra5_code'   => 22,
			'extra5_cost'   => 23,
			'total_cost'    => 24,
			'invoice_date'  => 25,
			'delivery_date' => 26,
			'order_ref'     => count( $header ) - 1,
		);

		// Header name auto-detection
		foreach ( $header as $idx => $col_name ) {
			$clean = mb_strtolower( preg_replace( '/[\s\._\-]/u', '', $col_name ), 'UTF-8' );
			if ( strpos( $clean, 'αραποστολης' ) !== false || strpos( $clean, 'voucher' ) !== false || strpos( $clean, 'αποδεικτικο' ) !== false ) {
				$col_map['voucher'] = $idx;
			} elseif ( strpos( $clean, 'αρτιμολ' ) !== false ) {
				$col_map['invoice_no'] = $idx;
			} elseif ( strpos( $clean, 'σειρα' ) !== false ) {
				$col_map['series'] = $idx;
			} elseif ( strpos( $clean, 'συνολαξια' ) !== false || strpos( $clean, 'συνολο' ) !== false ) {
				$col_map['total_cost'] = $idx;
			} elseif ( strpos( $clean, 'βασικηχρεωση' ) !== false ) {
				$col_map['base_cost'] = $idx;
			} elseif ( strpos( $clean, 'βαρος' ) !== false ) {
				$col_map['weight'] = $idx;
			} elseif ( strpos( $clean, 'ημνιαπαραδοσης' ) !== false ) {
				$col_map['delivery_date'] = $idx;
			} elseif ( strpos( $clean, 'ημνιατιμολ' ) !== false ) {
				$col_map['invoice_date'] = $idx;
			} elseif ( strpos( $clean, 'αναγνωριστικο' ) !== false || strpos( $clean, 'παρατηρησεις' ) !== false ) {
				$col_map['order_ref'] = $idx;
			}
		}

		$records = array();
		for ( $i = 1; $i < count( $lines ); $i++ ) {
			$line = trim( $lines[ $i ] );
			if ( empty( $line ) ) {
				continue;
			}

			$cols = str_getcsv( $line, $delimiter );
			if ( count( $cols ) < 5 ) {
				continue;
			}

			$voucher_val = isset( $cols[ $col_map['voucher'] ] ) ? trim( $cols[ $col_map['voucher'] ] ) : '';
			if ( empty( $voucher_val ) || ! preg_match( '/^\d{10}$/', $voucher_val ) ) {
				continue;
			}

			$order_ref_raw = isset( $cols[ $col_map['order_ref'] ] ) ? trim( $cols[ $col_map['order_ref'] ] ) : '';
			if ( empty( $order_ref_raw ) && isset( $cols[ count( $cols ) - 1 ] ) ) {
				$order_ref_raw = trim( $cols[ count( $cols ) - 1 ] );
			}

			// Parse base cost & total cost
			$base_cost_raw  = isset( $cols[ $col_map['base_cost'] ] ) ? $cols[ $col_map['base_cost'] ] : '0';
			$total_cost_raw = isset( $cols[ $col_map['total_cost'] ] ) ? $cols[ $col_map['total_cost'] ] : '0';
			$base_cost      = self::parse_amount( $base_cost_raw );
			$total_cost     = self::parse_amount( $total_cost_raw );
			$weight         = isset( $cols[ $col_map['weight'] ] ) ? self::parse_amount( $cols[ $col_map['weight'] ] ) : 0.0;

			// Parse extra services (AM, ZB, DP, B2, DX, etc.)
			$extra_services = array();
			$cod_fee        = 0.0;

			$extra_pairs = array(
				array( 'code' => $col_map['extra1_code'], 'cost' => $col_map['extra1_cost'] ),
				array( 'code' => $col_map['extra2_code'], 'cost' => $col_map['extra2_cost'] ),
				array( 'code' => $col_map['extra3_code'], 'cost' => $col_map['extra3_cost'] ),
				array( 'code' => $col_map['extra4_code'], 'cost' => $col_map['extra4_cost'] ),
				array( 'code' => $col_map['extra5_code'], 'cost' => $col_map['extra5_cost'] ),
			);

			foreach ( $extra_pairs as $pair ) {
				$code_val = isset( $cols[ $pair['code'] ] ) ? trim( mb_strtoupper( $cols[ $pair['code'] ], 'UTF-8' ) ) : '';
				$cost_val = isset( $cols[ $pair['cost'] ] ) ? self::parse_amount( $cols[ $pair['cost'] ] ) : 0.0;

				if ( ! empty( $code_val ) ) {
					$extra_services[ $code_val ] = $cost_val;
					if ( 'ΑΜ' === $code_val || 'AM' === $code_val ) {
						$cod_fee = $cost_val;
					}
				}
			}

			$series_val     = isset( $cols[ $col_map['series'] ] ) ? trim( $cols[ $col_map['series'] ] ) : '';
			$invoice_no_val = isset( $cols[ $col_map['invoice_no'] ] ) ? trim( $cols[ $col_map['invoice_no'] ] ) : '';
			$full_invoice   = trim( $series_val . ' ' . $invoice_no_val );

			$records[] = array(
				'row_num'        => $i + 1,
				'series'         => $series_val,
				'invoice_no'     => $invoice_no_val,
				'full_invoice'   => $full_invoice,
				'voucher'        => $voucher_val,
				'order_ref'      => $order_ref_raw,
				'ship_date'      => isset( $cols[ $col_map['ship_date'] ] ) ? trim( $cols[ $col_map['ship_date'] ] ) : '',
				'destination'    => isset( $cols[ $col_map['destination'] ] ) ? trim( $cols[ $col_map['destination'] ] ) : '',
				'recipient'      => isset( $cols[ $col_map['recipient'] ] ) ? trim( $cols[ $col_map['recipient'] ] ) : '',
				'weight'         => $weight,
				'base_cost'      => $base_cost,
				'cod_fee'        => $cod_fee,
				'extra_services' => $extra_services,
				'total_cost'     => $total_cost,
				'invoice_date'   => isset( $cols[ $col_map['invoice_date'] ] ) ? trim( $cols[ $col_map['invoice_date'] ] ) : '',
				'delivery_date'  => isset( $cols[ $col_map['delivery_date'] ] ) ? trim( $cols[ $col_map['delivery_date'] ] ) : '',
			);
		}

		return $records;
	}

	/**
	 * Matches invoice records against WooCommerce orders and calculates shipping cost comparison.
	 *
	 * @param array $raw_records
	 * @return array Enriched records with order details and profit/loss calculation.
	 */
	public static function match_records( array $raw_records ): array {
		$matched = array();

		foreach ( $raw_records as $rec ) {
			$order_id = 0;

			// Priority 1: Match by voucher
			if ( ! empty( $rec['voucher'] ) ) {
				$found_order = GT_COD_Importer::find_order( $rec['voucher'], $rec['order_ref'] ?? '' );
				if ( $found_order ) {
					$order_id = $found_order->get_id();
				}
			}

			// Priority 2: Match by order ID in order_ref (e.g. #55730)
			if ( ! $order_id && ! empty( $rec['order_ref'] ) && preg_match( '/\d+/', $rec['order_ref'], $matches ) ) {
				$candidate_id = intval( $matches[0] );
				$order_check  = wc_get_order( $candidate_id );
				if ( $order_check ) {
					$order_id = $candidate_id;
				}
			}

			$order = $order_id ? wc_get_order( $order_id ) : null;

			if ( ! $order ) {
				$rec['order_id']             = 0;
				$rec['client_shipping_net']  = 0.0;
				$rec['client_cod_fee']       = 0.0;
				$rec['client_total_charged'] = 0.0;
				$rec['difference']           = 0.0;
				$rec['status_code']          = 'not_found';
				$rec['status_label']         = __( 'Δεν βρέθηκε παραγγελία', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
			} else {
				$client_shipping_net = (float) $order->get_shipping_total();

				// Check if client was charged a COD fee
				$client_cod_fee = 0.0;
				foreach ( $order->get_fees() as $fee ) {
					$fee_name = mb_strtolower( $fee->get_name(), 'UTF-8' );
					if ( strpos( $fee_name, 'αντικαταβολ' ) !== false || strpos( $fee_name, 'cod' ) !== false ) {
						$client_cod_fee += (float) $fee->get_total();
					}
				}

				$client_total_charged = $client_shipping_net + $client_cod_fee;
				$courier_cost         = (float) $rec['total_cost'];
				$difference           = $client_total_charged - $courier_cost;

				$already_cost = $order->get_meta( 'gt_courier_shipping_cost' );

				$rec['order_id']             = $order_id;
				$rec['order_number']         = $order->get_order_number();
				$rec['client_shipping_net']  = $client_shipping_net;
				$rec['client_cod_fee']       = $client_cod_fee;
				$rec['client_total_charged'] = $client_total_charged;
				$rec['difference']           = $difference;
				$rec['is_profit']            = ( $difference >= 0 );

				if ( '' !== $already_cost && (float) $already_cost === $courier_cost ) {
					$rec['status_code']  = 'already_imported';
					$rec['status_label'] = __( 'Έχει ήδη καταχωρηθεί', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
				} else {
					$rec['status_code']  = 'ready';
					$rec['status_label'] = __( 'Έτοιμο για καταχώρηση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
				}
			}

			$matched[] = $rec;
		}

		return $matched;
	}

	/**
	 * Saves courier invoice shipping cost to order metadata and adds an audit note.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $record Record containing invoice details.
	 * @return true|WP_Error True on success or WP_Error.
	 */
	public static function save_order_invoice_cost( int $order_id, array $record ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			/* translators: %d: order ID */
			return new WP_Error( 'gt_order_not_found', sprintf( __( 'Η παραγγελία #%d δεν βρέθηκε.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $order_id ) );
		}

		$courier_cost   = (float) $record['total_cost'];
		$base_cost      = (float) $record['base_cost'];
		$cod_fee        = (float) $record['cod_fee'];
		$invoice_no     = ! empty( $record['full_invoice'] ) ? sanitize_text_field( $record['full_invoice'] ) : ( ! empty( $record['invoice_no'] ) ? sanitize_text_field( $record['invoice_no'] ) : '' );
		$invoice_date   = ! empty( $record['invoice_date'] ) ? sanitize_text_field( $record['invoice_date'] ) : '';
		$delivery_date  = ! empty( $record['delivery_date'] ) ? sanitize_text_field( $record['delivery_date'] ) : '';
		$weight         = isset( $record['weight'] ) ? (float) $record['weight'] : 0.0;
		$extra_services = isset( $record['extra_services'] ) ? (array) $record['extra_services'] : array();
		$has_cod        = isset( $extra_services['ΑΜ'] ) || isset( $extra_services['AM'] );

		// Update Order Meta
		$order->update_meta_data( 'gt_courier_shipping_cost', $courier_cost );
		$order->update_meta_data( 'gt_courier_base_cost', $base_cost );
		$order->update_meta_data( 'gt_courier_cod_fee', $cod_fee );
		$order->update_meta_data( 'gt_courier_has_cod', $has_cod ? 'yes' : 'no' );
		$order->update_meta_data( 'gt_courier_invoice_no', $invoice_no );
		$order->update_meta_data( 'gt_courier_invoice_date', $invoice_date );
		$order->update_meta_data( 'gt_courier_weight', $weight );
		if ( ! empty( $delivery_date ) ) {
			$order->update_meta_data( 'gt_courier_delivery_date', $delivery_date );
		}
		if ( ! empty( $extra_services ) ) {
			$order->update_meta_data( 'gt_courier_extra_services', $extra_services );
		}

		// Calculate client comparison
		$client_shipping_net = (float) $order->get_shipping_total();
		$client_cod_fee = 0.0;
		foreach ( $order->get_fees() as $fee ) {
			$fee_name = mb_strtolower( $fee->get_name(), 'UTF-8' );
			if ( strpos( $fee_name, 'αντικαταβολ' ) !== false || strpos( $fee_name, 'cod' ) !== false ) {
				$client_cod_fee += (float) $fee->get_total();
			}
		}
		$client_total = $client_shipping_net + $client_cod_fee;
		$diff         = $client_total - $courier_cost;
		$diff_sign    = $diff >= 0 ? '+' : '';

		// Build friendly extras string (only show separate cost when amount > 0, e.g. ZB: +2,30 €)
		$extras_str_arr = array();
		foreach ( $extra_services as $code => $amt ) {
			$label   = isset( self::SERVICE_LABELS[ $code ] ) ? self::SERVICE_LABELS[ $code ] : $code;
			$amt_val = (float) $amt;
			if ( $amt_val > 0 ) {
				$extras_str_arr[] = sprintf( '%s (+%s €)', $label, number_format( $amt_val, 2, ',', '.' ) );
			} else {
				$extras_str_arr[] = $label;
			}
		}
		$extras_text = ! empty( $extras_str_arr ) ? implode( ', ', $extras_str_arr ) : '-';

		$note = sprintf(
			/* translators: 1: invoice no, 2: courier cost, 3: client total, 4: difference, 5: base, 6: extras */
			__( 'Γενική Ταχυδρομική - Κόστος Τιμολογίου (%1$s): %2$s € | Χρέωση Πελάτη: %3$s € | Διαφορά: %4$s € (Αξία Μεταφ.: %5$s €, Πρόσθετες Υπηρεσίες: %6$s).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
			$invoice_no ? $invoice_no : '-',
			number_format( $courier_cost, 2, ',', '.' ),
			number_format( $client_total, 2, ',', '.' ),
			$diff_sign . number_format( $diff, 2, ',', '.' ),
			number_format( $base_cost, 2, ',', '.' ),
			$extras_text
		);

		$order->add_order_note( $note, false );
		$order->save();

		return true;
	}

	/**
	 * Process raw invoice CSV content (e.g. from webhook or file upload).
	 *
	 * @param string $raw_bytes
	 * @param string $filename
	 * @return array|WP_Error
	 */
	public static function process_raw_content( string $raw_bytes, string $filename = '' ) {
		if ( empty( $raw_bytes ) ) {
			return new WP_Error( 'gt_empty_content', __( 'Δεν ελήφθησαν δεδομένα αρχείου τιμολογίου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), array( 'status' => 400 ) );
		}

		$normalized = GT_COD_Importer::normalize_content( $raw_bytes );
		$raw_rows   = self::parse_invoice_content( $normalized );

		if ( is_wp_error( $raw_rows ) ) {
			return $raw_rows;
		}

		if ( empty( $raw_rows ) ) {
			return new WP_Error( 'gt_no_rows', __( 'Δεν βρέθηκαν γραμμές αποστολών στο αρχείο τιμολογίου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), array( 'status' => 400 ) );
		}

		$matched_records = self::match_records( $raw_rows );

		$updated_count    = 0;
		$already_imported = 0;
		$not_found        = 0;
		$error_count      = 0;
		$total_courier    = 0.0;
		$total_client     = 0.0;
		$details          = array();

		foreach ( $matched_records as $rec ) {
			if ( 'already_imported' === $rec['status_code'] ) {
				$already_imported++;
				$details[] = array(
					'order_id' => $rec['order_id'],
					'voucher'  => $rec['voucher'],
					'status'   => 'already_imported',
				);
			} elseif ( ! empty( $rec['order_id'] ) && 'ready' === $rec['status_code'] ) {
				$res = self::save_order_invoice_cost( $rec['order_id'], $rec );
				if ( true === $res ) {
					$updated_count++;
					$total_courier += (float) $rec['total_cost'];
					$total_client  += (float) $rec['client_total_charged'];
					$details[] = array(
						'order_id'     => $rec['order_id'],
						'voucher'      => $rec['voucher'],
						'courier_cost' => $rec['total_cost'],
						'client_total' => $rec['client_total_charged'],
						'difference'   => $rec['difference'],
						'status'       => 'updated',
					);
				} else {
					$error_count++;
					$details[] = array(
						'order_id' => $rec['order_id'],
						'voucher'  => $rec['voucher'],
						'status'   => 'error',
						'message'  => is_wp_error( $res ) ? $res->get_error_message() : 'Error',
					);
				}
			} else {
				$not_found++;
				$details[] = array(
					'voucher' => $rec['voucher'],
					'status'  => 'not_found',
				);
			}
		}

		$summary = array(
			'timestamp'        => current_time( 'mysql' ),
			'filename'         => $filename ? sanitize_file_name( $filename ) : 'invoice_import.csv',
			'total_rows'       => count( $matched_records ),
			'updated_orders'   => $updated_count,
			'already_imported' => $already_imported,
			'not_found'        => $not_found,
			'errors'           => $error_count,
			'total_courier'    => $total_courier,
			'total_client'     => $total_client,
			'net_difference'   => $total_client - $total_courier,
			'details'          => $details,
		);

		update_option( 'gtvfw_invoice_last_log', $summary );

		return $summary;
	}

}
