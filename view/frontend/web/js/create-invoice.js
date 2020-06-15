define([
    'jquery',
    'jquery-ui-modules/widget'
], function ($) {
    'use strict';

    $.widget('aario.createInvoicePopup', {
        options: {
        },

        /** @inheritdoc */
        _create: function () {
                $(this.options.createButton).on('click', $.proxy(function () {
                var shipmentIds = [];
                $(".shipment-checkbox").each(function() {
                    const checkbox = $(this);
                    if (!checkbox.is(":checked")) {
                        return;
                    }
                    shipmentIds.push(checkbox.val());
                });

                $(this.element).validation().submit();
            }, this));
        }
    });

    return $.aario.createInvoicePopup;
});
