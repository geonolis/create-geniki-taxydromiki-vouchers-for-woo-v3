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
			'Ρυθμίσεις',
			'Ρυθμίσεις',
			'administrator',
			'gtvfw_settings',
			array( $this, 'displayPluginAdminSettings' )
		);

		add_submenu_page(
			'gtvfw_settings',
			'Εισαγωγή Αντικαταβολών',
			'Εισαγωγή Αντικαταβολών',
			'administrator',
			'gtvfw_cod_import',
			array( $this, 'displayPluginCodImport' )
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

} //class
