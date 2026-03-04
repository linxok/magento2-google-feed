define([
    'Magento_Ui/js/form/element/abstract',
    'jquery',
    'ko',
    'mage/translate',
    'uiRegistry'
], function (Abstract, $, ko, $t, registry) {
    'use strict';

    return Abstract.extend({

        defaults: {
            elementTmpl: 'MyCompany_GoogleFeed/form/element/google-category-picker',
            pickerUrl: '',
            displayLabel: '',
            listens: {}
        },

        /**
         * Initialize observables — called before initialize()
         */
        initObservable: function () {
            this._super().observe(['displayLabel']);
            return this;
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this._bindPostMessage();
            return this;
        },

        /**
         * Listen for postMessage from picker popup window
         */
        _bindPostMessage: function () {
            var self = this;

            window.addEventListener('message', function (event) {
                if (!event.data || event.data.type !== 'gcpick') {
                    return;
                }
                self.value(String(event.data.id));
                self.displayLabel(String(event.data.label));
                self.bubble('update', self.hasChanged());
            });
        },

        /**
         * Open PHP-rendered picker popup
         */
        openPickerWindow: function () {
            var storeId = this.getStoreId(),
                baseUrl = this.pickerUrl || '',
                selectedId = typeof this.value === 'function' ? this.value() : '',
                separator = baseUrl.indexOf('?') === -1 ? '?' : '&',
                url = baseUrl
                    + separator + 'store=' + encodeURIComponent(storeId)
                    + '&selected=' + encodeURIComponent(selectedId || ''),
                pickerWindow = window.open(
                    url,
                    'mycompany_google_category_picker',
                    'width=980,height=700,resizable=yes,scrollbars=yes'
                );

            if (pickerWindow) {
                pickerWindow.focus();
            }
        },

        /**
         * Clear the selected value
         */
        clearValue: function () {
            this.value('');
            this.displayLabel('');
            this.bubble('update', this.hasChanged());
        },

        /**
         * Get current store ID from URL
         */
        getStoreId: function () {
            var matches = window.location.href.match(/[?&]store=(\d+)/);
            if (matches && matches[1]) {
                return matches[1];
            }
            matches = window.location.href.match(/\/store\/(\d+)/);
            if (matches && matches[1]) {
                return matches[1];
            }
            return 0;
        }
    });
});
