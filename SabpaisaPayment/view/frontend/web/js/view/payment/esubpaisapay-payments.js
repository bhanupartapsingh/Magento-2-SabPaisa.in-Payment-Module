/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'esubpaisapay',
                component: 'Ebizinfosys_SabpaisaPayment/js/view/payment/method-renderer/esubpaisapay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
