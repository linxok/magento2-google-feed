define([
    'Magento_Ui/js/form/element/ui-select',
    'jquery',
    'mage/translate',
    'underscore'
], function (UiSelect, $, $t, _) {
    'use strict';

    return UiSelect.extend({
        defaults: {
            filterOptions: true,
            chipsEnabled: false,
            disableLabel: true,
            levelsVisibility: 1,
            openLevelsAction: true,
            closeLevelsAction: true,
            elementTmpl: 'ui/grid/filters/elements/ui-select'
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
                        var flatOptions = self.flattenTree(response);
                        self.setOptions(flatOptions);
                        self.cacheOptions.plain = flatOptions;
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Failed to load Google Product Category tree:', error);
                }
            });
        },

        /**
         * Flatten tree structure to plain list with indentation
         */
        flattenTree: function (tree, level, parentPath) {
            var options = [],
                self = this;
            
            level = level || 0;
            parentPath = parentPath || '';
            
            _.each(tree, function (node) {
                var indent = new Array(level + 1).join('    '),
                    currentPath = parentPath ? parentPath + '/' + node.label : node.label,
                    option = {
                        value: node.value,
                        label: indent + node.label,
                        level: level,
                        path: currentPath
                    };
                
                options.push(option);
                
                if (node.optgroup && node.optgroup.length > 0) {
                    options = options.concat(self.flattenTree(node.optgroup, level + 1, currentPath));
                }
            });
            
            return options;
        },

        /**
         * Set options
         */
        setOptions: function (options) {
            this.options(options);
            
            return this;
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
         * Keep default value handling from ui-select for better performance.
         */
        onValueChange: function () {}
    });
});
