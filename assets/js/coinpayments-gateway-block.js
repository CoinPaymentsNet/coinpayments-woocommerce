const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;
const { __ } = window.wp.i18n;
const { decodeEntities } = window.wp.htmlEntities;
const settings = window.wc.wcSettings.getSetting( 'coinpayments_data', {} );


const CoinpaymentsPaymentMethod = {
    name: settings.name,
    label: createElement(
        'span',
        { style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' } },
        decodeEntities(settings.title || __('CoinPayments.net', 'woocommerce')),
        settings.icon ? createElement('img', { src: settings.icon, alt: __('CoinPayments icon', 'woocommerce'), style: { marginLeft: '8px', verticalAlign: 'middle', height: '44px', minHeight: '44px' } }) : null
    ),
    ariaLabel: decodeEntities(settings.title || __('CoinPayments.net', 'woocommerce')),
    content: createElement('div', null, decodeEntities(settings.description || __('Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'woocommerce'))),
    edit: createElement('div', null, decodeEntities(settings.description || __('Pay with Bitcoin, Litecoin, or other altcoins via CoinPayments.net', 'woocommerce'))),
    canMakePayment: () => true,
};

registerPaymentMethod(CoinpaymentsPaymentMethod);
