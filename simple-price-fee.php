<?php
/**
 * Plugin Name:       Simple Price Fee
 * Plugin URI:        https://github.com/autotech24/simple-price-fee
 * Description:       Adds a fixed fee or discount to WooCommerce cart total based on subtotal ranges, with a customizable single label.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Requires Plugins:  WooCommerce
 * Author:            Autotech24
 * Author URI:        https://www.autotech24.eu
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-price-fee
 * Domain Path:       /languages
 *
 * @package SimplePriceFee
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Add admin menu page
add_action( 'admin_menu', function () {
    add_menu_page(
        'Price Fee Settings',
        'Price Fee',
        'manage_woocommerce',
        'simple_price_fee',
        'spf_admin_page'
    );
} );

// Register plugin settings
add_action( 'admin_init', function () {
    register_setting( 'spf_group', 'spf_settings' );
} );

// Admin page HTML
function spf_admin_page() {
    $settings = get_option( 'spf_settings', array( 'rules' => array(), 'label' => '' ) );
    $rules    = $settings['rules'] ?? array();
    $label    = $settings['label'] ?? '';
    ?>
    <div class="wrap">
        <h1>Price Fee Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'spf_group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Fee Label (Displayed in Cart/Checkout)</th>
                    <td><input type="text" name="spf_settings[label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <h2>Fee Rules</h2>
            <table id="rules_table" class="widefat fixed">
                <thead>
                    <tr>
                        <th>From (subtotal)</th>
                        <th>To (subtotal)</th>
                        <th>Amount (positive = fee, negative = discount)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rules as $index => $rule ) : ?>
                        <tr>
                            <td><input name="spf_settings[rules][<?php echo $index; ?>][min]" type="number" step="0.01" value="<?php echo esc_attr( $rule['min'] ); ?>"></td>
                            <td><input name="spf_settings[rules][<?php echo $index; ?>][max]" type="number" step="0.01" value="<?php echo esc_attr( $rule['max'] ); ?>"></td>
                            <td><input name="spf_settings[rules][<?php echo $index; ?>][amount]" type="number" step="0.01" value="<?php echo esc_attr( $rule['amount'] ); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" onclick="spf_addRow()">+ Add Rule</button></p>
            <script>
                function spf_addRow() {
                    const table = document.getElementById('rules_table').getElementsByTagName('tbody')[0];
                    const index = table.rows.length;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input name="spf_settings[rules][${index}][min]" type="number" step="0.01"></td>
                        <td><input name="spf_settings[rules][${index}][max]" type="number" step="0.01"></td>
                        <td><input name="spf_settings[rules][${index}][amount]" type="number" step="0.01"></td>
                    `;
                    table.appendChild(row);
                }
            </script>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

// Apply fee based on subtotal
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $settings         = get_option( 'spf_settings', array() );
    $rules            = $settings['rules'] ?? array();
    $label            = $settings['label'] ?? 'Price Adjustment';
    $total_adjustment = 0;
    $cart_total       = $cart->get_subtotal();

    foreach ( $rules as $rule ) {
        $min    = isset( $rule['min'] ) ? floatval( $rule['min'] ) : 0;
        $max    = isset( $rule['max'] ) ? floatval( $rule['max'] ) : 0;
        $amount = isset( $rule['amount'] ) ? floatval( $rule['amount'] ) : 0;

        if ( $cart_total >= $min && $cart_total <= $max ) {
            $total_adjustment += $amount;
        }
    }

    if ( $total_adjustment != 0 ) {
        $cart->add_fee( $label, $total_adjustment, false );
    }
} );
