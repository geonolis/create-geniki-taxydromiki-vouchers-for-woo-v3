<?php
/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://github.com/geonolis
 * @since      1.0.0
 *
 * @package    Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3
 * @subpackage Create_Geniki_Taxydromiki_Vouchers_For_Woo_V3/public/partials
 */
?>
<!-- This html is added to the client account
if GT voucher number exists at order meta  -->

    <h3>Λεπτομέρειες αποστολής:</h3>
    <p>Η παραγγελία σας έχει αποσταλλεί με τη ΓΕΝΙΚΗ ΤΑΧΥΔΡΟΜΙΚΗ.</p>
    <table>
        <tr>
            <td>Αριθμός αποστολής</td><td> : </td>
            <td><a href="https://www.taxydromiki.com/track/<?php echo $courier_voucher ; ?>" target="_blank"><u> <?php echo $courier_voucher ?></u></a> </td>
        </tr>
        <!--		<tr>
			<td>Παρακολούθηση αποστολής</td><td> :</td>
			<td> <a href="https://www.taxydromiki.com/track/<?php echo $courier_voucher ; ?>" target="_blank">    https://www.taxydromiki.com/track/<?php echo $courier_voucher ; ?> </a> </td>
		</tr>
-->
    </table>

    <div id="custom_order_meta_box" class="postbox ">
        <div class="inside">
			<?php
	//		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/create-geniki-taxydromiki-vouchers-for-woo-v3-public.css', array(), $this->version, 'all' );
			echo $this->gt_api->get_track($courier_voucher) ;
			?>
        </div>
    </div>
<?php



