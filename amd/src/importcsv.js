/**
 * Javascript containing function of the catalog space
 */
define([
    'jquery',
    'jqueryui',
    'local_mentor_core/mentor'
], function ($, ui, mentor) {
    return {

        /**
         * Init JS
         */
        init: function (modaltitle, modalcontent) {

            // Check if import button exist
            if ($('#import-reports').children().length) {

                //
                $('#import-reports .mform').on('submit', function (eventForm) {
                    eventForm.preventDefault();
                    mentor.dialog('<p class="text-justify">' + modalcontent + '</p>', {
                        width: 700,
                        title: modaltitle,
                        close: function () {
                            $(this).dialog("destroy");
                        },
                        buttons: [
                            {
                                // Cancel button
                                text: M.util.get_string('no', 'moodle'),
                                class: "btn btn-secondary",
                                click: function () {//Just close the modal
                                    $(this).dialog("destroy");
                                }
                            },
                            {
                                // Confirm button
                                text: M.util.get_string('yes', 'moodle'),
                                id: 'confirm-form-import-csv',
                                class: "btn btn-primary",
                                click: function () {
                                    eventForm.target.submit();
                                }
                            },
                        ]
                    });

                });
            }
        }
    };
});