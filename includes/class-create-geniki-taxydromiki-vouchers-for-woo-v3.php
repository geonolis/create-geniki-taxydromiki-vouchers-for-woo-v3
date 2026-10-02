<?php
// OrderUtil to check if HPOS is enabled:
//use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/geonolis
 * @since      1.0.0
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/includes
 * @author     Γεώργιος Παπαμανώλης <geonolis@hotmail.com>
 */
class Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3 {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'CREATE_GENIKI_TAXYDROMIKI_VOUCHERS_FOR_WOO_V3_VERSION' ) ) {
			$this->version = CREATE_GENIKI_TAXYDROMIKI_VOUCHERS_FOR_WOO_V3_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'create-geniki-taxydromiki-vouchers-for-woo-v3';
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();



	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader. Orchestrates the hooks of the plugin.
	 * - Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_i18n. Defines internationalization functionality.
	 * - Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Admin. Defines all hooks for the admin area.
	 * - Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-create-geniki-taxydromiki-vouchers-for-woo-v3-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-create-geniki-taxydromiki-vouchers-for-woo-v3-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-create-geniki-taxydromiki-vouchers-for-woo-v3-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-create-geniki-taxydromiki-vouchers-for-woo-v3-public.php';

	/**
		 * The class responsible for creating an API that connects
		 * to GT Web Services.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class_gt_api_admin.php';

	/**
		 * The class that creates the voucher. 
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-gtvfw-admin.php';

		/**
		 * The class that handles COD payment file imports.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-gt-cod-importer.php';

		$this->loader = new Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
//		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// my added hooks:
		// Admin Menu Create
		$this->loader->add_action('admin_menu', $plugin_admin,'addPluginAdminMenu');
		// Settings
		$this->loader->add_action('admin_init', $plugin_admin,'registerAndBuildFields');
		// Create voucher when order completed
		$this->loader->add_action('woocommerce_order_status_completed', $plugin_admin, 'woocommerce_create_gt_voucher', 1, 2);

		// Add orders list GT shipping status column title + content
		// HPOS is enabled.
		$this->loader->add_action('manage_woocommerce_page_wc-orders_columns', $plugin_admin, 'gt_add_new_order_admin_list_column');
		$this->loader->add_filter('manage_woocommerce_page_wc-orders_custom_column', $plugin_admin, 'gt_add_new_order_admin_list_column_content',10,2);
		// CPT-based orders are in use.
		$this->loader->add_action('manage_edit-shop_order_columns', $plugin_admin, 'gt_add_new_order_admin_list_column');
		$this->loader->add_filter('manage_shop_order_posts_custom_column', $plugin_admin, 'gt_add_new_order_admin_list_column_content',10,2);

		// Add voucher number to searchable fields
		// HPOS is enabled.
		$this->loader->add_filter( 'woocommerce_order_table_search_query_meta_keys', $plugin_admin, 'woocommerce_shop_order_search_voucher' );
		// CPT-based orders are in use.
		$this->loader->add_filter( 'woocommerce_shop_order_search_fields', $plugin_admin, 'woocommerce_shop_order_search_voucher' );

		// Add a custom metabox only for shop_order post type (order edit pages)
		$this->loader->add_action( 'add_meta_boxes', $plugin_admin,'gt_add_meta_boxes' );

		// Add COD filter dropdown to orders list
		// HPOS
		$this->loader->add_action( 'woocommerce_order_list_table_restrict_manage_orders', $plugin_admin, 'render_order_list_cod_filter', 20, 2 );
		$this->loader->add_filter( 'woocommerce_order_list_table_prepare_items_query_args', $plugin_admin, 'filter_hpos_orders_by_cod_status', 10, 1 );
		// CPT
		$this->loader->add_action( 'restrict_manage_posts', $plugin_admin, 'render_order_list_cod_filter', 20, 2 );
		$this->loader->add_action( 'pre_get_posts', $plugin_admin, 'filter_cpt_orders_by_cod_status', 10, 1 );

		// Register REST API route for automated COD email webhook
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'register_rest_routes' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
//		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

//		Add shipping voucher info at client e-mail:
		$this->loader->add_action( 'woocommerce_email_before_order_table',  $plugin_public, 'woocommerce_email_order_tracking',100 );

//		ΕΜΦΆΝΙΣΗ ΑΡΙΘΜΟΥ ΑΠΟΣΤΟΛΗΣ ΓΕΝΙΚΗΣ ΤΑΧΥΔΡΟΜΙΚΗΣ ΣΤΟ ΛΟΓΑΡΙΑΣΜΟ ΠΕΛΑΤΗ
		$this->loader->add_action( 'woocommerce_view_order', $plugin_public, 'woocommerce_view_order_tracking' );

		// show message on specific extra shipping cost
		$this->loader->add_action( 'woocommerce_after_shipping_rate', $plugin_public, 'gt_extra_shipping_rate_description' );
  	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() { $this->loader->run(); }

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
