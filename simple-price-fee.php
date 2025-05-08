<?php
/**
 * Plugin Name:       Simple Price Fee
 * Plugin URI:        https://github.com/autotech24/simple-price-fee
 * Description:       Adds a fixed fee or discount to WooCommerce cart total based on subtotal ranges, with a customizable single label. Requires WooCommerce.
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
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register plugin settings with sanitization
add_action( 'admin_init', function () {
    register_setting( 'spf_group', 'spf_settings', [
        'sanitize_callback' => 'spf_sanitize_settings'
    ]);
});

// Sanitize settings input
function spf_sanitize_settings( $input ) {
    if ( ! is_array( $input ) ) return [];

    $output = [];
    $output['label'] = sanitize_text_field( $input['label'] ?? '' );

    if ( isset( $input['rules'] ) && is_array( $input['rules'] ) ) {
        $output['rules'] = [];
        foreach ( $input['rules'] as $rule ) {
            $output['rules'][] = [
                'min'    => isset( $rule['min'] ) ? floatval( $rule['min'] ) : 0,
                'max'    => isset( $rule['max'] ) ? floatval( $rule['max'] ) : 0,
                'amount' => isset( $rule['amount'] ) ? floatval( $rule['amount'] ) : 0,
            ];
        }
    }

    return $output;
}

// Add settings page under Settings menu
add_action( 'admin_menu', function () {
    add_options_page(
        'Simple Price Fee Settings',
        'Simple Price Fee',
        'manage_woocommerce',
        'simple_price_fee',
        'spf_admin_page'
    );
});

// Add "Settings" link in Plugins list
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function ( $links ) {
    $url = admin_url( 'options-general.php?page=simple_price_fee' );
    $settings_link = '<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'simple-price-fee' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
});

// Admin settings page output
function spf_admin_page() {
    $settings = get_option( 'spf_settings', array( 'rules' => array(), 'label' => '' ) );
    $rules = $settings['rules'] ?? array();
    $label = $settings['label'] ?? '';
    ?>
    <div class="wrap">
        <h1>Simple Price Fee Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'spf_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Fee Label (shown in cart)</th>
                    <td><input type="text" name="spf_settings[label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <h2>Fee Rules</h2>
            <table id="rules_table" class="widefat fixed">
                <thead>
                    <tr>
                        <th>From (subtotal)</th>
                        <th>To (subtotal)</th>
                        <th>Amount (+ fee, – discount)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rules as $i => $rule ) : ?>
                        <tr>
                            <td><input name="spf_settings[rules][<?php echo esc_attr( $i ); ?>][min]" type="number" step="0.01" value="<?php echo esc_attr( $rule['min'] ); ?>"></td>
                            <td><input name="spf_settings[rules][<?php echo esc_attr( $i ); ?>][max]" type="number" step="0.01" value="<?php echo esc_attr( $rule['max'] ); ?>"></td>
                            <td><input name="spf_settings[rules][<?php echo esc_attr( $i ); ?>][amount]" type="number" step="0.01" value="<?php echo esc_attr( $rule['amount'] ); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" onclick="spf_addRow()">+ Add Rule</button></p>
            <script>
                function spf_addRow() {
                    const tbody = document.getElementById('rules_table').getElementsByTagName('tbody')[0];
                    const index = tbody.rows.length;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input name="spf_settings[rules][${index}][min]" type="number" step="0.01"></td>
                        <td><input name="spf_settings[rules][${index}][max]" type="number" step="0.01"></td>
                        <td><input name="spf_settings[rules][${index}][amount]" type="number" step="0.01"></td>
                    `;
                    tbody.appendChild(row);
                }
            </script>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

// Add fee to WooCommerce cart based on subtotal ranges
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $settings = get_option( 'spf_settings', array() );
    $rules = $settings['rules'] ?? array();
    $label = $settings['label'] ?? 'Price Adjustment';
    $subtotal = $cart->get_subtotal();
    $adjustment = 0;

    foreach ( $rules as $rule ) {
        $min = floatval( $rule['min'] ?? 0 );
        $max = floatval( $rule['max'] ?? 0 );
        $amount = floatval( $rule['amount'] ?? 0 );

        if ( $subtotal >= $min && $subtotal <= $max ) {
            $adjustment += $amount;
        }
    }

    if ( $adjustment != 0 ) {
        $cart->add_fee( $label, $adjustment, false );
    }
});

// Clear related transients when settings are updated to avoid caching issues (e.g. with Redis Object Cache)
add_action( 'update_option_spf_settings', function () {
    global $wpdb;

    $transients = $wpdb->get_col("
        SELECT option_name FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_%'
        AND (
            option_name LIKE '%woocommerce_cart%' OR
            option_name LIKE '%wc_cache%' OR
            option_name LIKE '%spf%'
        )
    ");

    foreach ( $transients as $transient ) {
        $key = str_replace( '_transient_', '', $transient );
        delete_transient( $key );
    }

    if ( class_exists( 'WC_Cache_Helper' ) ) {
        WC_Cache_Helper::delete_version( 'cart' );
        WC_Cache_Helper::delete_version( 'shipping' );
        WC_Cache_Helper::delete_version( 'fee' );
    }
});
