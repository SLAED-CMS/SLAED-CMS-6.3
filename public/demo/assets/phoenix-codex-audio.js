(function () {
    'use strict';
    const beat = 60 / 128;
    const length = beat * 64;
    const chords = [[50, 53, 57], [46, 50, 53], [53, 57, 60], [48, 52, 55]];
    const motif = [0, 2, 1, 3, 2, 1, 4, 0, 0, 1, 2, 5, 2, 3, 1, 4];

    /* Convert a musical note number into an oscillator frequency */
    function getNotePitch(note) {
        return 440 * 2 ** ((note - 69) / 12);
    }

    /* Synthesize a voiced note with a smooth envelope and optional detuned layers */
    function addScoreNote(ctx, bus, note, when, span, level, voice, echo) {
        const gain = ctx.createGain();
        const filter = ctx.createBiquadFilter();
        const pads = voice === 'pad';
        const attack = pads ? 0.38 : voice === 'lead' ? 0.014 : 0.004;
        const release = pads ? 0.9 : voice === 'lead' ? 0.22 : 0.11;
        gain.gain.setValueAtTime(0, when);
        gain.gain.linearRampToValueAtTime(level, when + attack);
        gain.gain.setValueAtTime(level * (pads ? 0.82 : 0.62), when + Math.max(attack, span * 0.65));
        gain.gain.exponentialRampToValueAtTime(0.0001, when + span + release);
        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(voice === 'bass' ? 480 : pads ? 1800 : voice === 'lead' ? 5200 : 3400, when);
        if (voice === 'pluck') filter.frequency.exponentialRampToValueAtTime(380, when + span);
        filter.Q.value = voice === 'pluck' ? 1.2 : 0.55;
        gain.connect(filter).connect(bus);
        if (echo && voice !== 'bass') filter.connect(echo);
        const spread = voice === 'lead' ? [-9, 0, 9] : pads ? [-5, 5] : [0];
        spread.forEach((tune) => {
            const osc = ctx.createOscillator();
            const mix = ctx.createGain();
            osc.type = voice === 'bass' ? 'sawtooth' : voice === 'pluck' ? 'triangle' : 'sawtooth';
            osc.frequency.value = getNotePitch(note);
            osc.detune.value = tune;
            mix.gain.value = 1 / spread.length;
            osc.connect(mix).connect(gain);
            osc.start(when);
            osc.stop(when + span + release + 0.04);
        });
        if (voice === 'bass') {
            const sub = ctx.createOscillator();
            const mix = ctx.createGain();
            sub.type = 'sine';
            sub.frequency.value = getNotePitch(note);
            mix.gain.value = 0.7;
            sub.connect(mix).connect(gain);
            sub.start(when);
            sub.stop(when + span + release);
        }
    }

    /* Generate reusable filtered noise for percussion, sweeps and room reflections */
    function getNoiseBuffer(ctx, span, decay = false) {
        const buffer = ctx.createBuffer(2, Math.ceil(ctx.sampleRate * span), ctx.sampleRate);
        for (let chan = 0; chan < 2; chan++) {
            const data = buffer.getChannelData(chan);
            let prev = 0;
            for (let i = 0; i < data.length; i++) {
                const rand = Math.sin((i + chan * 971) * 127.1) * 43758.5453;
                const noise = (rand - Math.floor(rand)) * 2 - 1;
                prev = prev * 0.38 + noise * 0.62;
                data[i] = prev * (decay ? Math.exp(-i / data.length * 7) : 1);
            }
        }
        return buffer;
    }

    /* Add punchy synthesized drums without downloading any audio samples */
    function addScoreDrum(ctx, bus, noise, when, kind, level) {
        const gain = ctx.createGain();
        gain.connect(bus);
        gain.gain.setValueAtTime(0, when);
        gain.gain.linearRampToValueAtTime(level, when + 0.002);
        const span = kind === 'kick' ? 0.3 : kind === 'snare' ? 0.17 : kind === 'open' ? 0.19 : 0.055;
        gain.gain.exponentialRampToValueAtTime(0.0001, when + span);
        if (kind === 'kick') {
            const osc = ctx.createOscillator();
            osc.frequency.setValueAtTime(180, when);
            osc.frequency.exponentialRampToValueAtTime(49, when + 0.08);
            osc.frequency.exponentialRampToValueAtTime(37, when + span);
            osc.connect(gain);
            osc.start(when);
            osc.stop(when + span + 0.02);
            return;
        }
        const source = ctx.createBufferSource();
        const filter = ctx.createBiquadFilter();
        source.buffer = noise;
        filter.type = kind === 'snare' ? 'bandpass' : 'highpass';
        filter.frequency.value = kind === 'snare' ? 1900 : 7800;
        filter.Q.value = kind === 'snare' ? 0.6 : 0.3;
        source.connect(filter).connect(gain);
        source.start(when, (when * 0.71) % 0.7, span + 0.01);
        if (kind === 'snare') {
            const osc = ctx.createOscillator();
            const mix = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(185, when);
            osc.frequency.exponentialRampToValueAtTime(115, when + 0.12);
            mix.gain.value = 0.32;
            osc.connect(mix).connect(gain);
            osc.start(when);
            osc.stop(when + span);
        }
    }

    /* Raise a filtered rush into the phoenix reveal and settle it into a cymbal wash */
    function addScoreSweep(ctx, bus, noise, when, span, rise) {
        const source = ctx.createBufferSource();
        const filter = ctx.createBiquadFilter();
        const gain = ctx.createGain();
        source.buffer = noise;
        source.loop = true;
        filter.type = 'bandpass';
        filter.Q.value = 0.7;
        filter.frequency.setValueAtTime(rise ? 340 : 8400, when);
        filter.frequency.exponentialRampToValueAtTime(rise ? 8400 : 850, when + span);
        gain.gain.setValueAtTime(rise ? 0.0001 : 0.11, when);
        if (rise) {
            gain.gain.exponentialRampToValueAtTime(0.13, when + span - 0.05);
            gain.gain.linearRampToValueAtTime(0, when + span);
        } else gain.gain.exponentialRampToValueAtTime(0.0001, when + span);
        source.connect(filter).connect(gain).connect(bus);
        source.start(when);
        source.stop(when + span + 0.02);
    }

    /* Compose an original sixteen-bar electronic anthem at 128 BPM */
    async function getScoreRender() {
        const offline = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        if (!offline) return null;
        const ctx = new offline(2, Math.round(length * 44100), 44100);
        const bus = ctx.createGain();
        const comp = ctx.createDynamicsCompressor();
        const master = ctx.createGain();
        const room = ctx.createConvolver();
        const wet = ctx.createGain();
        const delay = ctx.createDelay(1);
        const echo = ctx.createGain();
        const noise = getNoiseBuffer(ctx, 2);
        bus.gain.value = 0.82;
        comp.threshold.value = -19;
        comp.knee.value = 18;
        comp.ratio.value = 5;
        comp.attack.value = 0.003;
        comp.release.value = 0.11;
        master.gain.setValueAtTime(0.94, 0);
        master.gain.setValueAtTime(0.94, 28.6);
        master.gain.linearRampToValueAtTime(0, length);
        bus.connect(comp).connect(master).connect(ctx.destination);
        room.buffer = getNoiseBuffer(ctx, 2.4, true);
        wet.gain.value = 0.12;
        bus.connect(room).connect(wet).connect(comp);
        delay.delayTime.value = beat * 0.75;
        echo.gain.value = 0.16;
        delay.connect(echo).connect(comp);
        for (let bar = 0; bar < 16; bar++) {
            const when = bar * beat * 4;
            const chord = bar === 15 ? chords[0] : chords[(bar >= 10 ? bar - 10 : bar) % 4];
            chord.forEach((note) => addScoreNote(ctx, bus, note + 12, when, beat * 3.5, bar < 4 ? 0.034 : 0.054, 'pad', delay));
            if (bar < 15) {
                const steps = bar < 4 ? 8 : 16;
                for (let i = 0; i < steps; i++) {
                    const note = chord[[0, 2, 1, 2][i % 4]] + 12 + (i % 8 === 7 ? 12 : 0);
                    addScoreNote(ctx, bus, note, when + i * beat * 4 / steps, beat * 0.3, bar < 4 ? 0.055 : 0.067, 'pluck', delay);
                }
            }
            if (bar >= 4 && bar < 15) {
                for (let i = 0; i < 8; i++) {
                    addScoreNote(ctx, bus, chord[0] - 12, when + i * beat * 0.5, beat * 0.32, bar >= 10 ? 0.28 : 0.17, 'bass');
                }
            }
            if (bar >= 10 && bar < 15) {
                const notes = [chord[0] + 24, chord[1] + 24, chord[2] + 24, chord[0] + 29, chord[0] + 26, chord[0] + 36];
                const phrase = bar % 2 === 0 ? motif.slice(0, 8) : motif.slice(8);
                phrase.forEach((part, index) => {
                    addScoreNote(ctx, bus, notes[part], when + index * beat * 0.5, beat * 0.44, index % 4 === 0 ? 0.14 : 0.105, 'lead', delay);
                });
            }
            if (bar < 4) {
                if (bar % 2 === 0) addScoreDrum(ctx, bus, noise, when, 'kick', 0.28);
                continue;
            }
            if (bar >= 15) continue;
            for (let i = 0; i < 4; i++) {
                if (bar >= 10 || i % 2 === 0) addScoreDrum(ctx, bus, noise, when + i * beat, 'kick', bar >= 10 ? 0.88 : 0.5);
                if (i % 2 === 1) addScoreDrum(ctx, bus, noise, when + i * beat, 'snare', bar >= 10 ? 0.43 : 0.24);
            }
            for (let i = 0; i < 8; i++) {
                addScoreDrum(ctx, bus, noise, when + i * beat * 0.5, i % 2 ? 'open' : 'hat', i % 2 ? 0.055 : 0.035);
            }
            if (bar === 8 || bar === 9) {
                const steps = bar === 9 ? 16 : 8;
                for (let i = 0; i < steps; i++) {
                    addScoreDrum(ctx, bus, noise, when + i * beat * 4 / steps, 'snare', 0.08 + i / steps * 0.15);
                }
            }
        }
        addScoreSweep(ctx, bus, noise, beat * 24, beat * 16, true);
        addScoreSweep(ctx, bus, noise, beat * 40, 2.8, false);
        addScoreDrum(ctx, bus, noise, beat * 40, 'kick', 1.1);
        addScoreSweep(ctx, bus, noise, beat * 52, 1.2, false);
        [62, 65, 69, 74].forEach((note) => addScoreNote(ctx, bus, note, beat * 60, 1.4, 0.085, 'pad', delay));
        const buffer = await ctx.startRendering();
        let peak = 0;
        for (let chan = 0; chan < 2; chan++) {
            const data = buffer.getChannelData(chan);
            for (let i = 0; i < data.length; i++) peak = Math.max(peak, Math.abs(data[i]));
        }
        if (peak > 0) {
            const level = 0.96 / peak;
            for (let chan = 0; chan < 2; chan++) {
                const data = buffer.getChannelData(chan);
                for (let i = 0; i < data.length; i++) data[i] *= level;
            }
        }
        return buffer;
    }

    /* Keep an original musical score seekable and synchronized with the presentation clock */
    class PhoenixScore {
        constructor() {
            this.audio = null;
            this.source = null;
            this.level = 0.7;
            this.offset = 0;
            this.ready = getScoreRender().then((buffer) => { this.buffer = buffer; return Boolean(buffer); });
        }

        /* Start the rendered track only after a user gesture has enabled sound */
        async setPlay(offset) {
            const ready = await this.ready;
            if (!ready) return false;
            const audioctx = window.AudioContext || window.webkitAudioContext;
            if (!this.audio) {
                this.audio = new audioctx();
                this.gain = this.audio.createGain();
                this.gain.connect(this.audio.destination);
            }
            await this.audio.resume();
            this.setStop();
            if (offset >= length) return false;
            this.offset = Math.max(0, offset);
            this.source = this.audio.createBufferSource();
            this.source.buffer = this.buffer;
            this.source.connect(this.gain);
            this.gain.gain.setValueAtTime(this.level, this.audio.currentTime);
            this.started = this.audio.currentTime;
            this.source.start(0, this.offset);
            return true;
        }

        /* Release the current playback source while retaining the rendered composition */
        setStop() {
            if (!this.source) return;
            this.source.stop();
            this.source.disconnect();
            this.source = null;
        }

        /* Use the audio device clock to keep musical impacts and visual beats aligned */
        getClock() {
            if (!this.source || !this.audio) return null;
            return this.offset + this.audio.currentTime - this.started;
        }

        /* Apply a smooth volume change without rebuilding the score */
        setVolume(level) {
            this.level = Math.max(0, Math.min(1, level));
            if (this.audio) this.gain.gain.setTargetAtTime(this.level, this.audio.currentTime, 0.04);
        }

        /* Export the complete original score as a standard stereo PCM WAV */
        getWave() {
            if (!this.buffer) return null;
            const buffer = this.buffer;
            const frames = buffer.length;
            const bytes = new ArrayBuffer(44 + frames * 4);
            const view = new DataView(bytes);
            const names = [[0, 'RIFF'], [8, 'WAVE'], [12, 'fmt '], [36, 'data']];
            names.forEach(([off, name]) => { for (let i = 0; i < name.length; i++) view.setUint8(off + i, name.charCodeAt(i)); });
            view.setUint32(4, bytes.byteLength - 8, true);
            view.setUint32(16, 16, true);
            view.setUint16(20, 1, true);
            view.setUint16(22, 2, true);
            view.setUint32(24, buffer.sampleRate, true);
            view.setUint32(28, buffer.sampleRate * 4, true);
            view.setUint16(32, 4, true);
            view.setUint16(34, 16, true);
            view.setUint32(40, frames * 4, true);
            const left = buffer.getChannelData(0);
            const right = buffer.getChannelData(1);
            for (let i = 0; i < frames; i++) {
                const aa = Math.max(-1, Math.min(1, left[i]));
                const bb = Math.max(-1, Math.min(1, right[i]));
                view.setInt16(44 + i * 4, aa < 0 ? aa * 32768 : aa * 32767, true);
                view.setInt16(46 + i * 4, bb < 0 ? bb * 32768 : bb * 32767, true);
            }
            return bytes;
        }
    }
    window.PhoenixScore = PhoenixScore;
})();
