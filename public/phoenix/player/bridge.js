(function () {
    'use strict';
    const embed = parent !== window && new URLSearchParams(location.search).get('embed') === '1';

    /* Reuse the audio context enabled by the launch button on the current page */
    function getHostAudio() {
        if (!embed) return null;
        try { return parent.SlaedPhoenix ? parent.SlaedPhoenix.getAudioContext() : null; }
        catch (error) { return null; }
    }

    /* Notify only the same-origin host that owns this presentation frame */
    function setHostEvent(name, data = {}) {
        if (embed) parent.postMessage({...data, channel: 'slaed-phoenix', event: name}, location.origin);
    }

    window.PhoenixBridge = Object.freeze({embedded: embed, getAudio: getHostAudio, setEvent: setHostEvent});
})();
