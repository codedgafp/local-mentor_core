/**
 * Javascript containing function for duplicating session
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
        init: function () {
            var that = this;

            if ($('#entitieslist').length > 0) {
                this.entities = $.parseJSON($('#entitieslist').html());

                // Observer on entity change.
                $('#id_entityid').on('change', function () {
                    var entityid = $(this).val();

                    that.updateSubentities(entityid);
                });

                $('#id_duplicationtype').on('change', function () {
                    // Check if entityid input exist.
                    if ($('#id_entityid').length) {
                        that.updateSubentities($('#id_entityid').val());
                    } else {
                        that.updateSubentities($('input[name="entityid"]').val());
                    }
                });

                // Initial update of subentities list.
                if ($('#id_entityid').length) { // Check if entityid input exist.
                    that.updateSubentities($('#id_entityid').val());
                } else {
                    that.updateSubentities($('input[name="entityid"]').val());
                }
            }

            // If there is just one entity, hide the selector.
            if ($('#id_entityid option').length == 1) {
                $('#fitem_id_entityid').hide();
            }

            // Submit event form.
            $('.mform').on('submit', function (eventForm) {
                if (eventForm.originalEvent.submitter.id !== 'id_cancel') {
                    // Catch submit event.
                    eventForm.preventDefault();

                    if ($('.mform #id_duplicationtype').val() === '1') {
                        // Overwrite training dialog.
                        mentor.dialog('<p class="text-center">' + M.util.get_string('confirmationwarnining', 'local_mentor_core') + '</p>', {
                            width: 600,
                            title: M.util.get_string('confirmation', 'local_mentor_core'),
                            close: function () {
                                $(this).dialog("destroy");
                            },
                            buttons: [
                                {
                                    // Cancel button
                                    text: M.util.get_string('confirm', 'moodle'),
                                    class: "btn btn-primary",
                                    click: function () {
                                        // Overwrite training submit.
                                        eventForm.target.submit();
                                    }
                                },
                                {
                                    // Confirm button
                                    text: M.util.get_string('cancel', 'moodle'),
                                    id: 'confirm-form-import-csv',
                                    class: "btn btn-secondary",
                                    click: function () {//Just close the modal
                                        $(this).dialog("destroy");
                                    }
                                },
                            ]
                        });
                    } else {
                        // Create new training submit.
                        eventForm.target.submit();
                    }
                }
            });
        },
        /**
         * Update subentities list depending on an entity id
         * @param int entityid
         */
        updateSubentities: function (entityid) {

            var subentities = this.entities[entityid];

            if (subentities.length == 0) {
                // THe entity has no subentities so hide them.
                $('#fitem_id_subentityid').hide();
            } else {
                // Remove subentities options.
                $('#id_subentityid').empty();

                // Add subentity option.
                $.each(subentities, function (key, value) {
                    var $option = document.createElement('option');
                    $option.setAttribute('value', value.id);
                    $option.text = mentor.sanitizeText(value.name);
                    $('#id_subentityid').append($option);
                });

                // Show subentity selector.
                $('#fitem_id_subentityid').show();
            }
        },
        /**
         * Duplicate success pop-in
         * If you confirm, redirect to session course page
         *
         * @param {int} sessionCourseId
         */
        duplicateSuccess: function (sessionCourseId) {

            mentor.dialog('<div class="text-center"><span>' + M.util.get_string('confirmduplication', 'local_mentor_core') + '</span></div>', {
                height: 260,
                width: 550,
                title: M.util.get_string('confirmation', 'local_mentor_core'),
                buttons: [
                    {
                        // Remove button
                        text: M.util.get_string('close', 'local_mentor_core'),
                        id: 'close-duplicate-success',
                        class: "btn btn-primary",
                        click: function (e) {
                            window.location.href = M.cfg.wwwroot + '/course/view.php?id=' + sessionCourseId;
                        }
                    }
                ],
                close: function (event, ui) {
                    $(this).dialog("destroy");
                },
            });
        }
    };
});
