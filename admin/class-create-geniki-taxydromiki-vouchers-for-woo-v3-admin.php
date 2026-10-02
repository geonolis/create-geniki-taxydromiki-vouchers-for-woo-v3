<?php
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/geonolis
 * @since      1.0.0
 *
 * @package    	
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin
 * @author     Γεώργιος Παπαμανώλης <geonolis@hotmail.com>
 */
class Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private string $version;

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;


	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/create-geniki-taxydromiki-vouchers-for-woo-v3-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/create-geniki-taxydromiki-vouchers-for-woo-v3-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Add a Settings menu
	 *
	 * @since  1.0.0
	 */
	public function addPluginAdminMenu() {
		add_menu_page(
			$this->plugin_name,
			'Γεν. Ταχυδρομική',
			'administrator',
			'gtvfw_settings',
			array( $this, 'displayPluginAdminSettings' ),
			plugin_dir_url( __FILE__ ) . 'images/gt-icon.png',
			26
		);

		add_submenu_page(
			'gtvfw_settings',
			'Ρυθμίσεις & Αυτοματισμός (Hub)',
			'Ρυθμίσεις & Αυτοματισμός',
			'administrator',
			'gtvfw_settings',
			array( $this, 'displayPluginAdminSettings' )
		);

		add_submenu_page(
			'gtvfw_settings',
			'Αντικαταβολές (COD)',
			'Αντικαταβολές (COD)',
			'administrator',
			'gtvfw_cod_import',
			array( $this, 'displayPluginCodImport' )
		);

		add_submenu_page(
			'gtvfw_settings',
			'Τιμολόγια & Έλεγχος Κόστους (P&L)',
			'Τιμολόγια & Έλεγχος Κόστους',
			'administrator',
			'gtvfw_invoice_import',
			array( $this, 'displayPluginInvoiceImport' )
		);
	}

	public function displayPluginAdminSettings() {
         // set this var to be used in the settings-display view
//		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
		if(isset($_GET['error_message'])){
			add_action('admin_notices', array($this,'pluginNameSettingsMessages'));
			do_action( 'admin_notices', $_GET['error_message'] );
		}
		require_once 'partials/'.$this->plugin_name.'-admin-display.php';
	}

	public function displayPluginCodImport() {
		require_once 'partials/'.$this->plugin_name.'-cod-import.php';
	}

	public function displayPluginInvoiceImport() {
		require_once 'partials/'.$this->plugin_name.'-invoice-import.php';
	}

	public function pluginNameSettingsMessages($error_message){
		switch ($error_message) {
			case '1':
			$message = __( 'There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'my-text-domain' );                 
			$err_code = esc_attr( 'plugin_name_example_setting' );                 
			$setting_field = 'plugin_name_example_setting';                 
			break;
		}
		$type = 'error';
		add_settings_error(
			$setting_field,
			$err_code,
			$message,
			$type
		);
	}

	public function registerAndBuildFields() {
         /**
        * First, we add_settings_section. This is necessary since all future settings must belong to one.
        * Second, add_settings_field
        * Third, register_setting
        */     
    // Add the section to reading settings so we can add our
 	// fields to it
    // add_settings_section(a string $id, string $title, callable $callback, string $page, array $args = array() )
         add_settings_section(
         	'gtvfw_settings_section',
         	'GT connection settings section',
         	array( $this, 'gtvfw_settings_section_callback_function'),
         	'gtvfw_settings'
         );

 	// 1.option: Geniki Taxydromiki Web Services User Name

 	// ορίζω το array που θα περάσει ως παράμετρος στην callback, που θα σχηματίσει το input:
         unset($args);
         $args = array (
         	'type'      => 'input',
         	'subtype'   => 'text',
         	'id'    => 'gt_username',
         	'name'      => 'gtvfw_settings',
         	'required' => 'true',
         	'get_options_list' => '',
         	'value_type'=>'normal',
         	'wp_data' => 'option',
         	'default'=> 'name'
         );
 	// Add the field with the names and function to use for our new
 	// settings, put it in our new section
 	// add_settings_field( string $id, string $title, callable $callback, string $page, string $section = ‘default’, array $args = array() )
         add_settings_field(
         	'gt_username',
         	'ΓΤ UserName',
         	array( $this, 'gtvfw_setting_callback_render_function'),
         	'gtvfw_settings',
         	'gtvfw_settings_section',
         	$args
         ); 

// 2.option: Geniki Taxydromiki Web Services Password
         unset($args);
         $args = array (
         	'type'      => 'input',
         	'subtype'   => 'text',
         	'id'    => 'gt_password',
         	'name'      => 'gtvfw_settings',
         	'required' => 'true',
         	'get_options_list' => '',
         	'value_type'=>'normal',
         	'wp_data' => 'option',
         	'default'=> 'Password'
         );

         add_settings_field(
         	'gt_password',
         	'ΓΤ Password',
         	array( $this, 'gtvfw_setting_callback_render_function'),
         	'gtvfw_settings',
         	'gtvfw_settings_section',
         	$args
         );

 // 3.option: Geniki Taxydromiki Web Services App Key
         unset($args);
         $args = array (
         	'type'      => 'input',
         	'subtype'   => 'text',
         	'id'    => 'gt_appkey',
         	'name'      => 'gtvfw_settings',
         	'required' => 'true',
         	'get_options_list' => '',
         	'value_type'=>'normal',
         	'wp_data' => 'option',
         	'default'=> 'key'
         );

         add_settings_field(
         	'gt_appkey',
         	'ΓΤ App Key',
         	array( $this, 'gtvfw_setting_callback_render_function'),
         	'gtvfw_settings',
         	'gtvfw_settings_section',
         	$args
         );

// 4.option: Test or Production CheckBox 
         unset($args);
         $args = array (
         	'type'      => 'input',
         	'subtype'   => 'checkbox',
         	'id'    => 'gt_testmode',
         	'name'      => 'gtvfw_settings',
         	'required' => 'true',
         	'get_options_list' => '',
         	'value_type'=>'normal',
         	'wp_data' => 'option',
         	'default'=> '0'
         );

         add_settings_field(
         	'gt_testmode',
         	'ΓΤ Test Mode',
         	array( $this, 'gtvfw_setting_callback_render_function'),
         	'gtvfw_settings',
         	'gtvfw_settings_section',
         	$args
         );

// 5.option: Shipping options Multi Select List
         unset($args);
//         global $woocommerce;
//       $woocommerce->shipping->load_shipping_methods();
         $shipping_methods=array();
         foreach (WC()->shipping->get_shipping_methods() as $method){
         	$shipping_methods[]=$method->id;
//         		echo $method->id; $method->title
         }

         
         $args = array (
         	'type'      => 'input',
         	'subtype'   => 'multiselect',
         	'id'    => 'gt_methods',
         	'name'      => 'gtvfw_settings',
         	'required' => 'true',
         	'get_options_list' => $shipping_methods,
         	'value_type'=>'normal',
         	'wp_data' => 'option',
         	'default'=> array()
         );

		add_settings_field(
			'gt_methods',
			'ΓΤ Μέθοδοι Αποστολής',
			array( $this, 'gtvfw_setting_callback_render_function'),
			'gtvfw_settings',
			'gtvfw_settings_section',
			$args
		);

 	// Register our setting so that $_POST handling is done for us and
 	// our callback function just has to echo the <input>
 	// register_setting( string $option_group, string $option_name, array $args = array() )
		register_setting( 'gtvfw_settings', 'gtvfw_settings', 
						 array(
        'sanitize_callback' => function ($input) {
            // Ensure "gt_testmode" is always set
            if (!isset($input['gt_testmode'])) {
                $input['gt_testmode'] = '0';
            }
            return $input;
        }
    )
						);

	}

/**
	Function to validate - needs improvement
     function validateTxt($input) {
	// Check option field contains no HTML tags - if so strip them out
	$input['text_string'] =  wp_filter_nohtml_kses($input['text_string']);	
	return $input; // return validated input
}
*/

	public function gtvfw_settings_section_callback_function() {
	echo '<p>Συμπληρώστε τα στοιχεία που σας έχει δώσει η Γενική Ταχυδρομική για πρόσβαση στα Web Services.</p>';
} 

	public function gtvfw_setting_callback_render_function( $args ) {
 /* EXAMPLE INPUT
								'type'      => 'input',
								'subtype'   => '',
								'id'    => $this->plugin_name.'_example_setting',
								'name'      => $this->plugin_name.'_example_setting',
								'required' => 'required="required"',
								'get_option_list' => "",
									'value_type' = serialized OR normal,
			'wp_data'=>(option or post_meta),
			'post_id' =>
			*/     
		// για να αποθηκευτεί κάθε field ως μέρος array, και όχι σε χωριστή εγγραφή στη βάση, ορίζω το $array_element όπου το name είναι το record name στον πίνακα wp_options της βάσης και id είναι ο δείκτης στο array.
			$array_element=$args['name'].'['.$args['id'].']';
			if($args['wp_data'] == 'option')
			{
//				$wp_data_value =get_option($args['name'])[$args['id']] ;
				$wp_data_value = is_array(get_option($args['name'])) ? get_option($args['name'])[$args['id']] : '';


			} elseif($args['wp_data'] == 'post_meta'){
				$wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
			}

			switch ($args['type']) {

				case 'input':
				$value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
				if($args['subtype'] != 'checkbox' && $args['subtype'] != 'multiselect') {
					$prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
					$prependEnd = (isset($args['prepend_value'])) ? '</div>' : '';
					$step = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
					$min = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
					$max = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';
					if(isset($args['disabled'])){
									// hide the actual input bc if it was just a disabled input the info saved in the database would be wrong - bc it would pass empty values and wipe the actual information
						echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'_disabled" '.$step.' '.$max.' '.$min.' name="'.$array_element.'_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="'.$args['id'].'" '.$step.' '.$max.' '.$min.' name="'.$array_element.'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
					} else {
						echo $prependStart.'<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" '.$step.' '.$max.' '.$min.' name="'.$array_element.'" size="40" value="' . esc_attr($value) . '" />'.$prependEnd;
					}
					/*<input required="required" '.$disabled.' type="number" step="any" id="'.$this->plugin_name.'_cost2" name="'.$this->plugin_name.'_cost2" value="' . esc_attr( $cost ) . '" size="25" /><input type="hidden" id="'.$this->plugin_name.'_cost" step="any" name="'.$this->plugin_name.'_cost" value="' . esc_attr( $cost ) . '" />*/

				} elseif ($args['subtype']=='checkbox') {
					$checked = ($value) ? 'checked' : '';
					echo '<input type="'.$args['subtype'].'" id="'.$args['id'].'" "'.$args['required'].'" name="'.$array_element.'" size="40" value="1" '.$checked.' />';
				} else {  //subtype==multiselect
					$wp_data_value = is_array($wp_data_value) ? $wp_data_value : array();

					echo '<select style="width:280px" id="'. $args['id'] .'" name="'. $array_element .'[]" multiple>';
					foreach ($args['get_options_list'] as $shipping_method) 
					{
						$selected = false;
						if( in_array(  $shipping_method, $wp_data_value )	) 
						{
							$selected = true;			
						} 

						echo "<option value='".$shipping_method."' " . selected( $selected, true, false ) . ">". $shipping_method."</option>";
					}
					echo '</select>';
				}
				break;
				default:
					# code...
				break;
			}
	}

	// Βασική διαδικασία που θα καλείται όταν ΟΛΟΚΛΗΡΩΝΕΤΑΙ η παραγγελία:
	// Έχει προστεθεί hook στην private function define_admin_hooks() στο includes\class-create-geniki-taxydromiki-vouchers-for-woo-v3.php
	public function woocommerce_create_gt_voucher( $order_id, $order )
	{
		if(! $order->get_meta( 'courier_voucher')=='') return;
		// ενεργοποίηση της διασύνδεσης (API) με ΓΤ Web Services
		if ( ! @isset($this->gt_api) ) $this->gt_api = new GT_API();

		// Νέο αντικείμενο που δημιουργεί Voucher
		// χρησιμοποιώντας το Αpplication Interface που ενεργοποιήσαμε
		$gtvfw=new GTVFW($this->gt_api);

//		$order=wc_get_order( $order_id );

		if ( $gtvfw->is_method($order_id)) {
			// Δημιουργία voucher για την παραγγελία
			$gtvfw->gtvfw_create_voucher($order_id, $order);
		}else{ // Αν η μέθοδος αποστολής της παραγγελίας δεν είναι
		// επιλεγμένη για τη ΓΤ τότε μην φτιάξεις voucher
			$order->add_order_note('Order send by other method');		
		}
		return;
	} // function woocommerce_create_gt_voucher()
	

	/**
	 * Add an extra column at orders table, with title: Αποστολή ΓΤ
	 * Function hooked
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function gt_add_new_order_admin_list_column( $columns ) {
 	   $columns['gt_track'] = 'Αποστολή ΓΤ';
    return $columns;
	}

	/**
	 * Add content to new column, with order shipping status
	 * Function hooked
	 *
	 * @param $column
	 * @param $order_or_postid
	 * @throws Exception
	 */
	function gt_add_new_order_admin_list_column_content( $column, $order_or_postid) {
  	 	if ( 'gt_track' === $column ) {
  	 		// check if arg is order object (HPOS) or post id (CPT)
		    $order = ( $order_or_postid instanceof WC_Order )
			    ? $order_or_postid
			    : wc_get_order( $order_or_postid );
	 		echo $this->gt_shipping_status( $order );

			if ( $order && $order->get_meta( 'gt_cod_paid' ) === 'yes' ) {
				$paid_amt = $order->get_meta( 'gt_cod_paid_amount' );
				$amt_str  = $paid_amt ? ' (' . number_format( (float) $paid_amt, 2, ',', '.' ) . '€)' : '';
				echo '<div style="margin-top:4px;"><span style="display:inline-block; padding:2px 6px; font-size:11px; font-weight:600; background:#d4edda; color:#155724; border-radius:3px;" title="' . esc_attr__( 'Η αντικαταβολή έχει εξοφληθεί', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '">✓ Εξόφληση Α/Κ' . esc_html( $amt_str ) . '</span></div>';
			}

			if ( $order && '' !== $order->get_meta( 'gt_courier_shipping_cost' ) ) {
				$courier_cost = (float) $order->get_meta( 'gt_courier_shipping_cost' );
				$client_ship  = (float) $order->get_shipping_total();
				$client_cod   = 0.0;
				foreach ( $order->get_fees() as $fee ) {
					$fee_name = mb_strtolower( $fee->get_name(), 'UTF-8' );
					if ( strpos( $fee_name, 'αντικαταβολ' ) !== false || strpos( $fee_name, 'cod' ) !== false ) {
						$client_cod += (float) $fee->get_total();
					}
				}
				$client_total = $client_ship + $client_cod;
				$diff         = $client_total - $courier_cost;
				$is_profit    = ( $diff >= 0 );
				$diff_sign    = $is_profit ? '+' : '';

				$bg_color     = $is_profit ? '#e8f5e9' : '#ffebee';
				$text_color   = $is_profit ? '#2e7d32' : '#c62828';
				$border_color = $is_profit ? '#a5d6a7' : '#ef9a9a';

				$tooltip = sprintf(
					__( 'Τιμολόγιο ΓΤ: %s€ | Χρέωση Πελάτη: %s€ (Μεταφορικά: %s€ + Α/Κ: %s€) | Διαφορά: %s%s€', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
					number_format( $courier_cost, 2, ',', '.' ),
					number_format( $client_total, 2, ',', '.' ),
					number_format( $client_ship, 2, ',', '.' ),
					number_format( $client_cod, 2, ',', '.' ),
					$diff_sign,
					number_format( $diff, 2, ',', '.' )
				);

				echo '<div style="margin-top:4px;"><span style="display:inline-block; padding:2px 6px; font-size:11px; font-weight:600; background:' . esc_attr( $bg_color ) . '; color:' . esc_attr( $text_color ) . '; border:1px solid ' . esc_attr( $border_color ) . '; border-radius:3px;" title="' . esc_attr( $tooltip ) . '">';
				echo esc_html( sprintf( 'Κόστος ΓΤ: %s€ (%s%s€)', number_format( $courier_cost, 2, ',', '.' ), $diff_sign, number_format( $diff, 2, ',', '.' ) ) );
				echo '</span></div>';
			}
    	}	
	}

	/**
	 * Εμφάνιση shipping status παραγγελίας
	 * @param $order
	 *
	 * @return string
	 * @throws Exception
	 */
	private function gt_shipping_status( $order ): string {
		$courier_voucher=$order->get_meta( 'courier_voucher');
		// αν δεν είναι συμπληρωμένος αριθμός voucher, τότε ABORD
		if ( $courier_voucher == '')	{
			return 'not available';
		}
		if ( ! @isset($this->gt_api) ) $this->gt_api = new GT_API();
		return 	$this->gt_api->get_status($courier_voucher);
	}

	/**
	 * Functions enables searching for voucher number in shop orders
	 * @param $search_fields
	 *
	 * @return mixed
	 */
	function woocommerce_shop_order_search_voucher( $search_fields ) {

		$search_fields[] = 'courier_voucher';

		return $search_fields;
	}

	/**
	 * Add a custom metabox only for shop_order post type (order edit pages)
	 *
	 */
	public function gt_add_meta_boxes() {
		// check if HPOS is enabled
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';
		add_meta_box('track_order_meta_box', 'Track and Trace', array($this,'gt_metabox_content'), $screen );
	}

	public function gt_metabox_content($order_or_postid) {
		// check if arg is order object (HPOS) or post id (CPT)
		$order = ( $order_or_postid instanceof WC_Order )
			? $order_or_postid
			: wc_get_order( $order_or_postid );
		$courier_voucher = $order ? $order->get_meta( 'courier_voucher' ) : '';
		if ( empty( $courier_voucher ) ) // Αν δεν έχει καταχωρηθεί αριθμός αποστολής τότε ... ΜΗ ΔΙΑΘΕΣΙΜΟ
		{
			echo 'not available';
		} else {
			if ( ! @isset( $this->gt_api ) ) $this->gt_api = new GT_API();
			echo $this->gt_api->get_track( $courier_voucher );
		}

		if ( $order ) {
			$payment_method = $order->get_payment_method();
			$is_cod_paid    = ( $order->get_meta( 'gt_cod_paid' ) === 'yes' );
			if ( $is_cod_paid ) {
				$paid_amt  = $order->get_meta( 'gt_cod_paid_amount' );
				$del_date  = $order->get_meta( 'gt_cod_delivery_date' );
				echo '<div style="margin-top:12px; padding:10px 12px; background:#d4edda; color:#155724; border-left:4px solid #28a745; border-radius:3px; font-size:13px;">';
				echo '<strong>' . esc_html__( 'Πληρωμή Αντικαταβολής (ΓΤ):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '</strong> ';
				echo esc_html__( 'Εξοφλήθηκε', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
				if ( $paid_amt ) {
					echo ' (' . esc_html( number_format( (float) $paid_amt, 2, ',', '.' ) ) . ' €)';
				}
				if ( $del_date ) {
					echo '<br><span style="font-size:12px; color:#2e6b36;">' . esc_html__( 'Ημ. Παράδοσης: ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . esc_html( $del_date ) . '</span>';
				}
				echo '</div>';
			} elseif ( 'cod' === $payment_method ) {
				echo '<div style="margin-top:12px; padding:10px 12px; background:#fff3cd; color:#856404; border-left:4px solid #ffc107; border-radius:3px; font-size:13px;">';
				echo '<strong>' . esc_html__( 'Πληρωμή Αντικαταβολής (ΓΤ):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '</strong> ';
				echo esc_html__( 'Εκκρεμεί εξόφληση από ΓΤ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
				echo '</div>';
			}

			// Display Courier Invoice Shipping Cost & Comparison if present
			$courier_shipping_cost = $order->get_meta( 'gt_courier_shipping_cost' );
			if ( '' !== $courier_shipping_cost ) {
				$courier_cost   = (float) $courier_shipping_cost;
				$base_cost      = (float) $order->get_meta( 'gt_courier_base_cost' );
				$cod_cost       = (float) $order->get_meta( 'gt_courier_cod_fee' );
				$invoice_no     = $order->get_meta( 'gt_courier_invoice_no' );
				$invoice_date   = $order->get_meta( 'gt_courier_invoice_date' );
				$weight         = $order->get_meta( 'gt_courier_weight' );
				$extra_services = (array) $order->get_meta( 'gt_courier_extra_services' );

				$client_ship = (float) $order->get_shipping_total();
				$client_cod  = 0.0;
				foreach ( $order->get_fees() as $fee ) {
					$fee_name = mb_strtolower( $fee->get_name(), 'UTF-8' );
					if ( strpos( $fee_name, 'αντικαταβολ' ) !== false || strpos( $fee_name, 'cod' ) !== false ) {
						$client_cod += (float) $fee->get_total();
					}
				}
				$client_total = $client_ship + $client_cod;
				$diff         = $client_total - $courier_cost;
				$is_profit    = ( $diff >= 0 );
				$diff_sign    = $is_profit ? '+' : '';

				$card_border = $is_profit ? '#28a745' : '#dc3545';
				$diff_bg     = $is_profit ? '#d4edda' : '#f8d7da';
				$diff_fg     = $is_profit ? '#155724' : '#721c24';

				echo '<div style="margin-top:14px; padding:12px; background:#fafafa; border:1px solid #ccd0d4; border-left:4px solid ' . esc_attr( $card_border ) . '; border-radius:3px; font-size:12px; line-height:1.6;">';
				echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; border-bottom:1px solid #eee; padding-bottom:6px;">';
				echo '<strong style="font-size:13px; color:#23282d;">' . esc_html__( 'Οικονομικός Έλεγχος Αποστολής (Τιμολόγιο ΓΤ)', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '</strong>';
				if ( $invoice_no ) {
					echo '<span style="color:#666; font-size:11px;">' . esc_html__( 'Τιμ: ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . esc_html( $invoice_no ) . ( $invoice_date ? ' (' . esc_html( $invoice_date ) . ')' : '' ) . '</span>';
				}
				echo '</div>';

				echo '<table style="width:100%; border-collapse:collapse; margin-bottom:8px;">';
				echo '<tr>';
				echo '<td style="padding:3px 0; color:#555;">' . esc_html__( 'Χρέωση Πελάτη (Καθαρή):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '</td>';
				echo '<td style="padding:3px 0; text-align:right; font-weight:600;">' . esc_html( number_format( $client_total, 2, ',', '.' ) ) . ' €';
				if ( $client_cod > 0 ) {
					echo ' <span style="font-weight:normal; font-size:11px; color:#777;">(Μεταφ: ' . esc_html( number_format( $client_ship, 2, ',', '.' ) ) . '€ + Α/Κ: ' . esc_html( number_format( $client_cod, 2, ',', '.' ) ) . '€)</span>';
				}
				echo '</td>';
				echo '</tr>';

				echo '<tr>';
				echo '<td style="padding:3px 0; color:#555;">' . esc_html__( 'Κόστος Γεν. Ταχυδρομικής (Καθαρό):', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '</td>';
				echo '<td style="padding:3px 0; text-align:right; font-weight:600;">' . esc_html( number_format( $courier_cost, 2, ',', '.' ) ) . ' €';
				echo ' <span style="font-weight:normal; font-size:11px; color:#777;">(Αξία Μεταφ.: ' . esc_html( number_format( $base_cost, 2, ',', '.' ) ) . '€)</span>';
				echo '</td>';
				echo '</tr>';

				if ( ! empty( $extra_services ) ) {
					echo '<tr>';
					echo '<td style="padding:3px 0; color:#555; vertical-align:top;">' . esc_html__( 'Πρόσθετες Υπηρεσίες ΓΤ:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '</td>';
					echo '<td style="padding:3px 0; text-align:right;">';
					foreach ( $extra_services as $code => $amt ) {
						$label     = isset( GT_Invoice_Importer::SERVICE_LABELS[ $code ] ) ? GT_Invoice_Importer::SERVICE_LABELS[ $code ] : $code;
						$amt_float = (float) $amt;
						if ( $amt_float > 0 ) {
							$badge_txt   = $code . ' (+' . number_format( $amt_float, 2, ',', '.' ) . '€)';
							$badge_title = $label . ': +' . number_format( $amt_float, 2, ',', '.' ) . ' € (+ ΦΠΑ)';
							$badge_style = 'background:#d1ecf1; color:#0c5460; font-weight:600;';
						} else {
							$badge_txt   = $code;
							$badge_title = $label . ( 'ΑΜ' === $code || 'AM' === $code ? ' (συμπεριλαμβάνεται στη χρέωση)' : '' );
							$badge_style = 'background:#e2e4e7; color:#333;';
						}
						echo '<span style="display:inline-block; margin-left:4px; padding:2px 6px; font-size:11px; border-radius:3px; ' . esc_attr( $badge_style ) . '" title="' . esc_attr( $badge_title ) . '">';
						echo esc_html( $badge_txt );
						echo '</span>';
					}
					echo '</td>';
					echo '</tr>';
				}

				if ( $weight > 0 ) {
					echo '<tr>';
					echo '<td style="padding:3px 0; color:#777; font-size:11px;">' . esc_html__( 'Βάρος αποστολής τιμολογίου:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) . '</td>';
					echo '<td style="padding:3px 0; text-align:right; font-size:11px; color:#777;">' . esc_html( number_format( (float) $weight, 2, ',', '.' ) ) . ' kg</td>';
					echo '</tr>';
				}

				echo '</table>';

				echo '<div style="padding:6px 10px; background:' . esc_attr( $diff_bg ) . '; color:' . esc_attr( $diff_fg ) . '; border-radius:3px; font-weight:600; text-align:center;">';
				echo esc_html__( 'Διαφορά Μεταφορικών: ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' );
				echo esc_html( $diff_sign . number_format( $diff, 2, ',', '.' ) . ' €' );
				echo ' (' . ( $is_profit ? esc_html__( 'Κέρδος / Κάλυψη', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) : esc_html__( 'Επιπλέον Κόστος Καταστήματος', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ) ) . ')';
				echo '</div>';

				echo '</div>';
			}
		}
	}

	/**
	 * Render COD status filter dropdown on the WooCommerce Orders list.
	 * Supports both HPOS and legacy CPT order lists.
	 *
	 * @param string $order_type Order type (in HPOS).
	 * @param string $which Table position ('top' or 'bottom').
	 */
	public function render_order_list_cod_filter( $order_type = '', $which = 'top' ) {
		// Only render on top bar to avoid duplicate elements
		if ( 'top' !== $which ) {
			return;
		}

		global $typenow;
		$current_type = ! empty( $order_type ) ? $order_type : $typenow;
		if ( 'shop_order' !== $current_type ) {
			return;
		}

		$current_val = isset( $_GET['gt_cod_status'] ) ? sanitize_text_field( wp_unslash( $_GET['gt_cod_status'] ) ) : '';
		?>
		<select name="gt_cod_status" id="dropdown_gt_cod_status">
			<option value=""><?php esc_html_e( 'Όλες οι Αντικαταβολές ΓΤ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
			<option value="paid" <?php selected( 'paid', $current_val ); ?>><?php esc_html_e( 'ΓΤ: Εξοφλημένες Α/Κ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
			<option value="pending" <?php selected( 'pending', $current_val ); ?>><?php esc_html_e( 'ΓΤ: Εκκρεμείς Α/Κ', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Filter HPOS orders by COD status.
	 *
	 * @param array $query_args Query arguments passed to wc_get_orders().
	 * @return array
	 */
	public function filter_hpos_orders_by_cod_status( array $query_args ): array {
		if ( ! isset( $_GET['gt_cod_status'] ) || empty( $_GET['gt_cod_status'] ) ) {
			return $query_args;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['gt_cod_status'] ) );

		if ( 'paid' === $status ) {
			$meta_query   = ! empty( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();
			$meta_query[] = array(
				'key'     => 'gt_cod_paid',
				'value'   => 'yes',
				'compare' => '=',
			);
			$query_args['meta_query'] = $meta_query;
		} elseif ( 'pending' === $status ) {
			$query_args['payment_method'] = 'cod';
			$meta_query   = ! empty( $query_args['meta_query'] ) ? $query_args['meta_query'] : array();
			$meta_query[] = array(
				'key'     => 'courier_voucher',
				'value'   => '',
				'compare' => '!=',
			);
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'gt_cod_paid',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'gt_cod_paid',
					'value'   => 'yes',
					'compare' => '!=',
				),
			);
			$query_args['meta_query'] = $meta_query;
		}

		return $query_args;
	}

	/**
	 * Filter legacy CPT orders by COD status in admin main query.
	 *
	 * @param WP_Query $query The main query instance.
	 */
	public function filter_cpt_orders_by_cod_status( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $typenow;
		if ( 'shop_order' !== $typenow && 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! isset( $_GET['gt_cod_status'] ) || empty( $_GET['gt_cod_status'] ) ) {
			return;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['gt_cod_status'] ) );

		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		if ( 'paid' === $status ) {
			$meta_query[] = array(
				'key'     => 'gt_cod_paid',
				'value'   => 'yes',
				'compare' => '=',
			);
			$query->set( 'meta_query', $meta_query );
		} elseif ( 'pending' === $status ) {
			$meta_query[] = array(
				'key'     => '_payment_method',
				'value'   => 'cod',
				'compare' => '=',
			);
			$meta_query[] = array(
				'key'     => 'courier_voucher',
				'value'   => '',
				'compare' => '!=',
			);
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'gt_cod_paid',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'gt_cod_paid',
					'value'   => 'yes',
					'compare' => '!=',
				),
			);
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Register REST API route for automated COD and Invoice webhooks.
	 */
	public function register_rest_routes() {
		register_rest_route( 'gtvfw/v1', '/cod-webhook', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => array( $this, 'handle_cod_webhook' ),
			'permission_callback' => array( $this, 'check_cod_webhook_permissions' ),
		) );

		register_rest_route( 'gtvfw/v1', '/invoice-webhook', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => array( $this, 'handle_invoice_webhook' ),
			'permission_callback' => array( $this, 'check_cod_webhook_permissions' ),
		) );
	}

	/**
	 * Verify secret token for incoming COD webhook request.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_cod_webhook_permissions( WP_REST_Request $request ) {
		$secret_expected = GT_COD_Importer::get_webhook_secret();

		// Check header X-GT-Secret
		$secret_provided = $request->get_header( 'x-gt-secret' );

		// Fallback: check query parameter or body parameter
		if ( empty( $secret_provided ) ) {
			$secret_provided = $request->get_param( 'secret' );
		}

		// Fallback: check Authorization Bearer
		if ( empty( $secret_provided ) ) {
			$auth = $request->get_header( 'authorization' );
			if ( $auth && 0 === stripos( $auth, 'Bearer ' ) ) {
				$secret_provided = trim( substr( $auth, 7 ) );
			}
		}

		if ( ! empty( $secret_provided ) && hash_equals( $secret_expected, (string) $secret_provided ) ) {
			return true;
		}

		return new WP_Error(
			'gt_unauthorized',
			__( 'Μη εξουσιοδοτημένη πρόσβαση: Μη έγκυρο secret key.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Handle incoming COD or Invoice file payload from Google Apps Script / webhook.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_cod_webhook( WP_REST_Request $request ) {
		$raw_content = '';
		$filename    = 'email_attachment.csv';

		// 1. JSON payload with base64 or text content
		$json = $request->get_json_params();
		if ( ! empty( $json['content'] ) ) {
			if ( ! empty( $json['encoding'] ) && 'base64' === strtolower( $json['encoding'] ) ) {
				$raw_content = base64_decode( $json['content'] );
			} else {
				$raw_content = (string) $json['content'];
			}
			if ( ! empty( $json['filename'] ) ) {
				$filename = sanitize_file_name( $json['filename'] );
			}
		}
		// 2. Multipart file upload ($_FILES['cod_file'] or $_FILES['invoice_file'])
		elseif ( ! empty( $_FILES['cod_file']['tmp_name'] ) ) {
			$raw_content = file_get_contents( $_FILES['cod_file']['tmp_name'] );
			$filename    = sanitize_file_name( $_FILES['cod_file']['name'] );
		} elseif ( ! empty( $_FILES['invoice_file']['tmp_name'] ) ) {
			$raw_content = file_get_contents( $_FILES['invoice_file']['tmp_name'] );
			$filename    = sanitize_file_name( $_FILES['invoice_file']['name'] );
		}
		// 3. Raw body text
		elseif ( ! empty( $request->get_body() ) ) {
			$raw_content = $request->get_body();
			$filename    = $request->get_param( 'filename' ) ? sanitize_file_name( $request->get_param( 'filename' ) ) : 'raw_body.csv';
		}

		if ( empty( $raw_content ) ) {
			return new WP_Error(
				'gt_empty_payload',
				__( 'Δεν στάλθηκαν δεδομένα αρχείου (κενό payload).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				array( 'status' => 400 )
			);
		}

		// Intelligent Routing: Check if this file is an Invoice (ΤΠΥ) rather than COD settlement
		$sender = ! empty( $json['sender'] ) ? sanitize_text_field( $json['sender'] ) : sanitize_text_field( $request->get_param( 'sender' ) );
		$is_invoice = false;

		if ( ! empty( $sender ) && strpos( strtolower( $sender ), 'apostoli_timologion' ) !== false ) {
			$is_invoice = true;
		}

		if ( preg_match( '/^(ΤΠΥ|TPY)/iu', $filename ) || strpos( strtolower( $filename ), 'timolog' ) !== false ) {
			$is_invoice = true;
		}

		$snippet = substr( $raw_content, 0, 500 );
		if ( strpos( $snippet, 'ΣΥΝΟΛ.ΑΞΙΑ' ) !== false || strpos( $snippet, 'ΠΡΟΣΘ.1' ) !== false || strpos( $snippet, 'ΒΑΣΙΚΗ ΧΡΕΩΣΗ' ) !== false || strpos( $snippet, 'ΑΡ.ΤΙΜΟΛ' ) !== false ) {
			$is_invoice = true;
		}

		if ( $is_invoice ) {
			$result = GT_Invoice_Importer::process_raw_content( $raw_content, $filename );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return rest_ensure_response( array(
				'success'          => true,
				'type'             => 'invoice',
				'message'          => sprintf( __( 'Ενημερώθηκαν επιτυχώς %d παραγγελίες από το τιμολόγιο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $result['updated_orders'] ),
				'filename'         => $result['filename'],
				'total_rows'       => $result['total_rows'],
				'updated_orders'   => $result['updated_orders'],
				'already_imported' => $result['already_imported'],
				'not_found'        => $result['not_found'],
				'errors'           => $result['errors'],
				'total_courier'    => $result['total_courier'],
				'total_client'     => $result['total_client'],
				'net_difference'   => $result['net_difference'],
				'timestamp'        => $result['timestamp'],
			) );
		}

		$result = GT_COD_Importer::process_raw_content( $raw_content, $filename );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success'        => true,
			'type'           => 'cod',
			'message'        => sprintf( __( 'Ενημερώθηκαν επιτυχώς %d παραγγελίες.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $result['updated_orders'] ),
			'filename'       => $result['filename'],
			'total_rows'     => $result['total_rows'],
			'updated_orders' => $result['updated_orders'],
			'already_paid'   => $result['already_paid'],
			'not_found'      => $result['not_found'],
			'errors'         => $result['errors'],
			'total_amount'   => $result['total_amount'],
			'timestamp'      => $result['timestamp'],
		) );
	}

	/**
	 * Handle incoming Invoice payload specifically via /invoice-webhook endpoint.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_invoice_webhook( WP_REST_Request $request ) {
		$raw_content = '';
		$filename    = 'invoice_attachment.csv';

		$json = $request->get_json_params();
		if ( ! empty( $json['content'] ) ) {
			if ( ! empty( $json['encoding'] ) && 'base64' === strtolower( $json['encoding'] ) ) {
				$raw_content = base64_decode( $json['content'] );
			} else {
				$raw_content = (string) $json['content'];
			}
			if ( ! empty( $json['filename'] ) ) {
				$filename = sanitize_file_name( $json['filename'] );
			}
		} elseif ( ! empty( $_FILES['invoice_file']['tmp_name'] ) ) {
			$raw_content = file_get_contents( $_FILES['invoice_file']['tmp_name'] );
			$filename    = sanitize_file_name( $_FILES['invoice_file']['name'] );
		} elseif ( ! empty( $request->get_body() ) ) {
			$raw_content = $request->get_body();
			$filename    = $request->get_param( 'filename' ) ? sanitize_file_name( $request->get_param( 'filename' ) ) : 'raw_body.csv';
		}

		if ( empty( $raw_content ) ) {
			return new WP_Error(
				'gt_empty_payload',
				__( 'Δεν στάλθηκαν δεδομένα αρχείου τιμολογίου (κενό payload).', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ),
				array( 'status' => 400 )
			);
		}

		$result = GT_Invoice_Importer::process_raw_content( $raw_content, $filename );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success'          => true,
			'type'             => 'invoice',
			'message'          => sprintf( __( 'Ενημερώθηκαν επιτυχώς %d παραγγελίες από το τιμολόγιο.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ), $result['updated_orders'] ),
			'filename'         => $result['filename'],
			'total_rows'       => $result['total_rows'],
			'updated_orders'   => $result['updated_orders'],
			'already_imported' => $result['already_imported'],
			'not_found'        => $result['not_found'],
			'errors'           => $result['errors'],
			'total_courier'    => $result['total_courier'],
			'total_client'     => $result['total_client'],
			'net_difference'   => $result['net_difference'],
			'timestamp'        => $result['timestamp'],
		) );
	}

	/**
	 * Callback for WP-Cron IMAP check event.
	 */
	public function run_imap_cod_cron() {
		if ( 'imap' === GT_COD_Importer::get_auto_method() ) {
			GT_COD_Importer::fetch_and_process_imap_emails();
		}
	}

} //class
