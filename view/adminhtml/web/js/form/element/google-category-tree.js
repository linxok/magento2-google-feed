define([
    'Magento_Ui/js/form/element/abstract',
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function (Abstract, $, modal, $t) {
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
            '    data-bind="click: openPickerModal, i18n: \'Select Category\'">',
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
            return this;
        },

        openPickerModal: function () {
            var self      = this,
                storeId   = this._getStoreId(),
                baseUrl   = this.pickerUrl || '',
                selectedId = this.value() || '',
                sep        = baseUrl.indexOf('?') === -1 ? '?' : '&',
                url        = baseUrl + sep
                    + 'store=' + encodeURIComponent(storeId)
                    + '&selected=' + encodeURIComponent(selectedId);

            if (!this._modalContainer) {
                this._modalContainer = $('<div id="gc-picker-modal-content"/>').appendTo('body');

                this._modal = modal({
                    type: 'slide',
                    title: $t('Select Google Product Category'),
                    buttons: [],
                    closed: function () {
                        self._modalContainer.empty();
                        window.gcOnPick = null;
                    }
                }, this._modalContainer);
            }

            window.gcOnPick = function (id, label) {
                self.value(id);
                self.displayLabel(label);
                self._modalContainer.modal('closeModal');
            };

            this._modalContainer
                .html('<div style="padding:20px;text-align:center;color:#999;">' + $t('Loading...') + '</div>')
                .modal('openModal');

            $.ajax({
                url: url,
                type: 'GET',
                success: function (html) {
                    self._modalContainer.html(html);
                },
                error: function () {
                    self._modalContainer.html('<div style="padding:20px;color:red;">' + $t('Failed to load categories.') + '</div>');
                }
            });
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
