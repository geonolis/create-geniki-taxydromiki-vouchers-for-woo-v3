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
		try {
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
					// Fallback to Windows-1253 (Greek) via iconv or ISO-8859-7
					$content = '';
					if ( function_exists( 'iconv' ) ) {
						$conv = @iconv( 'Windows-1253', 'UTF-8//IGNORE', $raw );
						if ( false !== $conv && ! empty( $conv ) ) {
							$content = $conv;
						} else {
							$conv_iso = @iconv( 'ISO-8859-7', 'UTF-8//IGNORE', $raw );
							if ( false !== $conv_iso && ! empty( $conv_iso ) ) {
								$content = $conv_iso;
							}
						}
					}
					if ( empty( $content ) ) {
						$content = mb_convert_encoding( $raw, 'UTF-8', 'ISO-8859-7' );
					}
				}
			}

			// Remove any remaining zero-width BOM (U+FEFF)
			if ( mb_substr( $content, 0, 1, 'UTF-8' ) === "\xEF\xBB\xBF" || mb_substr( $content, 0, 1, 'UTF-8' ) === "\u{FEFF}" ) {
				$content = mb_substr( $content, 1, null, 'UTF-8' );
			}

			return $content;
		} catch ( \Throwable $e ) {
			return $raw;
		}
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
	 * Retrieve active automated ingestion method ('webhook', 'imap', or 'disabled').
	 *
	 * @return string
	 */
	public static function get_auto_method(): string {
		return get_option( 'gtvfw_cod_auto_method', 'webhook' );
	}

	/**
	 * Retrieve IMAP server settings.
	 *
	 * @return array
	 */
	public static function get_imap_settings(): array {
		$defaults = array(
			'host'          => 'imap.gmail.com',
			'port'          => 993,
			'encryption'    => 'ssl',
			'username'      => '',
			'password'      => '',
			'folder'        => 'INBOX',
			'sender_filter' => 'cod@taxydromiki.gr',
			'schedule'      => 'hourly',
		);
		$saved = get_option( 'gtvfw_cod_imap_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Save IMAP server settings and sync cron schedule.
	 *
	 * @param array $settings
	 * @return void
	 */
	public static function save_imap_settings( array $settings ): void {
		update_option( 'gtvfw_cod_imap_settings', $settings );
		self::sync_cron_schedule( $settings );
	}

	/**
	 * Synchronize WP-Cron schedule for IMAP checking.
	 *
	 * @param array|null $settings
	 * @return void
	 */
	public static function sync_cron_schedule( array $settings = null ): void {
		if ( null === $settings ) {
			$settings = self::get_imap_settings();
		}
		$method   = self::get_auto_method();
		$schedule = ! empty( $settings['schedule'] ) ? $settings['schedule'] : 'hourly';
		$hook     = 'gtvfw_cod_imap_cron_check';

		$timestamp = wp_next_scheduled( $hook );

		if ( 'imap' !== $method || 'manual' === $schedule || empty( $settings['username'] ) || empty( $settings['password'] ) ) {
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
			return;
		}

		if ( $timestamp ) {
			$current_schedule = wp_get_schedule( $hook );
			if ( $current_schedule === $schedule ) {
				return;
			}
			wp_unschedule_event( $timestamp, $hook );
		}

		wp_schedule_event( time() + 60, $schedule, $hook );
	}

	/**
	 * Formats IMAP connection string for imap_open.
	 *
	 * @param array $settings
	 * @return string
	 */
	public static function get_imap_mailbox_string( array $settings ): string {
		$host       = ! empty( $settings['host'] ) ? trim( $settings['host'] ) : 'imap.gmail.com';
		$port       = ! empty( $settings['port'] ) ? intval( $settings['port'] ) : 993;
		$encryption = ! empty( $settings['encryption'] ) ? strtolower( trim( $settings['encryption'] ) ) : 'ssl';
		$folder     = ! empty( $settings['folder'] ) ? trim( $settings['folder'] ) : 'INBOX';

		$flags = '/imap';
		if ( 'ssl' === $encryption ) {
			$flags .= '/ssl/novalidate-cert';
		} elseif ( 'tls' === $encryption ) {
			$flags .= '/tls/novalidate-cert';
		} else {
			$flags .= '/notls';
		}

		return '{' . $host . ':' . $port . $flags . '}' . $folder;
	}

	/**
	 * Tests IMAP connection with provided or saved settings.
	 *
	 * @param array|null $settings
	 * @return array|WP_Error
	 */
	public static function test_imap_connection( array $settings = null ) {
		if ( ! function_exists( 'imap_open' ) ) {
			return new WP_Error( 'gt_no_imap_extension', __( 'Η επέκταση PHP IMAP δεν είναι ενεργοποιημένη στο διακομιστή σας.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		if ( null === $settings ) {
			$settings = self::get_imap_settings();
		}

		if ( empty( $settings['host'] ) || empty( $settings['username'] ) || empty( $settings['password'] ) ) {
			return new WP_Error( 'gt_missing_credentials', __( 'Παρακαλούμε συμπληρώστε όλα τα απαιτούμενα στοιχεία IMAP (Διακομιστής, Όνομα χρήστη / Email, Κωδικός πρόσβασης).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		$mailbox = self::get_imap_mailbox_string( $settings );

		imap_timeout( IMAP_OPENTIMEOUT, 10 );

		$inbox = @imap_open( $mailbox, $settings['username'], $settings['password'], OP_READONLY, 1 );

		if ( false === $inbox ) {
			$errors     = imap_errors();
			$last_error = imap_last_error();
			$msg        = $last_error ? $last_error : ( ! empty( $errors ) ? implode( ', ', $errors ) : __( 'Αποτυχία σύνδεσης στο διακομιστή IMAP.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
			/* translators: %s: error message */
			return new WP_Error( 'gt_imap_connect_failed', sprintf( __( 'Σφάλμα σύνδεσης IMAP: %s', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $msg ) );
		}

		$check     = @imap_check( $inbox );
		$msg_count = $check ? $check->Nmsgs : 0;

		@imap_close( $inbox );

		return array(
			'success'  => true,
			'messages' => $msg_count,
			/* translators: 1: folder name, 2: message count */
			'message'  => sprintf( __( 'Επιτυχής σύνδεση στο γραμματοκιβώτιο (%1$s)! Βρέθηκαν συνολικά %2$d μηνύματα.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), esc_html( $settings['folder'] ), (int) $msg_count ),
		);
	}

	/**
	 * Recursively extracts valid COD attachments from an IMAP email structure.
	 *
	 * @param resource $inbox IMAP stream.
	 * @param int      $msg_num Message sequence number.
	 * @param object   $structure Structure object from imap_fetchstructure.
	 * @param string   $part_prefix Prefix for nested parts.
	 * @return array List of attachments with 'filename' and 'content'.
	 */
	public static function extract_imap_attachments( $inbox, int $msg_num, $structure, string $part_prefix = '' ): array {
		$attachments = array();

		if ( empty( $structure->parts ) ) {
			return $attachments;
		}

		foreach ( $structure->parts as $idx => $part ) {
			$part_number = empty( $part_prefix ) ? (string) ( $idx + 1 ) : $part_prefix . '.' . ( $idx + 1 );

			if ( ! empty( $part->parts ) ) {
				$sub = self::extract_imap_attachments( $inbox, $msg_num, $part, $part_number );
				$attachments = array_merge( $attachments, $sub );
				continue;
			}

			$filename = '';
			if ( ! empty( $part->dparameters ) ) {
				foreach ( $part->dparameters as $param ) {
					if ( strtolower( $param->attribute ) === 'filename' ) {
						$filename = $param->value;
						break;
					}
				}
			}
			if ( empty( $filename ) && ! empty( $part->parameters ) ) {
				foreach ( $part->parameters as $param ) {
					if ( strtolower( $param->attribute ) === 'name' ) {
						$filename = $param->value;
						break;
					}
				}
			}

			if ( empty( $filename ) ) {
				continue;
			}

			if ( function_exists( 'iconv_mime_decode' ) ) {
				$decoded_name = @iconv_mime_decode( $filename, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8' );
				if ( ! empty( $decoded_name ) ) {
					$filename = $decoded_name;
				}
			} elseif ( function_exists( 'imap_utf8' ) ) {
				$filename = imap_utf8( $filename );
			}

			$clean_name = sanitize_file_name( $filename );
			$lower_name = mb_strtolower( $filename, 'UTF-8' );

			// Strictly skip invoices (ΤΠΥ)
			if ( strpos( $lower_name, 'τπυ' ) !== false || strpos( $lower_name, 'tpy' ) !== false || strpos( $lower_name, 'timolog' ) !== false ) {
				continue;
			}

			if ( ! preg_match( '/\.(csv|txt|tsv)$/i', $clean_name ) ) {
				continue;
			}

			$data = @imap_fetchbody( $inbox, $msg_num, $part_number );

			if ( 3 === $part->encoding ) {
				$data = base64_decode( $data );
			} elseif ( 4 === $part->encoding ) {
				$data = quoted_printable_decode( $data );
			}

			$attachments[] = array(
				'filename' => $clean_name,
				'content'  => $data,
			);
		}

		return $attachments;
	}

	/**
	 * Connects to IMAP mailbox, fetches unread COD settlement emails, and processes them.
	 *
	 * @param array|null $settings
	 * @return array|WP_Error
	 */
	public static function fetch_and_process_imap_emails( array $settings = null ) {
		if ( ! function_exists( 'imap_open' ) ) {
			return new WP_Error( 'gt_no_imap_extension', __( 'Η επέκταση PHP IMAP δεν είναι ενεργοποιημένη.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		if ( null === $settings ) {
			$settings = self::get_imap_settings();
		}

		if ( empty( $settings['host'] ) || empty( $settings['username'] ) || empty( $settings['password'] ) ) {
			return new WP_Error( 'gt_missing_credentials', __( 'Ελλιπή στοιχεία διακομιστή IMAP.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
		}

		$mailbox = self::get_imap_mailbox_string( $settings );
		imap_timeout( IMAP_OPENTIMEOUT, 15 );
		imap_timeout( IMAP_READTIMEOUT, 30 );

		$inbox = @imap_open( $mailbox, $settings['username'], $settings['password'] );

		if ( false === $inbox ) {
			$errors     = imap_errors();
			$last_error = imap_last_error();
			$msg        = $last_error ? $last_error : ( ! empty( $errors ) ? implode( ', ', $errors ) : __( 'Αποτυχία σύνδεσης στο διακομιστή IMAP.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) );
			return new WP_Error( 'gt_imap_connect_failed', $msg );
		}

		$sender_filter = ! empty( $settings['sender_filter'] ) ? trim( $settings['sender_filter'] ) : 'cod@taxydromiki.gr';

		// Find unseen messages
		$emails = @imap_search( $inbox, 'UNSEEN' );

		$processed_messages = 0;
		$processed_files    = 0;
		$updated_orders     = 0;
		$total_amount       = 0.0;
		$errors_list        = array();

		if ( ! empty( $emails ) ) {
			foreach ( $emails as $msg_num ) {
				$header = @imap_headerinfo( $inbox, $msg_num );
				if ( ! $header ) {
					continue;
				}

				$from_email = '';
				if ( ! empty( $header->from[0] ) ) {
					$from_email = strtolower( $header->from[0]->mailbox . '@' . $header->from[0]->host );
				}

				// Strictly ignore invoices from apostoli_timologion
				if ( strpos( $from_email, 'apostoli_timologion' ) !== false ) {
					continue;
				}

				// Filter sender
				if ( ! empty( $sender_filter ) && strpos( $from_email, strtolower( $sender_filter ) ) === false ) {
					continue;
				}

				$structure   = @imap_fetchstructure( $inbox, $msg_num );
				$attachments = self::extract_imap_attachments( $inbox, $msg_num, $structure );

				if ( empty( $attachments ) ) {
					continue;
				}

				$msg_file_success = false;

				foreach ( $attachments as $att ) {
					$res = self::process_raw_content( $att['content'], $att['filename'] );
					if ( is_wp_error( $res ) ) {
						$errors_list[] = sprintf( '[%s] %s', $att['filename'], $res->get_error_message() );
					} else {
						$msg_file_success = true;
						$processed_files++;
						$updated_orders += $res['updated_orders'];
						$total_amount   += (float) $res['total_amount'];
					}
				}

				if ( $msg_file_success ) {
					$processed_messages++;
					@imap_setflag_full( $inbox, (string) $msg_num, "\\Seen" );
				}
			}
		}

		@imap_close( $inbox, CL_EXPUNGE );

		$summary = array(
			'timestamp'          => current_time( 'mysql' ),
			'messages_processed' => $processed_messages,
			'files_processed'    => $processed_files,
			'updated_orders'     => $updated_orders,
			'total_amount'       => $total_amount,
			'errors'             => $errors_list,
		);

		update_option( 'gtvfw_cod_imap_last_log', $summary );

		return $summary;
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
			return new WP_Error( 'gt_empty_content', __( 'Δεν ελήφθησαν δεδομένα αρχείου.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), array( 'status' => 400 ) );
		}

		// Reject invoice files (e.g. ΤΠΥ from apostoli_timologion@taxydromiki.gr)
		if ( ! empty( $filename ) ) {
			$clean_name = mb_strtolower( $filename, 'UTF-8' );
			if ( strpos( $clean_name, 'τπυ' ) !== false || strpos( $clean_name, 'tpy' ) !== false || strpos( $clean_name, 'timolog' ) !== false || strpos( $clean_name, 'τιμολογ' ) !== false ) {
				return new WP_Error(
					'gt_invoice_file_rejected',
					/* translators: %s: filename */
					sprintf( __( 'Το αρχείο "%s" είναι τιμολόγιο (ΤΠΥ) και όχι εκκαθάριση αντικαταβολών. Επεξεργάζονται μόνο αρχεία αντικαταβολών από cod@taxydromiki.gr.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), esc_html( $filename ) ),
					array( 'status' => 400 )
				);
			}
		}

		$normalized = self::normalize_content( $raw_bytes );
		$raw_rows   = self::parse_csv_content( $normalized );

		if ( is_wp_error( $raw_rows ) ) {
			return $raw_rows;
		}

		if ( empty( $raw_rows ) ) {
			return new WP_Error( 'gt_no_rows', __( 'Δεν βρέθηκαν γραμμές αποστολών στο αρχείο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), array( 'status' => 400 ) );
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
			return new WP_Error( 'gt_no_lines', __( 'Δεν βρέθηκαν δεδομένα στο αρχείο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), array( 'status' => 400 ) );
		}

		// Detect and reject invoice content keywords in the first lines
		$first_sample = mb_strtolower( implode( ' ', array_slice( $lines, 0, 5 ) ), 'UTF-8' );
		if ( strpos( $first_sample, 'τιμολογ' ) !== false || strpos( $first_sample, 'παροχησ υπηρεσιων' ) !== false || strpos( $first_sample, 'παροχής υπηρεσιών' ) !== false || strpos( $first_sample, 'τπυ' ) !== false || strpos( $first_sample, 'τ.π.υ' ) !== false ) {
			return new WP_Error(
				'gt_invoice_content_detected',
				__( 'Το περιεχόμενο του αρχείου αναγνωρίστηκε ως τιμολόγιο (ΤΠΥ) και απορρίφθηκε. Επεξεργάζονται μόνο αρχεία εκκαθάρισης αντικαταβολών.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				array( 'status' => 400 )
			);
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

		$voucher_found = false;
		foreach ( $header as $idx => $col_name ) {
			$clean = mb_strtolower( preg_replace( '/[\s\._\-]/u', '', $col_name ), 'UTF-8' );
			if ( strpos( $clean, 'αποδεικτικο' ) !== false || strpos( $clean, 'voucher' ) !== false || strpos( $clean, 'αποστολη' ) !== false ) {
				$col_map['voucher'] = $idx;
				$voucher_found      = true;
			} elseif ( strpos( $clean, 'αναγνωριστικο' ) !== false || strpos( $clean, 'order' ) !== false || strpos( $clean, 'πελατη' ) !== false ) {
				$col_map['client_ref'] = $idx;
			} elseif ( strpos( $clean, 'ημαποστολης' ) !== false ) {
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

		if ( ! $voucher_found ) {
			// Check if first data line has a 10-digit voucher number
			$test_line = isset( $lines[1] ) ? str_getcsv( $lines[1], $delimiter ) : array();
			$test_val  = isset( $test_line[1] ) ? trim( $test_line[1] ) : '';
			if ( ! preg_match( '/^\d{10}$/', $test_val ) ) {
				return new WP_Error(
					'gt_no_voucher_column',
					__( 'Το αρχείο δεν περιέχει έγκυρη στήλη αριθμού voucher αντικαταβολών της Γενικής Ταχυδρομικής.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					array( 'status' => 400 )
				);
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
					/* translators: %s: paid date */
					$rec['status_label']   = sprintf( __( 'Έχει εξοφληθεί (%s)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $paid_date ? substr( $paid_date, 0, 10 ) : '' );
					$rec['is_selectable']  = true;
					$rec['default_checked']= false; // Don't check duplicates by default
				} elseif ( $amount_differs ) {
					$rec['status_code']    = 'amount_mismatch';
					/* translators: %s: formatted order total amount */
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
