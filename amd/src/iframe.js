/**
 * Javascript iframe
 */

define([], function () {
    return {
        /**
         * Stop all videos in an iframe contained in the element
         *
         * @param element
         */
        stopVideo: function (element) {
            var iframe = element.querySelector('iframe');
            var video = element.querySelector('video');
            if (iframe) {
                var iframeSrc = iframe.src;
                iframe.src = iframeSrc;
            }
            if (video) {
                video.pause();
            }
        }
    }
});
