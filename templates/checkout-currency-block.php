<p class="form-row form-row-wide validate-required" id="coin_currency_field" data-priority="40">
    <label for="coin_currency" class="">
        <?= __('Currency', 'coinpayments-payment-gateway-for-woocommerce') ?>
        <abbr class="required" title="required">*</abbr>
    </label>
    <span class="">
        <select name="coin_currency" id="coin_currency" class="coin_currency">
            <option value="" disabled
                    selected><?= __('-- Select Coinpayments currency --', 'coinpayments-payment-gateway-for-woocommerce') ?></option>
            <?php foreach ($currencies as $currency_code => $currency_name): ?>
                <option value="<?= $currency_code ?>" <?= ($currency_code == $default_rate ? 'selected' : '') ?>><?= $currency_name ?></option>
            <?php endforeach; ?>
        </select>
    </span>
</p>
<br/>
<p class="form-row form-row-wide validate-required" id="coin_currency_field" data-priority="40">
    <label for="coinpayments_currency_amount">
        <?= __('Currency amount:', 'coinpayments-payment-gateway-for-woocommerce') ?>
    </label>
    <span id="coinpayments_currency_amount" class="float-right">
        <?php if ($default_rate): ?>
            <?= $currency_amount ?>
        <?php else: ?>
            <?= $total ?> <?= $default_currency ?>
        <?php endif; ?>
    </span>
</p>
<br/>
