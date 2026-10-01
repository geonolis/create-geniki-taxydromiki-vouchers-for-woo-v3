<?php

/**
 * The core functionality of the plugin.
 *
 * Connects to GT Web Services and Creates the Voucher.
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin
 * @author     Γεώργιος Παπαμανώλης <geonolis@hotmail.com>
 */

class GT_API {

	private string $service_url;
	private $is_test;
	private $password;
	private $username;
	private $appkey;
	private $sel_methods=array();

	/**
	 * The constructor reads the plugin settings from database and stores them to
	 * object properties. It is designed to query database only once.
	 *
	 * GT_API constructor.
	 */
	public function __construct() {
		$settings=get_option('gtvfw_settings');
		$this->is_test=$settings['gt_testmode'];
		$this->username=$settings['gt_username'];
		$this->password=$settings['gt_password'];
		$this->appkey=$settings['gt_appkey'];
		$this->sel_methods=$settings['gt_methods'];
		$this->service_url=($this->is_test==1) ? "https://testvoucher.taxydromiki.gr/JobServicesV2.asmx" :  "https://voucher.taxydromiki.gr/JobServicesV2.asmx" ;
	}

	/**
	 * Authenticate method. Returns key to be used in every call to Web Service
	 * If optional param is set to true then asks for new authentication key
	 * If param is omitted it is meant to be false and it returns last key (set as static)
	 *
	 * @param false $gt_renew
	 *
	 * @return int|mixed|string
	 */
	private function gt_auth( bool $gt_renew = False )
	{
		static $gt_key;
		if ( ( ! $gt_renew ) and (! empty( $gt_key ) )){
			return $gt_key; 
		} else {	
			try 
			{ 
				$soap = new SoapClient( $this->service_url.'?WSDL' );
				$oAuthResult = $soap->Authenticate
				(
					array(	'sUsrName' => $this->username, 'sUsrPwd' => $this->password, 'applicationKey' => $this->appkey 	) 	
				);
				if ($oAuthResult->AuthenticateResult->Result != 0) 
					{ return 1 ;} 
				else
					{ $gt_key = $oAuthResult->AuthenticateResult->Key ;
						return $gt_key ;  }
			}
				catch(SoapFault $fault) 
			{ 
				return 'Soap protocol fault' ; //return $gt_key ; //προσωρινη λυση
			}
		}
	}

	/**
	* Ελέγχει αν η ορισμένη μέθοδος αποστολής περιλαμβάνεται 
	* στις μεθόδους αποστολής που έχουν επιλεγεί
	* για αποστολή με Γενική Ταχυδρομική 
	*
	* @since    1.0.0
	*/
	public function get_methods(){
		return $this->sel_methods;
	}

	/**
	 * Creates link to print voucher pdf
	 * @param $voucher number
	 *
	 * @return string with URL
	 */
 	public function get_voucher_url( $voucher ){
		return $this->service_url . "/GetVouchersPdf?authKey=". urlencode( $this->gt_auth() )."&voucherNumbers=".$voucher."&Format=Flyer&extraInfoFormat=None";
 	}

	/**
	 * Get shipping status of the given voucher number
	 * @param $voucher
	 *
	 * @return string with info of the shipping status, date of delivery and link to tracking page
	 * @throws Exception
	 */
	public function get_status( $voucher ): string {
		try {
			$oAuthResult = $this->gt_auth();
			if ( $oAuthResult == 1 ) {
				$result = 'Auth error';
			} else {
				$gt_tracked   = false;    // flag ότι έγινε η πρώτη προπάθεια δημιουργίας αποστολής
				$gt_counter = 0;        // μετρητής προσπαθειών σύνδεσης
				do {
					if ( $gt_tracked ) {
						$oAuthResult = $this->gt_auth( true );
					} // Αν είναι 2η προσπάθεια τότε ζήτα νέο κωδικό authorization
					$soap = new SoapClient( $this->service_url . '?WSDL' );
					$xml  = array(  // array για αναζήτηση αριθμού αποστολής
						'authKey'   => $oAuthResult,
						'voucherNo' => $voucher,
						'language'  => 'el'
					);

					// βασική κλήση αναζήτησης voucher
					$TT         = $soap->TrackAndTrace( $xml ); // αναζήτηση αποστολής
					$gt_tracked = true;    // flag ότι έγινε η πρώτη αναζήτηση
					if ( ++ $gt_counter == 3 ) { // αν προσπάθησε να συνδεθεί 3 φορές χωρίς επιτυχία τότε ABORD
						$result = 'error';
						return $result;
					}
				} while ( $TT->TrackAndTraceResult->Result == 11 );
				if ( $TT->TrackAndTraceResult->Result == 9 ) { // Αν δεν βρέθηκε η αποστολή
					$result = 'not found';
				} else {// διαφορετικά, αν πέτυχε η αναζήτηση αποστολής...
					// ανάκτησε την ημερομηνία
					if (isset( $TT->TrackAndTraceResult->Status)) {
					$dt     = new DateTime( $TT->TrackAndTraceResult->DeliveryDate );
					$result = '<a href="https://www.taxydromiki.com/track/' . $voucher . '" target="_blank"><p style=LINE-HEIGHT:18px><strong>' . $TT->TrackAndTraceResult->Status . '</strong>';
					
					if ( $TT->TrackAndTraceResult->Status == 'ΠΑΡΑΔΟΜΕΝΟ' ) {
						$result .= ' την ' . $dt->format( 'j-n-Y' ) . ' σε ' . $TT->TrackAndTraceResult->Consignee . '</p></a>';
					} else {
						$result .= '</p></a>';
					}
						} else { $result='just printed!';}
				}
			}
		} catch ( SoapFault $fault) {
			$result = 'Soap error';
		}
		return $result;
	}


	/**
	 * Βασική διαδικασία που θα καλείται όταν ΟΛΟΚΛΗΡΩΝΕΤΑΙ η παραγγελία
	 * Επιστρέφει τον αριθμό του voucher ή false αν απέτυχε
	 *
	 * @param $oVoucher array με τα στοιχεία που χρειάζεται η GT
	 *
	 * @return integer || false
	 */
	public function get_gt_voucher( $oVoucher ) {
			/** oVoucher array:
			 * (    'OrderId' => '#' . $order_id,
			 * 'Name' => $name,
			 * 'Address' => $address,
			 * 'City' => $city,
			 * 'Telephone' => $phone,
			 * 'Zip' => $zip,
			 * 'Destination' => "",
			 * 'Courier' => "",
			 * 'Pieces' => $pieces,
			 * 'Weight' => $weight,
			 * 'Comments' => $message,
			 * 'Services' => $services ,
			 * 'CodAmount' => $CodAmount,
			 * 'InsAmount' => 0,
			 * 'VoucherNumber' => "",
			 * 'SubCode' => "",
			 * 'BelongsTo' => "",
			 * 'DeliverTo' => "",
			 * 'ReceivedDate' => $ReceivedDate    )
			 */
			try {
				$oAuthResult = $this->gt_auth();
				if ( $oAuthResult == 1 ) {
					$voucher = False;
				} else {
					$gt_tried   = False;    // flag ότι έγινε η πρώτη προπάθεια δημιουργίας αποστολής
					$gt_counter = 0;        // μετρητής προσπαθειών σύνδεσης
					do {
						if ( $gt_tried )
							$oAuthResult = $this->gt_auth( True ); // Αν είναι 2η προσπάθεια τότε ζήτα νέο κωδικό authorization
						$soap     = new SoapClient( $this->service_url . '?WSDL' );
						$xml      = array( 'sAuthKey' => $oAuthResult, 'oVoucher' => $oVoucher, 'eType' => "Voucher" );
						$oResult  = $soap->CreateJob( $xml );  // βασική κλήση δημιουργίας αποστολής
						$gt_tried = True;    // flag ότι έγινε η πρώτη προσπάθεια
						if ( ++ $gt_counter == 3 ) { // αν προσπάθησε να συνδεθεί 3 φορές χωρίς επιτυχία τότε ABORD
							$voucher = False;

							return $voucher;
						}
					} while ( $oResult->CreateJobResult->Result == 11 );
					if ( $oResult->CreateJobResult->Result != 0 ) {//αν δεν πέτυχε η δημιουργία αποστολής...

						$voucher = False;
					} else {// διαφορετικά, αν πέτυχε η δημιουργία αποστολής...
						// αποθήκευσε τον αριθμό αποστολής σε meta-data
						$voucher = $oResult->CreateJobResult->Voucher;
					}

					if ( $this->is_test == 1 ) {
						// Η ΓΤ ζητάει να γίνει δοκιμαστική κλήση της CancelJob() πριν δώσει πλήρη πρόσβαση στο Web Service
						$CJResult = $soap->CancelJob( array(
							'sAuthKey' => $oAuthResult,
							'nJobId'   => $oResult->CreateJobResult->JobId,
							'bCancel'  => True
						) );
						$CJResult = $soap->CancelJob( array(
							'sAuthKey' => $oAuthResult,
							'nJobId'   => $oResult->CreateJobResult->JobId,
							'bCancel'  => False
						) );
					}

					// κλείσιμο αποδεικτικού:
					$soap->ClosePendingJobs( array( 'sAuthKey' => $oAuthResult ) );
				}
			} catch ( SoapFault $fault ) {
				$voucher = False;
			}
			return $voucher;
		} // τέλος function woocommerce_create_gt_voucher( $order_id )

	/**
	 * Εμφάνιση πορείας αποστολής (tracking)
	 * @param $voucher
	 *
	 * @throws Exception
	 */
	function get_track( $voucher ) {
		try {
			$gt_tracked  = false;                // flag αν έχει κληθεί η αναζήτηση αποστολής
			$oAuthResult = $this->gt_auth();        // κλήση της συνάρτησης για authorization και επιστροφή κλειδιού
			if ( $oAuthResult == 1 )            // Αν η authorization επέστρεψε -1-  τότε απέτυχε - ABORD
			{
				return 'authentication failure 1';
			}
			$gt_counter = 0; // μετρητής προσπαθειών σύνδεσης
			do {
				if ( $gt_tracked )
					$oAuthResult = $this->gt_auth( true );
				$soap       = new SoapClient( $this->service_url . '?WSDL' );
				$xml        = array
				(
					'authKey'   => $oAuthResult,
					'voucherNo' => $voucher,
					'language'  => 'el'
				);
				$TT         = $soap->TrackAndTrace( $xml );
				$gt_tracked = true;
				if ( ++ $gt_counter == 3 ) { // αν προσπάθησε να συνδεθεί 3 φορές χωρίς επιτυχία τότε ABORD
					return 'authentication failure 3';
				}
			} while ( $TT->TrackAndTraceResult->Result == 11 );
			if ( $TT->TrackAndTraceResult->Result == 9 ) {
				$result='not found';
			} else {
				$result= '<div class="l-content" role="main">
					  <div class="result-tracking-message form-wrapper" id="tracking-result" style="display: block">
					  <div class="tracking-result form-wrapper">
					  <div class="tracking-result-content form-wrapper" id="edit-content">';
				foreach ( $TT->TrackAndTraceResult->Checkpoints->Checkpoint as $Checkpoint ) {
					$dt = new DateTime( $Checkpoint->StatusDate );
//				print_r( $Checkpoint);
					$result.= '<div class="tracking-checkpoint form-wrapper">
				<div class="checkpoint-status"><strong>Κατάσταση</strong>' . $Checkpoint->Status . '</div>
				<div class="checkpoint-location"><strong>Τοποθεσία</strong>' . $Checkpoint->Shop . '</div>
				<div class="checkpoint-date"><strong>Ημερομηνία: </strong>' . $dt->format( 'j-n-Y' ) . '</div>
				<div class="checkpoint-time"><strong>Ώρα: </strong>' . $dt->format( 'H:i' ) . '</div></div>';
				}
				$result.= '</div></div></div></div>';
			}
		} catch ( SoapFault $fault ) {
			$result= $fault;
		}
		return $result;
	}


} //class