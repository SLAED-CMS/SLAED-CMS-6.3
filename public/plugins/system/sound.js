(function () {
    'use strict';
    var soundGap = 2500;
    var soundVolume = 0.6;
    var soundZones = { success: 'alert', error: 'alert', warn: 'alert', queue: 'mod', win: 'win', inbox: 'msg', send: 'msg' };
    var soundDefault = { alert: true, msg: true, win: true, mod: false };
    var soundBase = document.documentElement.getAttribute('data-sl-sound') || '';
    var soundWoke = false;
    var soundLast = {};
    var soundFiles = {};
    // Д4 The switch of this device, on until it is turned off
    function isSoundOn() {
        return window.getToggleState('sound', '') !== '0';
    }
    // Д10 The zones of this device chosen in the profile, the defaults where none was chosen
    function getSoundZones() {
        var kept = {};
        try {
            kept = JSON.parse(window.getToggleState('sound-zones', '') || '{}') || {};
        } catch (err) {
            kept = {};
        }
        return Object.assign({}, soundDefault, kept);
    }
    // Д4 One voice of the theme for an action or an event: silent before the first touch, off or out of its zone, one of a category per gap
    function setSoundPlay(voice) {
        var zone = soundZones[voice];
        var key, now, one;
        if (!soundBase || !zone || !soundWoke || !isSoundOn() || !getSoundZones()[zone]) return;
        key = zone === 'alert' ? voice : zone;
        now = Date.now();
        if (soundLast[key] && now - soundLast[key] < soundGap) return;
        soundLast[key] = now;
        one = soundFiles[voice] || (soundFiles[voice] = new Audio(soundBase + voice + '.mp3'));
        one.volume = soundVolume * ((voice === 'inbox' || voice === 'queue') && !document.hidden && document.hasFocus() ? 0.4 : 1);
        one.currentTime = 0;
        one.play().catch(function () {});
    }
    // Д4 The row of Options and the boxes of the profile show what this device keeps
    function setSoundView() {
        var zones = getSoundZones();
        document.querySelectorAll('#sl-set-sound').forEach(function (pick) { pick.value = isSoundOn() ? '1' : '0'; });
        document.querySelectorAll('[data-sl-sound-zone]').forEach(function (box) { box.checked = !!zones[box.getAttribute('data-sl-sound-zone')]; });
    }
    // Д4 The switch of Options: turned on it answers with the success tone
    function setSoundOn(on) {
        window.setToggleState('sound', '', on ? '1' : '0');
        setSoundView();
        if (on) setSoundPlay('success');
    }
    // The browser gives the sound only after a touch of the page
    ['pointerdown', 'keydown'].forEach(function (name) {
        document.addEventListener(name, function () { soundWoke = true; }, { passive: true, once: true });
    });
    // Answer 15: a zone box of the profile is kept at once on this device and never sent
    document.addEventListener('change', function (event) {
        var box = event.target;
        var zones;
        if (!box.matches || !box.matches('[data-sl-sound-zone]')) return;
        zones = getSoundZones();
        zones[box.getAttribute('data-sl-sound-zone')] = box.checked;
        window.setToggleState('sound-zones', '', JSON.stringify(zones));
    });
    // An answer of the server names its sound by the event sl-sound of HX-Trigger
    document.addEventListener('sl-sound', function (event) {
        if (event.detail && typeof event.detail.value === 'string') setSoundPlay(event.detail.value);
    });
    // A choice made in another tab reaches this page without a reload
    window.addEventListener('storage', function (event) {
        if (event.key === null || event.key.indexOf('slaed-toggle:sound') === 0) setSoundView();
    });
    window.setSoundPlay = setSoundPlay;
    window.setSoundOn = setSoundOn;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setSoundView);
    else setSoundView();
})();
