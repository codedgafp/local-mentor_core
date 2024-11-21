/**
 * Javascript Mentor lib management.
 */

define([
    'jquery',
    'jqueryui'
], function ($) {

    var mentor = {

        /**
         * Create JqueryUI modale with default config.
         *
         * @param {string} selector
         * @param {JSON} config
         */
        dialog: function (selector, config) {
            var that = this;

            if (typeof config.open !== 'undefined') {
                var openCallback = config.open;
                delete config.open;
            }

            // Default dialog configuration.
            var defaultConfig = {
                height: "auto",
                my: "center",
                at: "center",
                modal: true,
                draggable: false,
                dialogClass: 'confirm-dialog',
                open: function () {
                    that.configTitleDialog();
                    that.accessibilityAdjustments();

                    // Add a title on close button.
                    $(".ui-dialog-titlebar-close")
                        .html('')
                        .attr('title', M.util.get_string('closebuttontitle', 'moodle'))
                        .focus();

                    // Call the open callback if defined in config param.
                    if (typeof openCallback !== 'undefined') {
                        openCallback();
                    }
                },
            };

            // Merge and update default config.
            Object.assign(defaultConfig, config);

            $(selector).dialog(defaultConfig);

            if (defaultConfig.id) {
                // Add id attribute to dialog.
                $(selector).parent().attr('id', defaultConfig.id);
            }
        },
        /**
         * Accessibility adjustments for dialogs
         */
        accessibilityAdjustments: function () {
            var dialogElements = $('.ui-dialog[role="dialog"]');

            dialogElements.each(function () {
                // Add aria-modal attribute
                $(this).attr('aria-modal', 'true');

            });
        },
        /**
         * Replace span by h1 on every dialog title
         */
        configTitleDialog: function () {
            var that = this;

            // Change all JqueryUI modale title with "h1" element
            var titleElements = $('span.ui-dialog-title');

            titleElements.each(function () {

                var attrs = {};

                attrs.text = that.sanitizeText(this.innerText);

                $.each(this.attributes, function (index, attribute) {
                    attrs[attribute.nodeName] = attribute.nodeValue;
                });

                $(this).replaceWith($("<h1 />", attrs));
            });
        },
        /**
         * Return number of line for the element
         *
         * @param element
         * @returns {number}
         */
        getNumberOfLine: function (element) {
            var elementSelector = $(element)[0];
            return (elementSelector.clientHeight) / parseInt(window.getComputedStyle(elementSelector).lineHeight, 10);
        },

        /**
         * Sanitize a string
         *
         * @param string text
         * @returns {*}
         */
        sanitizeText: function (text) {
            var $div = document.createElement('div');
            $div.innerText = text;
            return $div.innerHTML;
        }

    };

    // Add object to window to be called outside require.
    window.mentor = mentor;
    return mentor;
});


