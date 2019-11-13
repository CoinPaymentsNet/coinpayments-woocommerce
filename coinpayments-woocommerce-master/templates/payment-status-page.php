<?php
/**
 * The Template for displaying all single products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see        https://docs.woocommerce.com/document/template-structure/
 * @package    WooCommerce/Templates
 * @version     1.6.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

get_header('shop'); ?>

<?php
/**
 * woocommerce_before_main_content hook.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 */
do_action('woocommerce_before_main_content');
?>

    <article id="post-9" class="post-9 page type-page status-publish hentry">
        <header class="entry-header">
            <h1 class="entry-title"><?= __("Order received", 'coinpayments-payment-gateway-for-woocommerce') ?></h1>
        </header>
        <div class="entry-content">
            <div class="woocommerce">

                <div class="woocommerce-content">
                    <div class="woocommerce-notices-wrapper"></div>

                    <table class="woocommerce-status-table woocommerce-orders shop_table shop_table_responsive account-status-table">

                        <tbody>
                        <tr class="woocommerce-status-table__row">
                            <td class="woocommerce-status-table__cell" colspan="2">
                                <h3>
                                    <a href="<?= $transaction['status_url'] ?>"
                                       target="_blank"><?= __("Status link", 'coinpayments-payment-gateway-for-woocommerce') ?></a>
                                </h3>
                            </td>
                        </tr>
                        <tr class="woocommerce-status-table__row">
                            <td class="woocommerce-status-table__cell">
                                <span><?= __("Address", 'coinpayments-payment-gateway-for-woocommerce') ?></span>:
                            </td>
                            <td class="woocommerce-status-table__cell">
                                <span><?= $transaction['address'] ?></span>
                            </td>
                        </tr>
                        <tr class="woocommerce-status-table__row">
                            <td class="woocommerce-status-table__cell">
                                    <span><?= __("Amount", 'coinpayments-payment-gateway-for-woocommerce') ?>:
                            </td>
                            <td class="woocommerce-status-table__cell">
                                <span><?= $transaction['amount'] ?> <?= $custom_currency ?>
                            </td>
                        </tr>

                        <?php if ($transaction['time_left'] > 0): ?>
                            <tr class="woocommerce-status-table__row">
                                <td class="woocommerce-status-table__cell">
                                    <span><?= __("Time left", 'coinpayments-payment-gateway-for-woocommerce') ?></span>:
                                </td>
                                <td class="woocommerce-status-table__cell">
                                    <span id="time_left_out"><?= $transaction['time_diff'] ?></span>
                                </td>
                            </tr>
                        <?php endif ?>

                        <tr class="woocommerce-status-table__row">
                            <td class="woocommerce-status-table__cell">
                                <span><?= __("QR Code", 'coinpayments-payment-gateway-for-woocommerce') ?></span>:
                            </td>
                            <td class="woocommerce-status-table__cell">
                                <span><img class="thumb" src="<?= $transaction['qrcode_url'] ?>"></span>
                            </td>
                        </tr>

                        </tbody>
                    </table>

                </div>
            </div>
        </div><!-- .entry-content -->
    </article>

    <script type="text/javascript">

        var timer = document.getElementById('time_left').value;
        var minutes, seconds;

        setInterval(function () {
            minutes = parseInt(timer / 60, 10);
            seconds = parseInt(timer % 60, 10);


            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;


            if (--timer >= 0) {
                document.getElementById('time_left_out').textContent = minutes + "m " + seconds + 's';
            }
        }, 1000);
    </script>
<?php
/**
 * woocommerce_after_main_content hook.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action('woocommerce_after_main_content');
?>

<?php
/**
 * woocommerce_sidebar hook.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action('woocommerce_sidebar');
?>

<?php get_footer('shop');

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
