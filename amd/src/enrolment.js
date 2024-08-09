/**
 * Javascript containing function of the catalog space
 */

define([
    'jquery',
    'jqueryui',
    'format_edadmin/format_edadmin',
    'local_mentor_core/mentor'
], function ($, ui, format_edadmin, mentor) {


    var enrolment = {

        /**
         * Opens a pop-up for the user to register for a session
         *
         * @param {int} sessionId
         */
        enrolmentPopup: function (sessionId) {

            if (typeof sessionId === 'undefined') {
                return;
            }

            format_edadmin.ajax_call({
                url: M.cfg.wwwroot + '/local/catalog/ajax/ajax.php',
                controller: 'catalog',
                action: 'get_session_enrolment_data',
                format: 'json',
                sessionid: sessionId,
                callback: function (response) {
                    response = JSON.parse(response);

                    // Find the id of the right popup to display.
                    var selector = response.message.hasselfregistrationkey ? 'enrolment-popup-with-key' : 'enrolment-popup';

                    mentor.dialog('#' + selector, {
                        height: 360,
                        width: 590,
                        title: M.util.get_string('enrolmentpopuptitle', 'local_mentor_core'),
                        buttons: [
                            {
                                // Confirm button
                                text: M.util.get_string('confirm', 'moodle'),
                                id: 'confirm-enrol-session',
                                class: "btn btn-primary",
                                click: function (e) {

                                    var enrolmentKey = $('#enrolmentkey').val();

                                    format_edadmin.ajax_call({
                                        url: M.cfg.wwwroot + '/local/catalog/ajax/ajax.php',
                                        controller: 'catalog',
                                        action: 'enrol_current_user',
                                        format: 'json',
                                        sessionid: sessionId,
                                        enrolmentkey: enrolmentKey,
                                        callback: function (ajaxResponse) {
                                            ajaxResponse = JSON.parse(ajaxResponse);

                                            if (ajaxResponse.success === true) {
                                                window.location.href = ajaxResponse.message;
                                            } else {
                                                $('#' + selector + ' .enrolment-warning').html(ajaxResponse.message);
                                            }
                                        }
                                    });
                                }
                            },
                            {
                                // Cancel button
                                text: M.util.get_string('cancel', 'moodle'),
                                class: "btn btn-secondary",
                                click: function () {//Just close the modal
                                    $(this).dialog("destroy");
                                }
                            }
                        ],
                        close: function (event, userInterface) {
                            $(this).dialog("destroy");
                        }
                    });
                }
            });
        }
    };

    // Add object to window to be called outside require.
    window.enrolment = enrolment;
    return enrolment;
});
