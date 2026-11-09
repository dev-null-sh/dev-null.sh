/*
 * Sizes /dev/null commit embeds to their content. Each card posts its height; only messages from /dev/null's origin
 * are trusted, and only the frame that sent one is resized.
 */
(function () {
    if (window.devNullEmbeds) {
        return;
    }

    window.devNullEmbeds = true;

    var origin = new URL(document.currentScript.src).origin;

    window.addEventListener('message', function (event) {
        var data = event.data;

        if (event.origin !== origin || !data || data.type !== 'devnull:embed:resize' || typeof data.height !== 'number') {
            return;
        }

        var frames = document.querySelectorAll('iframe[src^="' + origin + '/embed/"]');

        for (var i = 0; i < frames.length; i++) {
            if (frames[i].contentWindow === event.source) {
                frames[i].style.height = Math.ceil(data.height) + 'px';
            }
        }
    });
})();
