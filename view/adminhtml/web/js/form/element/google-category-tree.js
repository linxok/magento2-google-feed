define([
    'Magento_Ui/js/form/element/select',
    'jquery',
    'mage/translate'
], function (Select, $, $t) {
    'use strict';

    return Select.extend({

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.addPickerButton();
            return this;
        },

        /**
         * Add lightweight popup picker button near current field
         */
        addPickerButton: function () {
            var self = this;

            window.setTimeout(function () {
                var fieldContainer = $('[data-index="' + self.index + '"] .admin__field-control').first(),
                    buttonClass = 'mycompany-google-category-picker-btn';

                if (!fieldContainer.length || fieldContainer.find('.' + buttonClass).length) {
                    return;
                }

                $('<button/>', {
                    type: 'button',
                    'class': 'action-default scalable ' + buttonClass,
                    text: $t('Open Tree Picker'),
                    css: {
                        marginLeft: '10px'
                    }
                }).on('click', function () {
                    self.openPickerWindow();
                }).appendTo(fieldContainer);
            }, 250);
        },

        /**
         * Open separate picker window rendered by PHP controller
         */
        openPickerWindow: function () {
            var storeId = this.getStoreId(),
                inputName = this.inputName || 'category[mycompany_google_product_category]',
                baseUrl = this.pickerUrl || 'googlefeed/category/picker',
                selectedId = this.value && typeof this.value === 'function' ? this.value() : '',
                separator = baseUrl.indexOf('?') === -1 ? '?' : '&',
                url = baseUrl
                    + separator + 'store=' + encodeURIComponent(storeId)
                    + '&field=' + encodeURIComponent(inputName)
                    + '&selected=' + encodeURIComponent(selectedId || ''),
                pickerWindow = window.open(url, 'mycompany_google_category_picker', 'width=980,height=700,resizable=yes,scrollbars=yes');

            if (pickerWindow) {
                pickerWindow.focus();
            }
        },

        /**
         * Get current store ID
         */
        getStoreId: function () {
            var storeId = 0;
            var matches = window.location.href.match(/\/store\/(\d+)/);
            
            if (matches && matches[1]) {
                storeId = matches[1];
            }
            
            return storeId;
        }
    });
});
