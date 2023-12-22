<?php
/**
 * Plugin Name: Gravity Forms Getepay Add-On
 * Plugin URI: https://getepay.in/PayWeb_Gravity_Forms
 * Description: Connects Gravity Forms to Getepay, an Indian payment gateway, for seamless integration.
 * Version: 1.0.0
 * Tested: 6.4
 * Author: Getepay
 * Author URI: https://www.getepay.in/
 * Developer: Getepay
 * Developer URI: https://monusingh.com/
 * Text Domain: gravityformsgetepay
 * Domain Path: /languages
 *
 * Copyright: © 2023 Getepay.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action('plugins_loaded', 'paygate_init');

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */

function paygate_init(): void
{
    /**
     * Auto updates from GIT
     *
     * @since 2.2.9
     *
     */

    require_once 'updater.class.php';

    if (is_admin()) {
        // note the use of is_admin() to double check that this is happening in the admin

        $config = array(
            'slug'               => plugin_basename(__FILE__),
            'proper_folder_name' => 'gravity-forms-getepay-plugin',
            'api_url'            => 'https://api.github.com/repos/monusinghlodhi/Getepay-Gravity-Forms',
            'raw_url'            => 'https://raw.github.com/monusinghlodhi/Getepay-Gravity-Forms/master',
            'github_url'         => 'https://github.com/monusinghlodhi/Getepay-Gravity-Forms',
            'zip_url'            => 'https://github.com/monusinghlodhi/Getepay-Gravity-Forms/archive/master.zip',
            'homepage'           => 'https://github.com/monusinghlodhi/Getepay-Gravity-Forms',
            'sslverify'          => true,
            'requires'           => '4.0',
            'tested'             => '6.0.2',
            'readme'             => 'README.md',
            'access_token'       => '',
        );

        new WP_GitHub_Updater_GETEPAY_GF($config);
    }
}

ob_start();
if ( !headers_sent() && empty(session_id())) {
    try {
        session_start();
    } catch (Exception $e) {
        // Catch exception
    }
}

add_action('gform_loaded', array('GF_Getepay_Bootstrap', 'load'), 5);

class GF_Getepay_Bootstrap
{

    public static function load()
    {
        if ( ! method_exists('GFForms', 'include_payment_addon_framework')) {
            return;
        }

        require_once 'getepay_gf_class.php';

        GFAddOn::register('GetepayGF');

        add_filter('gform_currencies', function (array $currencies) {
            $currencies['INR'] = array(
                'name'               => __( 'India Rupee', 'gravityforms' ),
                'code'               => 'INR',
                'symbol_left'        => '&#8377;',
                'symbol_right'       => '',
                'symbol_padding'     => ' ',
                'thousand_separator' => ',',
                'decimal_separator'  => '.',
                'decimals'           => 2
            );

            return $currencies;
        });

    }

}

function change_message($message, $form)
{
    if (isset($_SESSION['trans_failed']) && ! empty($_SESSION['trans_failed']) && strlen(
                                                                                      $_SESSION['trans_failed']
                                                                                  ) > 0) {
        $err_msg = $_SESSION['trans_failed'];

        return "<div class='validation_error'>" . $_SESSION['trans_failed'] . '</div>';
    } elseif (isset($_SESSION['trans_declined']) && ! empty($_SESSION['trans_declined'])) {
        $err_msg = $_SESSION['trans_declined'];

        return "<div class='validation_error'>" . $_SESSION['trans_declined'] . '</div>';
    } else {
        return $message;
    }
}

add_filter('gform_pre_render', 'gform_pre_render_callback');

function gform_pre_render_callback($form)
{
    ob_start();
    ob_clean();
    $form_id = $form['id'];
    define("SCRIPT", '<script type="text/javascript">');
    define("QUERY", 'jQuery(document).ready(function($){');
    define("QUERY_FORM", 'jQuery("#gform_');
    define('APPEND', ' .gform_heading").append("<div class=\"validation_error\">');
    define('DIV_TAG_CLOSING', '</div>")');
    define('SCRIPT_TAG_CLOSING', '</script>');

    if (isset($_SESSION['trans_failed']) && ! empty($_SESSION['trans_failed'])) {
        $msg = $_SESSION['trans_failed'];
        echo constant("SCRIPT");
        echo constant("Query");
        echo constant("QUERY_FORM") . $form_id . constant('APPEND') . $msg . constant('DIV_TAG_CLOSING');
        echo '});';
        echo constant('SCRIPT_TAG_CLOSING');
    } elseif (isset($_SESSION['trans_declined']) && ! empty($_SESSION['trans_declined'])) {
        $msg = $_SESSION['trans_declined'];
        echo constant("SCRIPT");
        echo constant('QUERY');
        echo constant("QUERY_FORM") . $form_id . constant('APPEND') . $msg . constant('DIV_TAG_CLOSING');
        echo '});';
        echo constant('SCRIPT_TAG_CLOSING');
    } elseif (isset($_SESSION['trans_cancelled']) && ! empty($_SESSION['trans_cancelled'])) {
        $msg = $_SESSION['trans_cancelled'];
        echo constant("SCRIPT");
        echo constant('QUERY');
        echo constant("QUERY_FORM") . $form_id . constant('APPEND') . $msg . constant('DIV_TAG_CLOSING');
        echo '});';
        echo constant('SCRIPT_TAG_CLOSING');
    }

    return $form;
}

add_filter('gform_pre_validation', 'cleanTransaction_status');

function cleanTransaction_status($form)
{
    unset($_SESSION['trans_failed']);
    unset($_SESSION['trans_declined']);
    unset($_SESSION['trans_cancelled']);

    return $form;
}

add_filter('gform_after_submission', 'gw_conditional_requirement');

function gw_conditional_requirement($form)
{
    if (isset($_SESSION['trans_failed']) && ! empty($_SESSION['trans_failed'])) {
        $confirmation = $_SESSION['trans_failed'];
        add_filter('gform_validation_message', 'change_message', 10, 2);
    } elseif (isset($_SESSION['trans_declined']) && ! empty($_SESSION['trans_declined'])) {
        $confirmation = $_SESSION['trans_declined'];
        add_filter('gform_validation_message', 'change_message', 10, 2);
    }

    return $form;
}

/**
 * Encrypt and decrypt
 *
 * @param string $string string to be encrypted/decrypted
 * @param string $action what to do with this? e for encrypt, d for decrypt
 */
function GF_encryption($string, $action = 'e')
{
    // you may change these values to your own
    /** @noinspection PhpUndefinedConstantInspection */
    $secret_key = AUTH_SALT;
    /** @noinspection PhpUndefinedConstantInspection */
    $secret_iv = NONCE_SALT;

    $output         = false;
    $encrypt_method = "AES-256-CBC";
    $key            = hash('sha256', $secret_key);
    $iv             = substr(hash('sha256', $secret_iv), 0, 16);

    if ($action == 'e') {
        $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
    } elseif ($action == 'd') {
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
    }

    return $output;
}
