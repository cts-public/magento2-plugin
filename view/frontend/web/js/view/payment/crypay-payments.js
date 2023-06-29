define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ], function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'crypay_merchant',
                component: 'CryPay_Merchant/js/view/payment/method-renderer/crypay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
