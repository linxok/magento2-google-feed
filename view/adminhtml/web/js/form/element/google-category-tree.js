define([
    'Magento_Ui/js/form/element/abstract'
], function (Abstract) {
    'use strict';

    return Abstract.extend({

        defaults: {
            pickerUrl: '',
            displayLabel: '',
            elementTmpl: 'MyCompany_GoogleFeed/form/element/google-category-picker',
            listens: {}
        },

        initObservable: function () {
            this._super()
                .observe(['displayLabel']);

            return this;
        },

        initialize: function () {
            this._super();

            var self = this;

            window.addEventListener('message', function (event) {
                if (!event.data || event.data.type !== 'gcpick') {
                    return;
                }
                self.value(String(event.data.id));
                self.displayLabel(String(event.data.label));
            });

            return this;
        },

        openPickerWindow: function () {
            var storeId   = this._getStoreId(),
                baseUrl   = this.pickerUrl || '',
                selectedId = this.value() || '',
                sep        = baseUrl.indexOf('?') === -1 ? '?' : '&',
                url        = baseUrl + sep
                    + 'store=' + encodeURIComponent(storeId)
                    + '&selected=' + encodeURIComponent(selectedId),
                w = window.open(url, 'gc_picker', 'width=980,height=700,resizable=yes,scrollbars=yes');

            if (w) {
                w.focus();
            }
        },

        clearValue: function () {
            this.value('');
            this.displayLabel('');
        },

        _getStoreId: function () {
            var m = window.location.href.match(/[?&]store=(\d+)/);
            if (m) { return m[1]; }
            m = window.location.href.match(/\/store\/(\d+)/);
            if (m) { return m[1]; }
            return 0;
        }
    });
});
