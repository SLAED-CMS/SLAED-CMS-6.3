(function () {
    'use strict';
    const anchors = [
        [0, 0], [7.5, 13.16], [22.5, 29.749], [24.4, 35.8], [30, 40.2], [33.75, 44.368],
        [34.6, 46], [38.8, 61.3], [40.8, 66.449], [42.15, 70.289], [43.95, 74.7], [45, 80.7], [46, 83.352]
    ];
    /* Map every visual phase to the edited anthem while retaining the audio clock */
    function getSceneTime(time) {
        for (let i = 1; i < anchors.length; i++) {
            if (time > anchors[i][1]) continue;
            const from = anchors[i - 1];
            const unto = anchors[i];
            return from[0] + (unto[0] - from[0]) * Math.max(0, (time - from[1]) / (unto[1] - from[1]));
        }
        return 46;
    }
    window.PhoenixTimeline = Object.freeze({
        length: 83.352, bpm: 129.2, dna: 13.16, continuity: 29.749, rewrite: 35.8, assembly: 40.2,
        drop: 44.368, motto: 70.289, fade: 74.7, site: 80.7,
        track: 'audio/theme.mp3?v=1', getSceneTime
    });
})();
