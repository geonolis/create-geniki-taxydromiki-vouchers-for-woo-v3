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

    <h3><?php esc_html_e( 'Λεπτομέρειες αποστολής:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></h3>
    <p><?php esc_html_e( 'Η παραγγελία σας έχει αποσταλλεί με τη ΓΕΝΙΚΗ ΤΑΧΥΔΡΟΜΙΚΗ.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></p>
    <table>
        <tr>
            <td><?php esc_html_e( 'Αριθμός αποστολής', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td><td> : </td>
            <td><a href="<?php echo esc_url( 'https://www.taxydromiki.com/track/' . $courier_voucher ); ?>" target="_blank"><u><?php echo esc_html( $courier_voucher ); ?></u></a> </td>
        </tr>
    </table>

    <div id="custom_order_meta_box" class="postbox ">
        <div class="inside">
			<?php
			echo wp_kses_post( $this->gt_api->get_track( $courier_voucher ) );
			?>
        </div>
    </div>
<?php



