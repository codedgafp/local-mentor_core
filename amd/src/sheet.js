/**
 * Javascript containing function for the training and session sheet events
 */

define([
    'jquery',
    'jqueryui',
    'local_mentor_core/enrolment',
    'local_mentor_core/iframe',
    'core/templates',
    'core/notification',
    'local_mentor_core/mentor'
], function ($, ui, enrolment, iframe, templates, displayException, mentor) {

    /**
     * Use the templates contained in local_mentor_core/sheet to use this library
     *
     * @type {{initSheet: sheet.initSheet, initTrainingSheet: sheet.initTrainingSheet, initSessionSheet: sheet.initSessionSheet}}
     */
    var sheet = {

        /**
         * Default training and session sheet events initialization
         *
         * The element where the user clicks to open the training sheet
         * must contain the training id in a "data-training-id" attribute
         *
         * @param {string} elementClassToOpenSheet Element class where user click for open training sheet
         * @param {string} overlayId Overlay element id
         */
        initSheet: function (elementClassToOpenSheet, overlayId) {
            sheet.initTrainingSheet(elementClassToOpenSheet, overlayId, true, '', '');
        },
        loadTrainingSheet: function (trainingId, currentElement, overlayId, initSessionSheet, trainingsElement, sheetOpener) {
            var that = this;

            if (trainingsElement == '' || typeof trainingsElement === 'undefined') {
                trainingsElement = 'main[role="main"] #available-trainings';
            }

            if (sheetOpener == '' || typeof sheetOpener === 'undefined') {
                sheetOpener = '';
            }

            this.trainings = JSON.parse($(trainingsElement).html());

            if (!this.trainings.hasOwnProperty(trainingId)) {
                mentor.dialog("<div>" + M.util.get_string('notpermissionscourse', 'local_mentor_core') + "</div>", {
                    height: 200,
                    width: 500,
                    title: M.util.get_string('trainingnotavailable', 'local_mentor_core'),
                    position: {my: "center", at: "top+20"},
                    buttons: [
                        {
                            text: "OK",
                            class: "btn btn-primary",
                            click: function () {
                                $(this).dialog("close");
                            }
                        }
                    ]
                });
                return;
            }

            var trainingData = this.trainings[trainingId];

            $('.training-sheet').remove();
            $('.session-sheet').remove();

            trainingData.isloggedin = !$('body').hasClass('notloggedin');

            templates.renderForPromise('local_mentor_specialization/catalog/training-sheet', trainingData)

                // It returns a promise that needs to be resoved.
                .then(function (_ref) {
                    var html = _ref.html;

                    var elem = $(html);
                    elem.addClass(sheetOpener);

                    $('main[role="main"]').append(elem);
                })
                .then(function () {
                    that.loadTrainingSessions(trainingId);
                })
                .then(function () {
                    sheet.initSessionTiles('session-tile', overlayId);
                    that.openTrainingSheet(trainingId, currentElement, overlayId);
                })
                // Deal with this exception (Using core/notify exception function is recommended).
                .catch(function (ex) {
                    displayException(ex);
                });


        },
        loadTrainingSessions: function (trainingId) {
            var sessions = this.trainings[trainingId].sessions;

            for (var i in sessions) {
                var sessionData = sessions[i];

                this.loadSessionSheet(sessionData);
            }
        },
        loadSessionSheet: function (sessionData) {

            // If user is not logged in, do not call session sheet renderer.
            if (!$('body').hasClass('notloggedin')) {
                templates.renderForPromise('local_mentor_specialization/catalog/session-sheet', sessionData)

                    // It returns a promise that needs to be resoved.
                    .then(function (_ref) {
                        var html = _ref.html;
                        var js = _ref.js;

                        $('main[role="main"]').append(html);

                        $('#session-sheet-' + sessionData.id + ' .enrolment-button').on('click', function () {
                            enrolment.enrolmentPopup(sessionData.id);
                        });
                    })

                    // Deal with this exception (Using core/notify exception function is recommended).
                    .catch(function (ex) {
                        displayException(ex);
                    });
            }
        },

        /**
         * Training sheet events initialization
         *
         * The element where the user clicks to open the training sheet
         * must contain the training id in a "data-training-id" attribute
         *
         * @param {string} elementClassToOpenSheet Element class where user click for open training sheet
         * @param {string} overlayId Overlay element id
         * @param {boolean} initSessionSheet Tells if the session sheet should be initialized or not
         * @param {string} currentElement The element in which the training sheets are created
         */
        initTrainingSheet: function (elementClassToOpenSheet, overlayId, initSessionSheet, currentElement, trainingsElement, sheetOpener) {

            var that = this;

            $('.' + elementClassToOpenSheet).on('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var trainingId = event.currentTarget.getAttribute('data-training-id');

                that.loadTrainingSheet(trainingId, currentElement, overlayId, initSessionSheet, trainingsElement, sheetOpener);
            });

            if (!this.eventLoaded) {
                $(document).keydown(function (e) {

                    // ESCAPE key pressed
                    if (e.keyCode == 27) {

                        var sessionSheet = $('.session-sheet.opened');
                        var trainingSheet = $('.training-sheet.opened');

                        if (sessionSheet.length > 0) {
                            sheet.closeSessionSheet(sessionSheet);
                        } else if (trainingSheet.length > 0) {
                            sheet.closeTrainingSheet(trainingSheet);
                        }

                    }
                });

                that.eventLoaded = true;
            }

        },

        initTrainingSheetCollapse: function () {
            // Collapse Manager
            $('.card-header').on('click', function (event) {
                var currentChildElement = event.currentTarget.children[0];
                var openingButton = $(currentChildElement).find('.accordion-opening');

                if (currentChildElement.classList.contains('collapsed')) {
                    openingButton.removeClass('close').addClass('open');

                    // Get training sheet id
                    var trainingSheetId = $(event.currentTarget).parent().parent().attr('id');
                    // Get all collapse training sheet
                    var allCollapse = $('#' + trainingSheetId + ' .card-header');
                    allCollapse.each(function (index, element) {
                        if (element !== event.currentTarget) {
                            $(element).find('.accordion-opening').removeClass('open').addClass('close');
                        }
                    })
                } else {
                    openingButton.removeClass('open').addClass('close');
                }
            });
        },

        initCopyLink: function () {
            $('.copy-training-link').on('click', function (e) {
                e.preventDefault();

                var copyLinkButton = e.currentTarget;

                var trainingId = $(this).data('trainingid');

                var link = M.cfg.wwwroot + '/catalog/' + trainingId;

                navigator.clipboard.writeText(link).then(function () {
                    mentor.dialog("<div>" + M.util.get_string('copylinktext', 'local_mentor_core') + "</div>", {
                        close: function () {
                            copyLinkButton.focus();
                        },
                        title: M.util.get_string('confirmation', 'admin'),
                        position: {my: "center", at: "top+20"},
                        buttons: [
                            {
                                text: "OK",
                                class: "btn btn-primary",
                                click: function () {
                                    $(this).dialog("close");
                                    copyLinkButton.focus();
                                }
                            }
                        ]
                    });

                }, function () {
                    // TODO : replace by a real popup.
                    alert(M.util.get_string('copylinkerror', 'local_mentor_core') + link);
                });
            });
        },

        /**
         * Session sheet events initialization
         *
         * The element where the user clicks to open the session sheet
         * must contain the session id in a "data-session-id" attribute
         *
         * @param {string} elementClassToOpenSheet Element class where user click for open training sheet
         * @param {string} overlayId Overlay element id
         */
        initSessionTiles: function (elementClassToOpenSheet, overlayId) {
            var that = this;

            $('.' + elementClassToOpenSheet).on('click', function (event) {
                event.preventDefault();
                var sessionSheet = $('#session-sheet-' + event.currentTarget.dataset.sessionId);

                var closeSessionSheet = $('.close-session-sheet, #' + overlayId);
                iframe.stopVideo(event.currentTarget.closest('.training-sheet'));

                sessionSheet.removeClass('closed');
                sessionSheet.addClass('opened');

                closeSessionSheet.on('click', function () {
                    that.closeSessionSheet(sessionSheet);
                });
            });
        },

        closeSessionSheet: function (sessionSheet) {
            sessionSheet.removeClass('opened');
            sessionSheet.addClass('closed');
        },
        /**
         * Open a training sheet
         *
         * @param {int} trainingId Id of the training
         * @param {string} currentElement The element in which the training sheets are created
         * @param {string} overlayId Overlay element id
         */
        openTrainingSheet: function (trainingId, currentElement, overlayId) {

            var trainingSheet = currentElement === '' ? $('#training-sheet-' + trainingId) : $(currentElement + ' #training-sheet-' + trainingId);

            // Check if the training sheet exists.
            if (trainingSheet.length == 0) {
                this.loadTrainingSheet(trainingId, currentElement, overlayId);
                return;
            }

            this.initCopyLink();

            this.initTrainingSheetCollapse();

            var overlay = $('#' + overlayId);
            var body = $('body');
            var that = this;

            trainingSheet.removeClass('closed');
            trainingSheet.addClass('opened');

            overlay.css('display', 'block');
            body.css('overflow', 'hidden');

            $('.close-training-sheet, #' + overlayId).on('click', function () {
                that.closeTrainingSheet(trainingSheet, overlay, body);
            });

            // Update page url.
            if ($('#page-local-catalog-index').length > 0) {
                window.history.pushState({}, null, M.cfg.wwwroot + '/local/catalog/index.php?trainingid=' + trainingId);
            }
        },
        closeTrainingSheet: function (trainingSheet) {

            var body = $('body');

            trainingSheet.removeClass('opened');
            trainingSheet.addClass('closed');
            iframe.stopVideo(trainingSheet[0]);

            $('#session-sheet-overlay, #training-sheet-overlay, #sheet-overlay').css('display', 'none');
            body.css('overflow', 'auto');

            // Update page url.
            if ($('#page-local-catalog-index').length > 0) {
                window.history.pushState({}, null, M.cfg.wwwroot + '/local/catalog/index.php');
            }
        }
    };

    // Add object to window to be called outside require.
    window.sheet = sheet;
    return sheet;
});
