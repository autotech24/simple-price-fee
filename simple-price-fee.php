<?php
/**
 * Plugin Name:       Simple Price Fee
 * Plugin URI:        https://github.com/autotech24/simple-price-fee
 * Description:       Adds a fixed fee or discount to WooCommerce cart total based on subtotal ranges, with a customizable single label. Requires WooCommerce.
 * Version:           1.0.2
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Requires Plugins:  woocommerce
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

define( 'AT24SPF_URL', plugin_dir_url(__FILE__) );
define( 'AT24SPF_PATH', plugin_dir_path(__FILE__) );

// Enqueue assets on settings page
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook === 'settings_page_simple_price_fee' ) {
        wp_enqueue_script( 'at24spf-admin', AT24SPF_URL . 'assets/admin.js', [], '1.0', true );
        wp_enqueue_style( 'at24spf-admin-style', AT24SPF_URL . 'assets/admin.css', [], '1.0' );
    }
});

// Register plugin settings
add_action( 'admin_init', function () {
    register_setting( 'at24spf_group', 'at24spf_settings', [
        'sanitize_callback' => 'at24spf_sanitize_settings'
    ]);
});

// Sanitize
function at24spf_sanitize_settings( $input ) {
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

// Admin menu
add_action( 'admin_menu', function () {
    add_options_page(
        'Simple Price Fee Settings',
        'Simple Price Fee',
        'manage_woocommerce',
        'simple_price_fee',
        'at24spf_admin_page'
    );
});

// Settings link in plugin list
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function ( $links ) {
    $url = admin_url( 'options-general.php?page=simple_price_fee' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . __( 'Settings', 'simple-price-fee' ) . '</a>' );
    return $links;
});

// Admin page output
function at24spf_admin_page() {
    $settings = get_option( 'at24spf_settings', array( 'rules' => array(), 'label' => '' ) );
    $rules = $settings['rules'] ?? array();
    $label = $settings['label'] ?? '';
    ?>
    <div class="wrap">
        <h1>Simple Price Fee Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'at24spf_group' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Fee Label (shown in cart)</th>
                    <td><input type="text" name="at24spf_settings[label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <h2>Fee Rules</h2>
            <table id="at24spf_rules_table" class="widefat fixed">
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
                        <td><input name="at24spf_settings[rules][<?php echo esc_attr( $i ); ?>][min]" type="number" step="0.01" value="<?php echo esc_attr( $rule['min'] ); ?>"></td>
                        <td><input name="at24spf_settings[rules][<?php echo esc_attr( $i ); ?>][max]" type="number" step="0.01" value="<?php echo esc_attr( $rule['max'] ); ?>"></td>
                        <td><input name="at24spf_settings[rules][<?php echo esc_attr( $i ); ?>][amount]" type="number" step="0.01" value="<?php echo esc_attr( $rule['amount'] ); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="at24spf_add_rule">+ Add Rule</button></p>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
    </div>
    <?php
}

// WooCommerce fee logic
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $settings = get_option( 'at24spf_settings', array() );
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
