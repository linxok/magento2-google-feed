define([
    'Magento_Ui/js/form/element/abstract'
], function (Abstract) {
    'use strict';

    var TMPL_ID = 'gc-category-picker-tmpl';

    function injectTemplate() {
        if (document.getElementById(TMPL_ID)) {
            return;
        }
        var s = document.createElement('script');
        s.type = 'text/html';
        s.id   = TMPL_ID;
        s.text = [
            '<input type="hidden"',
            '    data-bind="value: value, attr: {name: inputName, id: uid}"/>',
            '<input class="admin__control-text" type="text" readonly="readonly"',
            '    style="width:auto;min-width:260px;cursor:default;background:#f9f9f9;"',
            '    data-bind="value: displayLabel,',
            '        attr: {id: uid + \'_display\', placeholder: $t(\'No category selected\')}"',
            '/>',
            '<button type="button" class="action-default scalable" style="margin-left:6px;"',
            '    data-bind="click: openPickerWindow, i18n: \'Select Category\'">',
            '</button>',
            '<!-- ko if: value() -->',
            '<button type="button" class="action-default scalable" style="margin-left:4px;"',
            '    data-bind="click: clearValue, i18n: \'Clear\'">',
            '</button>',
            '<!-- /ko -->'
        ].join('\n');
        document.head.appendChild(s);
    }

    return Abstract.extend({

        defaults: {
            pickerUrl: '',
            displayLabel: '',
            elementTmpl: TMPL_ID,
            listens: {}
        },

        initObservable: function () {
            this._super()
                .observe(['displayLabel']);

            return this;
        },

        initialize: function () {
            injectTemplate();
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
