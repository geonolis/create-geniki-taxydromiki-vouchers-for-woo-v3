# Geniki Taxydromiki Woo Vouchers v3

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-HPOS%20Compatible-purple.svg)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B%20%7C%208.x-8892BF.svg)](https://php.net)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**Geniki Taxydromiki Woo Vouchers v3** is a WordPress & WooCommerce plugin that bridges your online store with **Geniki Taxydromiki** Web Services. It streamlines voucher creation, live package tracking, Cash on Delivery (COD) settlement reconciliation, and order management.

---

## Features

### 1. Automatic & Manual Voucher Creation
* Automatically generates Geniki Taxydromiki vouchers when an order reaches the **Completed** status (or via custom workflow).
* Restricts voucher creation to designated shipping methods configured in plugin settings.
* Validates sender and recipient address formats for courier compliance.

### 2. Live Shipment Tracking
* **Orders Admin List**: Custom column displaying live shipment status alongside tracking numbers.
* **Order Edit Screen**: Dedicated metabox with real-time tracking route history directly from Geniki Taxydromiki Web Services.
* **Customer Notifications**: Embeds courier tracking numbers and direct tracking links in customer completion emails and the "My Account > View Order" screen.
* **Admin Search**: Search orders directly by voucher number (`courier_voucher`) from the WooCommerce order search bar.

### 3. COD (Cash On Delivery) Payment Reconciliation
* **Settlement File Importer**: Upload and process Geniki Taxydromiki COD settlement export files (CSV / TSV).
* **Smart Encoding Support**: Built-in normalization for **UTF-16LE with BOM** (standard Geniki email export), UTF-16BE, Windows-1253 (Greek), and UTF-8.
* **Automated Order Matching**: Dual-lookup strategy matching by courier voucher number (`courier_voucher`) or order ID client reference (`#12345`).
* **Validation & Fraud Guard**:
  * Identifies discrepancies between the order total and the collected amount (> 0.05€ tolerance).
  * Prevents duplicate imports by flagging already paid orders.
* **Audit Trail**: Adds private internal order notes with amount, voucher, and delivery date, and records structured order metadata (`gt_cod_paid`, `gt_cod_paid_amount`, `gt_cod_paid_date`, `gt_cod_delivery_date`).

### 4. WooCommerce Order List Filter
* Filter orders table with a single click:
  * **Όλες οι Αντικαταβολές ΓΤ** (All orders)
  * **ΓΤ: Εξοφλημένες Α/Κ** (COD Paid orders)
  * **ΓΤ: Εκκρεμείς Α/Κ** (COD Pending orders with voucher dispatched)
* Fully compatible with both **HPOS** (High-Performance Order Storage / Custom Order Tables) and legacy post storage (`shop_order`).

---

## Requirements

* **WordPress**: 6.0 or higher
* **WooCommerce**: 7.0 or higher (HPOS supported)
* **PHP**: 7.4, 8.0, 8.1, 8.2, or 8.3
* **PHP Extensions**: `curl`, `mbstring`, `simplexml`, `json`
* Active Geniki Taxydromiki Web Services account (Username, Password, AppKey)

---

## Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/geonolis/create-geniki-taxydromiki-vouchers-for-woo-v3.git
   ```
2. In the WordPress Admin, navigate to **Plugins > Installed Plugins**.
3. Locate **Geniki Taxydromiki Woo Vouchers v.3** and click **Activate**.

---

## Configuration

Navigate to **Γεν. Ταχυδρομική > Ρυθμίσεις**:

1. **ΓΤ UserName**: Enter your Geniki Taxydromiki Web Services username.
2. **ΓΤ Password**: Enter your Web Services password.
3. **ΓΤ App Key**: Enter your Web Services application key.
4. **ΓΤ Test Mode**: Check this box if you are connecting to the test/staging API endpoints.
5. **ΓΤ Μέθοδοι Αποστολής**: Select which WooCommerce shipping methods should trigger voucher creation.
6. Click **Save Changes**.

---

## COD Settlement Import Guide

1. Download the periodic settlement CSV/TSV file received via email from Geniki Taxydromiki (e.g. `TAXYDR...csv`).
2. In the WordPress admin, go to **Γεν. Ταχυδρομική > Εισαγωγή Αντικαταβολών**.
3. Upload the file and click **Ανάλυση & Προεπισκόπηση Αρχείου**.
4. Review the preview table:
   * **Ready (Green)**: Matched orders ready to be confirmed.
   * **Amount Mismatch (Yellow)**: Amount collected differs from the order total.
   * **Already Paid (Yellow)**: Previously imported orders (unchecked by default).
   * **Not Found (Red)**: Orders not found in your database.
5. Click **Επιβεβαίωση & Ενημέρωση Επιλεγμένων Παραγγελιών** to apply the updates.

---

## Architecture & Compatibility

* **HPOS Ready**: Uses WooCommerce CRUD APIs (`$order->get_meta()`, `$order->update_meta_data()`, `$order->save()`) and declares compatibility with `CustomOrdersTableController`.
* **Hooks & Filters**:
  * Dropdown UI: `woocommerce_order_list_table_restrict_manage_orders` & `restrict_manage_posts`
  * Query Filtering: `woocommerce_order_list_table_prepare_items_query_args` & `pre_get_posts`
  * Voucher Search: `woocommerce_order_table_search_query_meta_keys` & `woocommerce_shop_order_search_fields`
  * Order Columns: `manage_woocommerce_page_wc-orders_columns` & `manage_edit-shop_order_columns`

---

## Changelog

### 1.0.0
* Initial release with Geniki Taxydromiki Web Services integration.
* Automated voucher creation on order completion.
* Live tracking in admin list, order view, and customer emails.
* COD settlement file importer supporting UTF-16LE, UTF-8, and Windows-1253.
* COD Paid / COD Pending dropdown filter in WooCommerce Orders table.
* Full WooCommerce HPOS (High-Performance Order Storage) support.

---

## License

This project is licensed under the GNU General Public License v2.0 or later - see the [LICENSE](LICENSE) file for details.

## Author

**Γεώργιος Παπαμανώλης**  
GitHub: [@geonolis](https://github.com/geonolis)  
Email: geonolis@hotmail.com
