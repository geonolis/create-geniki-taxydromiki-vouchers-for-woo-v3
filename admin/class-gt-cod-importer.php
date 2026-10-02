<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles importing and processing Geniki Taxydromiki COD (Cash On Delivery) payment files.
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin
 * @author     Γεώργιος Παπαμανώλης <geonolis@hotmail.com>
 */
class GT_COD_Importer {

	/** 
	 * Reads and decodes a Geniki Taxydromiki CSV file, normalizing encoding to UTF-8.
	 *
	 * Geniki Taxydromiki typically sends  tab-delimited files encoded in UTF-16LE with BOM.
	 * This method handles UTF-16LE, UTF-16BE, UTF-8 (with/without BOM), and Windows-1253.
	 *
	 * @param string $raw Raw byte string from file or webhook payload.
	 * @return string Normalized UTF-8 string.
	 */
	public static function normalize_content( string $raw ): string {
		// Detect and strip BOM / convert encoding
		if ( substr( $raw, 0, 2 ) === "\xFF\xFE" ) {
			// UTF-16LE with BOM
			$content = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16LE' );
		} elseif ( substr( $raw, 0, 2 ) === "\xFE\xFF" ) {
			// UTF-16BE with BOM
			$content = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16BE' );
		} elseif ( substr( $raw, 0, 3 ) === "\xEF\xBB\xBF" ) {
			// UTF-8 with BOM
			$content = substr( $raw, 3 );
		} else {
			// Check for null bytes indicating UTF-16LE without BOM
			$sample = substr( $raw, 0, min( 200, strlen( $raw ) ) );
			if ( substr_count( $sample, "\x00" ) > 5 ) {
				$content = mb_convert_encoding( $raw, 'UTF-8', 'UTF-16LE' );
			} elseif ( mb_check_encoding( $raw, 'UTF-8' ) ) {
				$content = $raw;
			} else {
				// Fallback to Windows-1253 (Greek) or ISO-8859-7
				$content = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1253' );
			}
		}

		// Remove any remaining zero-width BOM (U+FEFF)
		if ( mb_substr( $content, 0, 1, 'UTF-8' ) === "\xEF\xBB\xBF" || mb_substr( $content, 0, 1, 'UTF-8' ) === "\u{FEFF}" ) {
			$content = mb_substr( $content, 1, null, 'UTF-8' );
		}

		return $content;
	}

	/** 
	 * Reads and decodes a Geniki Taxydromiki CSV file, normalizing encoding to UTF-8.
	 *
	 * @param string $file_path Path to the uploaded temporary file.
	 * @return string|WP_Error Normalized UTF-8 string or WP_Error on failure.
	 */
	public static function read_file_content( string $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'gt_file_missing', __( 'Το αρχείο δεν βρέθηκε ή δεν είναι αναγνώσιμο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		$raw = file_get_contents( $file_path );
		if ( false === $raw || strlen( $raw ) === 0 ) {
			return new WP_Error( 'gt_file_empty', __( 'Το αρχείο είναι κενό.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		return self::normalize_content( $raw );
	}

	/**
	 * Retrieve or generate the webhook secret key for automated imports.
	 *
	 * @return string Secret key string.
	 */
	public static function get_webhook_secret(): string {
		$secret = get_option( 'gtvfw_cod_webhook_secret' );
		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 32, false );
			update_option( 'gtvfw_cod_webhook_secret', $secret );
		}
		return $secret;
	}

	/**
	 * Processes raw CSV text or binary bytes from webhook / automated import.
	 *
	 * @param string $raw_bytes Raw binary or text string from email attachment.
	 * @param string $filename Name of the file being processed.
	 * @return array|WP_Error Processing summary or WP_Error on failure.
	 */
	public static function process_raw_content( string $raw_bytes, string $filename = '' ) {
		if ( empty( $raw_bytes ) ) {
			return new WP_Error( 'gt_empty_content', __( 'Δεν ελήφθησαν δεδομένα αρχείου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		$normalized = self::normalize_content( $raw_bytes );
		$raw_rows   = self::parse_csv_content( $normalized );

		if ( is_wp_error( $raw_rows ) ) {
			return $raw_rows;
		}

		if ( empty( $raw_rows ) ) {
			return new WP_Error( 'gt_no_rows', __( 'Δεν βρέθηκαν γραμμές αποστολών στο αρχείο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		$matched_records = self::match_records( $raw_rows );

		$updated_count = 0;
		$already_paid  = 0;
		$not_found     = 0;
		$error_count   = 0;
		$total_amount  = 0.0;
		$details       = array();

		foreach ( $matched_records as $rec ) {
			if ( 'already_paid' === $rec['status_code'] ) {
				$already_paid++;
				$details[] = array(
					'order_id' => $rec['order_id'],
					'voucher'  => $rec['voucher'],
					'status'   => 'already_paid',
				);
			} elseif ( ! empty( $rec['order_id'] ) && in_array( $rec['status_code'], array( 'ready', 'amount_mismatch' ), true ) ) {
				$res = self::mark_order_cod_paid( $rec['order_id'], $rec );
				if ( true === $res ) {
					$updated_count++;
					$total_amount += (float) $rec['amount'];
					$details[] = array(
						'order_id' => $rec['order_id'],
						'voucher'  => $rec['voucher'],
						'amount'   => $rec['amount'],
						'status'   => 'updated',
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
			'timestamp'      => current_time( 'mysql' ),
			'filename'       => $filename ? sanitize_file_name( $filename ) : 'automated_import.csv',
			'total_rows'     => count( $matched_records ),
			'updated_orders' => $updated_count,
			'already_paid'   => $already_paid,
			'not_found'      => $not_found,
			'errors'         => $error_count,
			'total_amount'   => $total_amount,
			'details'        => $details,
		);

		update_option( 'gtvfw_cod_webhook_last_log', $summary );

		return $summary;
	}

	/**
	 * Parses normalized CSV text content into structured record arrays.
	 *
	 * @param string $content UTF-8 CSV content.
	 * @return array|WP_Error Array of parsed rows or WP_Error.
	 */
	public static function parse_csv_content( string $content ) {
		$lines = preg_split( '/\r\n|\r|\n/', trim( $content ) );
		if ( empty( $lines ) ) {
			return new WP_Error( 'gt_no_lines', __( 'Δεν βρέθηκαν δεδομένα στο αρχείο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		// Detect delimiter in the first line
		$first_line = $lines[0];
		$delimiters = array( "\t", ';', ',' );
		$delimiter  = "\t";
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
			'ship_date'     => 0,
			'voucher'       => 1,
			'client_ref'    => 2,
			'destination'   => 3,
			'recipient'     => 4,
			'delivery_date' => 5,
			'amount'        => 6,
		);

		foreach ( $header as $idx => $col_name ) {
			$clean = mb_strtolower( preg_replace( '/[\s\._\-]/u', '', $col_name ), 'UTF-8' );
			if ( strpos( $clean, 'αποδεικτικο' ) !== false || strpos( $clean, 'voucher' ) !== false ) {
				$col_map['voucher'] = $idx;
			} elseif ( strpos( $clean, 'αναγνωριστικο' ) !== false || strpos( $clean, 'order' ) !== false || strpos( $clean, 'πελατη' ) !== false ) {
				$col_map['client_ref'] = $idx;
			} elseif ( strpos( $clean, 'ημαποστολης' ) !== false || strpos( $clean, 'αποστολη' ) !== false ) {
				$col_map['ship_date'] = $idx;
			} elseif ( strpos( $clean, 'ημπαραδοσης' ) !== false || strpos( $clean, 'παραδοση' ) !== false ) {
				$col_map['delivery_date'] = $idx;
			} elseif ( strpos( $clean, 'ποσο' ) !== false || strpos( $clean, 'amount' ) !== false || strpos( $clean, 'cod' ) !== false ) {
				$col_map['amount'] = $idx;
			} elseif ( strpos( $clean, 'παραληπτης' ) !== false || strpos( $clean, 'recipient' ) !== false ) {
				$col_map['recipient'] = $idx;
			} elseif ( strpos( $clean, 'προς' ) !== false || strpos( $clean, 'destination' ) !== false ) {
				$col_map['destination'] = $idx;
			}
		}

		$records = array();
		for ( $i = 1; $i < count( $lines ); $i++ ) {
			$line = trim( $lines[ $i ] );
			if ( empty( $line ) ) {
				continue;
			}

			$cols = str_getcsv( $line, $delimiter );
			if ( count( $cols ) < 2 ) {
				continue;
			}

			$voucher_val = isset( $cols[ $col_map['voucher'] ] ) ? trim( $cols[ $col_map['voucher'] ] ) : '';
			if ( empty( $voucher_val ) ) {
				continue;
			}

			$amount_raw = isset( $cols[ $col_map['amount'] ] ) ? trim( $cols[ $col_map['amount'] ] ) : '0';
			$amount_num = self::parse_amount( $amount_raw );

			$records[] = array(
				'row_num'       => $i + 1,
				'ship_date'     => isset( $cols[ $col_map['ship_date'] ] ) ? trim( $cols[ $col_map['ship_date'] ] ) : '',
				'voucher'       => $voucher_val,
				'client_ref'    => isset( $cols[ $col_map['client_ref'] ] ) ? trim( $cols[ $col_map['client_ref'] ] ) : '',
				'destination'   => isset( $cols[ $col_map['destination'] ] ) ? trim( $cols[ $col_map['destination'] ] ) : '',
				'recipient'     => isset( $cols[ $col_map['recipient'] ] ) ? trim( $cols[ $col_map['recipient'] ] ) : '',
				'delivery_date' => isset( $cols[ $col_map['delivery_date'] ] ) ? trim( $cols[ $col_map['delivery_date'] ] ) : '',
				'amount_raw'    => $amount_raw,
				'amount'        => $amount_num,
			);
		}

		return $records;
	}

	/**
	 * Converts a Greek localized formatted amount (e.g. "29,37" or "1.234,56") to float.
	 *
	 * @param string $val Raw string representation.
	 * @return float Clean numeric value.
	 */
	public static function parse_amount( string $val ): float {
		$clean = preg_replace( '/[^\d,.\-]/', '', $val );
		if ( strpos( $clean, ',' ) !== false && strpos( $clean, '.' ) !== false ) {
			// e.g. 1.234,56
			$clean = str_replace( '.', '', $clean );
			$clean = str_replace( ',', '.', $clean );
		} elseif ( strpos( $clean, ',' ) !== false ) {
			// e.g. 29,37
			$clean = str_replace( ',', '.', $clean );
		}
		return (float) $clean;
	}

	/**
	 * Finds a WooCommerce order by Geniki Taxydromiki voucher number or client reference.
	 * Compatible with HPOS (Custom Orders Table) and traditional posts storage.
	 *
	 * @param string $voucher Voucher number (Αποδεικτικό).
	 * @param string $client_ref Client reference from file (e.g. "#56006").
	 * @return WC_Order|false Order object if found, false otherwise.
	 */
	public static function find_order( string $voucher, string $client_ref = '' ) {
		$voucher = trim( $voucher );

		// 1. Primary lookup by courier_voucher meta key using standard wc_get_orders
		if ( ! empty( $voucher ) && function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders( array(
				'meta_key'   => 'courier_voucher',
				'meta_value' => $voucher,
				'limit'      => 1,
				'return'     => 'ids',
			) );

			if ( ! empty( $orders ) ) {
				$found = wc_get_order( $orders[0] );
				if ( $found instanceof WC_Order ) {
					return $found;
				}
			}
		}

		// 2. Direct database query fallback for HPOS or legacy postmeta
		if ( ! empty( $voucher ) ) {
			global $wpdb;

			// HPOS meta table check
			$hpos_table = $wpdb->prefix . 'wc_orders_meta';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) ) === $hpos_table ) {
				$order_id = $wpdb->get_var( $wpdb->prepare(
					"SELECT order_id FROM {$wpdb->prefix}wc_orders_meta WHERE meta_key = 'courier_voucher' AND meta_value = %s LIMIT 1",
					$voucher
				) );
				if ( ! empty( $order_id ) ) {
					$found = wc_get_order( (int) $order_id );
					if ( $found instanceof WC_Order ) {
						return $found;
					}
				}
			}

			// Postmeta fallback
			$post_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'courier_voucher' AND meta_value = %s LIMIT 1",
				$voucher
			) );
			if ( ! empty( $post_id ) ) {
				$found = wc_get_order( (int) $post_id );
				if ( $found instanceof WC_Order ) {
					return $found;
				}
			}
		}

		// 3. Fallback lookup by order ID in client_ref (e.g. "#56006")
		if ( ! empty( $client_ref ) ) {
			$clean_id = preg_replace( '/[^0-9]/', '', $client_ref );
			if ( ! empty( $clean_id ) ) {
				$found = wc_get_order( (int) $clean_id );
				if ( $found instanceof WC_Order ) {
					return $found;
				}
			}
		}

		return false;
	}

	/**
	 * Matches parsed CSV records against existing WooCommerce orders and determines match status.
	 *
	 * @param array $records Parsed CSV records.
	 * @return array Annotated records with order details and match statuses.
	 */
	public static function match_records( array $records ): array {
		$annotated = array();

		foreach ( $records as $rec ) {
			$order = self::find_order( $rec['voucher'], $rec['client_ref'] );

			if ( ! $order ) {
				$rec['order_id']       = 0;
				$rec['order']          = null;
				$rec['status_code']    = 'not_found';
				$rec['status_label']   = __( 'Δεν βρέθηκε παραγγελία', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
				$rec['order_total']    = 0.0;
				$rec['order_status']   = '';
				$rec['customer_name']  = '';
				$rec['is_already_paid']= false;
				$rec['is_selectable']  = false;
				$rec['default_checked']= false;
			} else {
				$order_id           = $order->get_id();
				$order_total        = (float) $order->get_total();
				$is_paid            = ( $order->get_meta( 'gt_cod_paid' ) === 'yes' );
				$paid_date          = $order->get_meta( 'gt_cod_paid_date' );
				$customer_name      = trim( $order->get_formatted_billing_full_name() );
				if ( empty( $customer_name ) ) {
					$customer_name  = trim( $order->get_formatted_shipping_full_name() );
				}

				$rec['order_id']        = $order_id;
				$rec['order']           = $order;
				$rec['order_total']     = $order_total;
				$rec['order_status']    = wc_get_order_status_name( $order->get_status() );
				$rec['customer_name']   = $customer_name;
				$rec['is_already_paid'] = $is_paid;
				$rec['paid_date']       = $paid_date;

				// Check amount difference (> 0.05€ tolerance)
				$amount_differs = abs( $order_total - $rec['amount'] ) > 0.05;

				if ( $is_paid ) {
					$rec['status_code']    = 'already_paid';
					$rec['status_label']   = sprintf( __( 'Έχει εξοφληθεί (%s)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $paid_date ? substr( $paid_date, 0, 10 ) : '' );
					$rec['is_selectable']  = true;
					$rec['default_checked']= false; // Don't check duplicates by default
				} elseif ( $amount_differs ) {
					$rec['status_code']    = 'amount_mismatch';
					$rec['status_label']   = sprintf( __( 'Ασυμφωνία ποσού (Παραγγελία: %s€)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), number_format( $order_total, 2, ',', '.' ) );
					$rec['is_selectable']  = true;
					$rec['default_checked']= true;
				} else {
					$rec['status_code']    = 'ready';
					$rec['status_label']   = __( 'Έτοιμο προς ενημέρωση', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
					$rec['is_selectable']  = true;
					$rec['default_checked']= true;
				}
			}

			$annotated[] = $rec;
		}

		return $annotated;
	}

	/**
	 * Updates a WooCommerce order marking COD payment as received.
	 *
	 * Adds an internal order note and stores custom order metadata.
	 *
	 * @param int|WC_Order $order_or_id WooCommerce order or ID.
	 * @param array $record COD record data (voucher, amount, delivery_date).
	 * @return bool|WP_Error True on success, WP_Error or false on failure.
	 */
	public static function mark_order_cod_paid( $order_or_id, array $record ) {
		$order = ( $order_or_id instanceof WC_Order ) ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order ) {
			return new WP_Error( 'gt_invalid_order', __( 'Μη έγκυρη παραγγελία.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		$amount_formatted = number_format( (float) $record['amount'], 2, ',', '.' ) . ' €';
		$voucher          = sanitize_text_field( $record['voucher'] );
		$delivery_date    = sanitize_text_field( $record['delivery_date'] );

		// 1. Add internal order note
		$note = sprintf(
			/* translators: 1: amount formatted, 2: delivery date, 3: voucher number */
			__( 'Γενική Ταχυδρομική: Επιβεβαίωση πληρωμής αντικαταβολής %1$s στις %2$s (Αρ. Voucher: %3$s).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
			$amount_formatted,
			$delivery_date ? $delivery_date : date_i18n( 'd/m/Y H:i' ),
			$voucher
		);
		$order->add_order_note( $note, 0, true ); // 0 = internal note

		// 2. Set custom order metadata
		$order->update_meta_data( 'gt_cod_paid', 'yes' );
		$order->update_meta_data( 'gt_cod_paid_amount', (float) $record['amount'] );
		$order->update_meta_data( 'gt_cod_paid_date', current_time( 'mysql' ) );
		$order->update_meta_data( 'gt_cod_delivery_date', $delivery_date );
		$order->update_meta_data( 'gt_cod_voucher', $voucher );

		$order->save_meta_data();
		$order->save();

		return true;
	}
}
