<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://github.com/geonolis
 * @since      1.0.0
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. 
It is the actual HTML content that shows up in the settings-->

<div class="wrap">
		        <div id="icon-themes" class="icon32"></div>  
		        <h2>Γενική Ταχυδρομική Voucher Settings</h2>  
		         <!--NEED THE settings_errors below so that the errors/success messages are shown after submission - wasn't working once we started using add_menu_page and stopped using add_options_page so needed this-->
				<?php settings_errors(); ?>  
		        <form method="POST" action="options.php">  
		            <?php 
		                settings_fields( 'gtvfw_settings' );
		                // Outputs nonce, action, and option_page fields for a settings page (name of page, also referred to in Settings API as option group name).'gtvfw_settings_group' should match the group name used in register_setting() 
		                do_settings_sections( 'gtvfw_settings' );
		                //outputs all the sections and fields that were added to that $page with add_settings_section() and add_settings_field() 
		            ?>             
		            <?php submit_button(); ?>  
		        </form> 
</div>
