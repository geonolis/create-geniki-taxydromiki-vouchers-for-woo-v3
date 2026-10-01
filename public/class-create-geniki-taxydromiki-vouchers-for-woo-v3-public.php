<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/geonolis
 * @since      1.0.0
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/public
 * @author     Γεώργιος Παπαμανώλης <geonolis@hotmail.com>
 */
class Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;


	/**
	 * The GT connection API object
	 * It is referenced as class private property, to be created once and accessed from all class methods
	 * @since    1.0.0
	 * @access   private
	 * @var      GT_API    $gt_api    The API connection object.
	 */
	private GT_API $gt_api;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		$url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		if ( strpos( $url, 'view-order'))
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/create-geniki-taxydromiki-vouchers-for-woo-v3-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/create-geniki-taxydromiki-vouchers-for-woo-v3-public.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * ΕΜΦΆΝΙΣΗ ΑΡΙΘΜΟΥ ΑΠΟΣΤΟΛΗΣ ΓΕΝΙΚΗΣ ΤΑΧΥΔΡΟΜΙΚΗΣ ΣΤΟ ΕΜΑΙΛ ΠΕΛΑΤΗ
	 * @param $order
	 */
	function woocommerce_email_order_tracking( $order ) {
		/* ανάκτηση αριθμού αποστολής από custom field */
		$courier_voucher = $order->get_meta( 'courier_voucher') ;
		/*  αν βρέθηκε αριθμός αποστολής εμφάνισε λινκ παρακολούθησης */
		if ( ! empty( $courier_voucher) ) {
			require_once 'partials/' . $this->plugin_name . '-client-email.php';
		}
	}

	/**
	 * ΕΜΦΆΝΙΣΗ ΑΡΙΘΜΟΥ ΑΠΟΣΤΟΛΗΣ ΓΕΝΙΚΗΣ ΤΑΧΥΔΡΟΜΙΚΗΣ ΣΤΟ ΛΟΓΑΡΙΑΣΜΟ ΠΕΛΑΤΗ
	 * @param $order_id
	 */
	function woocommerce_view_order_tracking( $order_id ) {
		/* ανάκτηση αριθμού αποστολής από custom field */
		$courier_voucher = wc_get_order($order_id)->get_meta( 'courier_voucher') ;
		/*  αν δεν έχει συμπληρωθεί αριθμός αποστολής επέστρεψε */
		if ( ! empty( $courier_voucher) ) {
			if ( ! @isset( $this->gt_api ) ) $this->gt_api = new GT_API();
			require_once 'partials/' . $this->plugin_name . '-client-account.php';
		}
	}

	function gt_extra_shipping_rate_description( $method ) {
   		if ( $method->id === 'tree_table_rate:14:ab29938a' ) {
  	    echo '<p>Από 17/6 έως 15/10/2024, η Γενική Ταχυδρομική εφαρμόζει για τις παραδόσεις σε Ζάκυνθο, Κέρκυρα, Λευκάδα, Μύκονο, Ρόδο, Σαντορίνη πρόσθετο τέλος €6,20. Για να μην επιβαρυνθείτε, σας προτείνουμε να επιλέξετε "παραλαβή από Γενική Ταχυδρομική" και να μεταβείτε να παραλάβετε από το τοπικό σημείο εξυπηρέτησης της Γενικής Ταχυδρομικής.</p>';
  		 }
	}

}
