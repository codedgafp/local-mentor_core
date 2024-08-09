/**
 * Javascript pagination
 *
 * Add the html below to paginate
 *  <div>
 *      <nav aria-label="Page navigation example">
 *          <ul class="mentor-pagination"></ul>
 *      </nav>
 *  </div>
 */

define([
    'jquery'
], function ($) {

    var pagination = {
        /**
         * Init the pagination
         * @param {object} elements Elements to paginate
         * @param {object} paginationBloc
         * @param {int} resultPerPage
         * @param {boolean} hidePagination hide the pagination if it is not needed
         * @param {function} callback
         */
        initPagination: function (elements, paginationBloc, resultPerPage, hidePagination, callback) {
            if (typeof resultPerPage === 'undefined') {
                resultPerPage = 12;
            }

            var that = this;

            // Pages number.
            var pagesNumber = Math.floor(elements.length / resultPerPage);

            if (pagesNumber !== 1 || elements.length / resultPerPage > 1) {
                pagesNumber++;
            }

            // Get page number from URL.
            var pageToDisplay = 1;
            var pageParam = this.getUrlParameter('page');
            if (false !== pageParam && pageParam <= pagesNumber) {
                pageToDisplay = parseInt(pageParam);
            }

            // Display the navigation bloc.
            paginationBloc.html('');

            if (pagesNumber < 2 && hidePagination) {
                paginationBloc.hide();
            }

            var previousDisable = (pageToDisplay === 1) ? 'disabled' : '';
            var nextDisable = (pageToDisplay === pagesNumber) ? 'disabled' : '';

            paginationBloc.append('<li class="page-item '
                + previousDisable
                + '"><a class="page-link fr-pagination__link" data-page="previous" tabindex="0">'
                + M.str.local_mentor_core.previous
                + '</a></li>');

            // Apply different style on the selected page.
            var rangeWithDots = this.rangeWithDots(pageToDisplay, pagesNumber);

            rangeWithDots.forEach(function (range) {
                if (range === '...') {
                    paginationBloc.append('<li class="page-item"><span class="dots">' + range + '</span></li>');
                } else {
                    var selectedClass = (range == pageToDisplay) ? 'selected-page' : '';
                    paginationBloc.append('<li class="page-item"><a class="page-link fr-pagination__link '
                        + selectedClass
                        + '" data-page="' + range + '" tabindex="0">' + range + '</a></li>');


                    $('.page-link.selected-page').attr('aria-current', 'page');
                }
            });

            paginationBloc.append('<li class="page-item '
                + nextDisable
                + '"><a class="page-link fr-pagination__link" data-page="next" tabindex="0"> '
                + M.str.local_mentor_core.next
                + '</a></li>');

            // Display the current page.
            this.displayPage(elements, pageToDisplay, resultPerPage);

            // Callback function when page is displayed.
            if (typeof callback !== 'undefined') {
                callback();
            }

            $('.page-link', paginationBloc).on('keypress', function () {
                var keycode = (event.keyCode ? event.keyCode : event.which);
                if (keycode === 13) {
                    that.pageSelected($(this), elements, pageToDisplay, pagesNumber, paginationBloc, resultPerPage, hidePagination, callback);
                }
            });

            // Display the selected page.
            $('.page-link', paginationBloc).on('click', function () {
                that.pageSelected($(this), elements, pageToDisplay, pagesNumber, paginationBloc, resultPerPage, hidePagination, callback);
            });
        },

        pageSelected: function (item, elements, pageToDisplay, pagesNumber, paginationBloc, resultPerPage, hidePagination, callback) {

            var that = this;

            var selectedPage = item.attr("data-page");

            // Handle previous and next buttons.
            if (selectedPage === "next") {
                selectedPage = parseInt(pageToDisplay) + 1;
            }

            if (selectedPage === "previous") {
                selectedPage = parseInt(pageToDisplay) - 1;
            }

            $('.page-link[data-page=' + pageToDisplay + ']', paginationBloc).removeClass('selected-page');
            $('.page-link[data-page=' + selectedPage + ']', paginationBloc).addClass('selected-page');

            pageToDisplay = selectedPage;

            // Disable previous or next buttons if needed.
            if (pageToDisplay == 1) {
                $('.page-link[data-page=previous]', paginationBloc).parent().addClass('disabled');
            } else {
                $('.page-link[data-page=previous]', paginationBloc).parent().removeClass('disabled');
            }

            if (pageToDisplay == pagesNumber) {
                $('.page-link[data-page=next]', paginationBloc).parent().addClass('disabled');
            } else {
                $('.page-link[data-page=next]', paginationBloc).parent().removeClass('disabled');
            }

            // Replace URL parameter with the selected page.
            window.history.replaceState(null, null, "?page=" + selectedPage);

            // Display the selected page.
            that.initPagination(elements, paginationBloc, resultPerPage, hidePagination, callback);

            // Go to the top of the block.
            $(window).scrollTop($(paginationBloc).closest('.card-body').find('h2').offset().top - 102)
        },

        /**
         *
         * @param {int} c
         * @param {int} m
         * @returns {[]}
         */
        rangeWithDots: function (c, m) {
            var current = c,
                last = m,
                delta = 2,
                left = current - delta,
                right = current + delta + 1,
                range = [],
                rangeWithDots = [],
                l;

            for (var i = 1; i <= last; i++) {
                if (i == 1 || i == last || i >= left && i < right) {
                    range.push(i);
                }
            }

            for (var i in range) {
                if (l) {
                    if (range[i] - l === 2) {
                        rangeWithDots.push(l + 1);
                    } else if (range[i] - l !== 1) {
                        rangeWithDots.push('...');
                    }
                }
                rangeWithDots.push(range[i]);
                l = range[i];
            }

            return rangeWithDots;
        },

        /**
         * Display the selected page
         * @param {object} elements
         * @param {int} pageToDisplay
         * @param {int} resultPerPage
         */
        displayPage: function (elements, pageToDisplay, resultPerPage) {
            var firstElementFocused = false;

            elements.each(function (key, element) {
                if (key + 1 > (pageToDisplay * resultPerPage - resultPerPage)
                    && key + 1 <= (pageToDisplay * resultPerPage)
                ) {
                    $(this).show();

                    // Set focus on first element.
                    if (firstElementFocused) {
                        $(this).focus();
                        firstElementFocused = false;
                    }

                    // Open the session sheet on enter pressed.

                    $(this).keypress(function (event) {
                        event.preventDefault();

                        if ($(this).is(':focus') && event.keyCode == 13) {
                            $(this).click();
                        }
                    });

                } else {
                    $(this).hide();
                }
            });
        },

        /**
         * Returns the requested URL parameter, false if not found
         * @param {string} param
         * @returns {string|boolean}
         */
        getUrlParameter: function (param) {
            var sPageURL = window.location.search.substring(1);
            var sURLVariables = sPageURL.split('&');
            for (var i = 0; i < sURLVariables.length; i++) {
                var sParameterName = sURLVariables[i].split('=');
                if (sParameterName[0] === param) {
                    return sParameterName[1];
                }
            }
            return false;
        }
    };

    return pagination;
});
