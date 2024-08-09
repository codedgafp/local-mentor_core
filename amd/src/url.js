/**
 * Javascript url management
 */

define([], function () {

    var url = {
        /**
         * Get url parameter value
         *
         * @param sParam
         * @returns {boolean|string|boolean}
         */
        getParam: function getParam(sParam) {
            var sPageURL = window.location.search.substring(1),
                sURLVariables = sPageURL.split('&'),
                sParameterName,
                i;

            for (i = 0; i < sURLVariables.length; i++) {
                sParameterName = sURLVariables[i].split('=');

                if (sParameterName[0] === sParam) {
                    return typeof sParameterName[1] == 'undefined' ? true : decodeURIComponent(sParameterName[1]);
                }
            }
            return false;
        },
        /**
         * Remove url parameter
         *
         * @param parameter
         * @returns {string}
         */
        removeParam: function (parameter) {
            var url = document.location.href;
            var urlparts = url.split('?');

            if (urlparts.length >= 2) {
                var urlBase = urlparts.shift();
                var queryString = urlparts.join("?");

                var prefix = encodeURIComponent(parameter) + '=';
                var pars = queryString.split(/[&;]/g);
                for (var i = pars.length; i-- > 0;)
                    if (pars[i].lastIndexOf(prefix, 0) !== -1)
                        pars.splice(i, 1);
                url = urlBase + '?' + pars.join('&');
                window.history.pushState('', document.title, url); // added this line to push the new url directly to url bar .

            }
            return url;
        },
    };

    // Add object to window to be called outside require.
    window.url = url;
    return url;
});
