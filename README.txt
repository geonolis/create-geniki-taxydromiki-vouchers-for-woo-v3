=== Geniki Taxydromiki Woo Vouchers v.3 ===
Contributors: geonolis
Donate link: https://github.com/geonolis/
Tags: woocommerce, geniki taxydromiki, vouchers, shipping, cod
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connects WooCommerce to Geniki Taxydromiki Web Services for automated voucher creation, tracking, COD payments import, and HPOS order filtering.

== Description ==

**Geniki Taxydromiki Woo Vouchers v3** integrates your WooCommerce store with **Geniki Taxydromiki** Web Services.

= Key Features =
* **Voucher Automation**: Automatically create vouchers when orders are marked as completed.
* **Tracking & Route Details**: Display live status and tracking route in WooCommerce Orders admin table, order edit screen metabox, and customer emails.
* **COD Settlement Import**: Upload and process Geniki Taxydromiki COD CSV/TSV settlement files with smart encoding handling (UTF-16LE with BOM, Windows-1253, UTF-8).
* **Order List Filter**: Filter orders by "ΓΤ: Εξοφλημένες Α/Κ" (COD Paid) and "ΓΤ: Εκκρεμείς Α/Κ" (COD Pending).
* **HPOS Compatibility**: Fully compatible with WooCommerce High-Performance Order Storage (HPOS).
* **Voucher Search**: Search orders directly by Geniki Taxydromiki voucher number.

== Installation ==

1. Upload the `create-geniki-taxydromiki-vouchers-for-woo-v3` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Γεν. Ταχυδρομική > Ρυθμίσεις** to enter your Web Services credentials.

== Frequently Asked Questions ==

= Where do I find my Geniki Taxydromiki Web Services credentials? =
Contact your Geniki Taxydromiki account representative to request Web Services API access (Username, Password, AppKey).

= What encoding is supported for COD file imports? =
The plugin handles UTF-16LE with BOM (standard format sent by Geniki Taxydromiki email reports), UTF-16BE, UTF-8, and Greek Windows-1253.

= Is WooCommerce HPOS supported? =
Yes, the plugin is fully compatible with WooCommerce High-Performance Order Storage (Custom Orders Table).

== Screenshots ==

1. Plugin settings page with Web Services credentials and shipping method selector.
2. COD payment settlement import tool with preview and order reconciliation.
3. WooCommerce orders table with Geniki Taxydromiki tracking column and COD filter dropdown.
4. Order edit screen tracking metabox.

== Changelog ==

= 1.0.0 =
* Initial release.
* Geniki Taxydromiki Web Services integration.
* Automated voucher creation.
* Live shipping tracking across admin and customer emails.
* COD settlement CSV/TSV import with encoding normalization.
* WooCommerce order list filter for COD Paid and COD Pending statuses.
* Full HPOS support.

== Upgrade Notice ==

= 1.0.0 =
Initial release with Geniki Taxydromiki voucher creation, tracking, and COD payments import.