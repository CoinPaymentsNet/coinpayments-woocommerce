jQuery(function ($) {

    $(document.body).on('updated_checkout', function (event) {
        append_currencies_block();
    });


    function append_currencies_block() {

        if (!window.coinpayments) {

            $('.woocommerce-checkout-payment').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                type: 'POST',
                url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'json_coinpayments_data'),
                success: function (data) {
                    window.coinpayments = data;
                    append_currencies_block();
                    $('.woocommerce-checkout-payment').unblock();
                }
            });
        } else {

            $('.payment_box.payment_method_coinpayments').prepend(window.coinpayments.currency_block);

            $('#coin_currency')
                .on('select2:select', function () {
                    $(this).focus(); // Maintain focus after select https://github.com/select2/select2/issues/4384
                })
                .selectWoo({
                    width: '100%',
                    minimumResultsForSearch: Infinity
                });

            $('#coin_currency')
                .on('change', function () {

                    selected_currency = window.coinpayments.selected_currency = this.value;
                    var amount = 0.00;

                    if (selected_currency) {
                        var store_btc_rate = window.coinpayments.rates[window.coinpayments.default_currency].rate_btc;
                        if (selected_currency === 'BTC') {
                            amount = (store_btc_rate * window.coinpayments.total);
                        } else {
                            amount = (store_btc_rate * window.coinpayments.total) / window.coinpayments.rates[selected_currency].rate_btc;
                        }
                    } else {
                        amount = window.coinpayments.total;
                        selected_currency = window.coinpayments.default_currency;
                    }
                    amount = amount.toFixed(7);

                    $('#coinpayments_currency_amount').html(amount + ' ' + selected_currency);

                    $.ajax({
                        type: 'POST',
                        url: wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'set_coinpayments_currency'),
                        data: {'coinpayments_currency': selected_currency}
                    });
                });

            if (window.coinpayments.selected_currency) {
                $('#coin_currency').val(window.coinpayments.selected_currency).change();
            }

        }

    }

});

