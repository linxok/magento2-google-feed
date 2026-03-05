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

            var self = this;

            // Load initial label
            this._loadLabel();

            // Listen for store switcher changes
            $(document).on('change', '[data-role="stores-list"]', function () {
                if (self.value()) {
                    self._loadLabel();
                }
            });

            return this;
        },

        _loadLabel: function () {
            var self = this;
            
            if (!self.value()) {
                return;
            }

            var storeId = self._getStoreId(),
                baseUrl = self.pickerUrl || '',
                sep     = baseUrl.indexOf('?') === -1 ? '?' : '&',
                url     = baseUrl + sep
                    + 'store=' + encodeURIComponent(storeId)
                    + '&selected=' + encodeURIComponent(self.value())
                    + '&label_only=1';

            $.ajax({
                url: url,
                type: 'GET',
                success: function (label) {
                    if (label) {
                        self.displayLabel(label.trim());
                    }
                }
            });
        },

        openPickerModal: function () {
            var self       = this,
                storeId    = this._getStoreId(),
                baseUrl    = this.pickerUrl || '',
                selectedId = this.value() || '',
                sep        = baseUrl.indexOf('?') === -1 ? '?' : '&',
                url        = baseUrl + sep
                    + 'store=' + encodeURIComponent(storeId)
                    + '&selected=' + encodeURIComponent(selectedId);

            if (!this._modalContainer) {
                this._modalContainer = $('<div id="gc-picker-modal-content"/>').appendTo('body');

                modal({
                    type: 'slide',
                    title: $t('Select Google Product Category'),
                    buttons: [],
                    closed: function () {
                        self._modalContainer.empty();
                    }
                }, this._modalContainer);
            }

            this._modalContainer
                .html('<div style="padding:20px;text-align:center;color:#999;">' + $t('Loading...') + '</div>')
                .modal('openModal');

            $.ajax({
                url: url,
                type: 'GET',
                success: function (html) {
                    self._modalContainer.html(html);
                    self._bindPickerEvents();
                },
                error: function () {
                    self._modalContainer.html(
                        '<div style="padding:20px;color:red;">' + $t('Failed to load categories.') + '</div>'
                    );
                }
            });
        },

        _bindPickerEvents: function () {
            var self      = this,
                container = this._modalContainer;

            container.off('.gcpicker');

            container.on('click.gcpicker', '.gc-pick-btn', function () {
                var id    = String($(this).data('id')),
                    label = String($(this).data('label'));

                self.value(id);
                self.displayLabel(label);
                container.modal('closeModal');
            });

            container.on('input.gcpicker', '#gc-search', function () {
                clearTimeout(self._searchTimer);
                var q = $(this).val().trim();
                self._searchTimer = setTimeout(function () {
                    self._runSearch(q, container);
                }, 150);
            });
        },

        _runSearch: function (q, container) {
            var allLi   = container.find('#gc-tree li'),
                infoEl  = container.find('#gc-info');

            container.find('.gc-highlight').each(function () {
                var p = this.parentNode;
                p.replaceChild(document.createTextNode(this.textContent), this);
                p.normalize();
            });

            if (!q) {
                allLi.removeClass('gc-hidden');
                container.find('#gc-tree details').removeAttr('open');
                infoEl.text('');
                return;
            }

            var ql = q.toLowerCase(), count = 0;
            allLi.addClass('gc-hidden');

            allLi.each(function () {
                var li      = $(this),
                    labelEl = li.find('> .gc-node .gc-node-label, > details > summary .gc-node-label').first();

                if (!labelEl.length) { return; }

                var text = labelEl.text();
                if (text.toLowerCase().indexOf(ql) === -1) { return; }

                count++;
                li.removeClass('gc-hidden');

                var idx  = text.toLowerCase().indexOf(ql),
                    mark = $('<mark class="gc-highlight"/>').text(text.slice(idx, idx + q.length)),
                    frag = document.createDocumentFragment();

                frag.appendChild(document.createTextNode(text.slice(0, idx)));
                frag.appendChild(mark[0]);
                frag.appendChild(document.createTextNode(text.slice(idx + q.length)));
                labelEl[0].textContent = '';
                labelEl[0].appendChild(frag);

                li.parents('#gc-tree li').removeClass('gc-hidden');
                li.parents('#gc-tree details').attr('open', '');
            });

            infoEl.text(count + ' result' + (count !== 1 ? 's' : ''));
        },

        clearValue: function () {
            this.value('');
            this.displayLabel('');
        },

        _getStoreId: function () {
            var storeValue = null;
            
            // Try to get store from Magento store switcher
            var storeSwitcher = $('[data-role="stores-list"]');
            if (storeSwitcher.length) {
                storeValue = storeSwitcher.val();
                console.log('Google Category Picker - Store from switcher:', storeValue);
            }
            
            // Fallback: try URL parameters (both ID and code)
            if (!storeValue) {
                var m = window.location.href.match(/[?&]store=([^&]+)/);
                if (m) { 
                    storeValue = m[1];
                    console.log('Google Category Picker - Store from URL:', storeValue);
                }
            }
            
            // Fallback: try store_id input field (category form)
            if (!storeValue) {
                var storeInput = $('input[name="store_id"]');
                if (storeInput.length && storeInput.val()) {
                    storeValue = storeInput.val();
                    console.log('Google Category Picker - Store from input:', storeValue);
                }
            }
            
            // Return the value (can be ID or code - server will handle both)
            return storeValue || 0;
        }
    });
});
