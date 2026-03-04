define([
    'Magento_Ui/js/form/element/ui-select',
    'jquery',
    'mage/translate'
], function (UiSelect, $, $t) {
    'use strict';

    return UiSelect.extend({
        defaults: {
            filterOptions: true,
            chipsEnabled: false,
            disableLabel: true,
            levelsVisibility: 1,
            elementTmpl: 'ui/grid/filters/elements/ui-select',
            listens: {
                value: 'onValueChange'
            }
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();
            this.loadCategoryTree();
            return this;
        },

        /**
         * Load category tree from backend
         */
        loadCategoryTree: function () {
            var self = this;
            
            $.ajax({
                url: this.treeUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    store: this.getStoreId()
                },
                showLoader: true,
                success: function (response) {
                    if (response && response.length > 0) {
                        self.options(response);
                        self.cacheOptions.tree = response;
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Failed to load Google Product Category tree:', error);
                }
            });
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
        },

        /**
         * Handle value change
         */
        onValueChange: function (value) {
            if (value) {
                this.filterOptionsList();
            }
        }
    });
});
