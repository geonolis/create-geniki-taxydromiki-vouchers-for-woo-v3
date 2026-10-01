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
<h3>Λεπτομέρειες αποστολής:</h3>
<p>Η παραγγελία σας έχει αποσταλλεί με τη ΓΕΝΙΚΗ ΤΑΧΥΔΡΟΜΙΚΗ.</p>
<table>
    <tr>
        <td>Αριθμός αποστολής</td>
        <td> :</td>
        <td> <?php echo $courier_voucher ?> </td>
    </tr>
    <tr>
        <td>Παρακολούθηση αποστολής</td>
        <td> :</td>
        <td><a href="https://www.taxydromiki.com/track/<?php echo $courier_voucher; ?>" target="_blank">
                https://www.taxydromiki.com/track/<?php echo $courier_voucher; ?> </a></td>
    </tr>
</table>
<br>

