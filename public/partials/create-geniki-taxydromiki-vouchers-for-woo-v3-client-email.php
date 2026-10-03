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
<!-- This html is added to the client order e-mail
if GT voucher number exists at order meta  -->
<h3><?php esc_html_e( 'Λεπτομέρειες αποστολής:', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></h3>
<p><?php esc_html_e( 'Η παραγγελία σας έχει αποσταλλεί με τη ΓΕΝΙΚΗ ΤΑΧΥΔΡΟΜΙΚΗ.', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></p>
<table>
    <tr>
        <td><?php esc_html_e( 'Αριθμός αποστολής', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
        <td> :</td>
        <td> <?php echo esc_html( $courier_voucher ); ?> </td>
    </tr>
    <tr>
        <td><?php esc_html_e( 'Παρακολούθηση αποστολής', 'create-geniki-taxydromiki-vouchers-for-woo-v3' ); ?></td>
        <td> :</td>
        <td><a href="<?php echo esc_url( 'https://www.taxydromiki.com/track/' . $courier_voucher ); ?>" target="_blank">
                <?php echo esc_html( 'https://www.taxydromiki.com/track/' . $courier_voucher ); ?> </a></td>
    </tr>
</table>
<br>

