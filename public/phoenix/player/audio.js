(function () {
    'use strict';
    const timing = window.PhoenixTimeline;
    const beat = 60 / timing.bpm;
    const length = timing.length;
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
        const release = pads ? 0.9 : voice === 'lead' ? 0.22 : voice === 'bass' ? 0.07 : 0.11;
        gain.gain.setValueAtTime(0, when);
        gain.gain.linearRampToValueAtTime(level, when + attack);
        gain.gain.setValueAtTime(level * (pads ? 0.82 : 0.62), when + Math.max(attack, span * 0.65));
        gain.gain.exponentialRampToValueAtTime(0.0001, when + span + release);
        filter.type = 'lowpass';
        filter.frequency.setValueAtTime(voice === 'bass' ? 580 : pads ? 1800 : voice === 'lead' ? 6400 : 3400, when);
        if (voice === 'pluck') filter.frequency.exponentialRampToValueAtTime(380, when + span);
        filter.Q.value = voice === 'pluck' ? 1.2 : 0.55;
        gain.connect(filter);
        if (voice === 'bass') {
            const shape = ctx.createWaveShaper();
            const curve = new Float32Array(257);
            for (let i = 0; i < curve.length; i++) curve[i] = Math.tanh((i / 128 - 1) * 2.2) / Math.tanh(2.2);
            shape.curve = curve;
            shape.oversample = '2x';
            filter.connect(shape).connect(bus);
        } else filter.connect(bus);
        if (echo && voice !== 'bass') filter.connect(echo);
        const spread = voice === 'lead' ? [-14, -6, 0, 6, 14] : pads ? [-5, 5] : [0];
        spread.forEach((tune) => {
            const osc = ctx.createOscillator();
            const mix = ctx.createGain();
            osc.type = voice === 'bass' ? 'sawtooth' : voice === 'pluck' ? 'triangle' : 'sawtooth';
            osc.frequency.value = getNotePitch(note + (voice === 'bass' ? 12 : 0));
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
            mix.gain.value = 1.15;
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
        const low = kind === 'kick' || kind === 'boom';
        const span = kind === 'boom' ? 0.8 : kind === 'kick' ? 0.38 : kind === 'snare' ? 0.19 : kind === 'open' ? 0.19 : 0.045;
        gain.gain.exponentialRampToValueAtTime(0.0001, when + span);
        if (low) {
            const osc = ctx.createOscillator();
            const harm = ctx.createOscillator();
            const layer = ctx.createGain();
            osc.frequency.setValueAtTime(kind === 'boom' ? 115 : 205, when);
            osc.frequency.exponentialRampToValueAtTime(52, when + 0.045);
            osc.frequency.exponentialRampToValueAtTime(kind === 'boom' ? 32 : 39, when + span);
            harm.type = 'triangle';
            harm.frequency.setValueAtTime(145, when);
            harm.frequency.exponentialRampToValueAtTime(76, when + 0.09);
            layer.gain.value = 0.13;
            harm.connect(layer).connect(gain);
            osc.connect(gain);
            osc.start(when);
            harm.start(when);
            osc.stop(when + span + 0.02);
            harm.stop(when + 0.14);
            const snap = ctx.createBufferSource();
            const edge = ctx.createBiquadFilter();
            const click = ctx.createGain();
            snap.buffer = noise;
            edge.type = 'highpass';
            edge.frequency.value = 2400;
            click.gain.value = 0.11;
            snap.connect(edge).connect(click).connect(gain);
            snap.start(when, 0.3, 0.014);
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

    /* Compose a bass-driven twenty-four-bar anthem with hip-hop drums and a trance drop */
    async function getScoreRender() {
        const offline = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        if (!offline) return null;
        const ctx = new offline(2, Math.round(length * 44100), 44100);
        const bus = ctx.createGain();
        const comp = ctx.createDynamicsCompressor();
        const shelf = ctx.createBiquadFilter();
        const master = ctx.createGain();
        const room = ctx.createConvolver();
        const wet = ctx.createGain();
        const delay = ctx.createDelay(1);
        const echo = ctx.createGain();
        const noise = getNoiseBuffer(ctx, 2);
        bus.gain.value = 0.82;
        comp.threshold.value = -17;
        comp.knee.value = 18;
        comp.ratio.value = 4;
        comp.attack.value = 0.003;
        comp.release.value = 0.11;
        master.gain.setValueAtTime(0.94, 0);
        master.gain.setValueAtTime(0.94, timing.fade);
        master.gain.linearRampToValueAtTime(0, length);
        shelf.type = 'lowshelf';
        shelf.frequency.value = 105;
        shelf.gain.value = 3.5;
        bus.connect(shelf).connect(comp).connect(master).connect(ctx.destination);
        room.buffer = getNoiseBuffer(ctx, 2.4, true);
        wet.gain.value = 0.075;
        bus.connect(room).connect(wet).connect(comp);
        delay.delayTime.value = beat * 0.75;
        echo.gain.value = 0.16;
        delay.connect(echo).connect(comp);
        const bars = Math.ceil(length / (beat * 4));
        for (let bar = 0; bar < bars; bar++) {
            const when = bar * beat * 4;
            const drop = when >= timing.drop && when < timing.fade - 2;
            const dance = when >= timing.dna && when < timing.rewrite || drop;
            const quiet = when >= timing.fade;
            const chord = quiet ? chords[0] : chords[bar % 4];
            chord.forEach((note) => addScoreNote(ctx, bus, note + 12, when, beat * 3.5, drop ? 0.065 : 0.045, 'pad', delay));
            if (!quiet) {
                const steps = bar < 4 ? 8 : 16;
                for (let i = 0; i < steps; i++) {
                    const note = chord[[0, 2, 1, 2][i % 4]] + 12 + (i % 8 === 7 ? 12 : 0);
                    addScoreNote(ctx, bus, note, when + i * beat * 4 / steps, beat * 0.3, drop ? 0.065 : 0.05, 'pluck', delay);
                }
            }
            if (!quiet) {
                const steps = dance ? [0.52, 1.52, 2.52, 3.52] : [0.05, 0.78, 1.5, 2.05, 2.78, 3.5];
                steps.forEach((step, index) => {
                    const note = chord[0] - 24 + (index === steps.length - 1 && bar % 4 === 3 ? 7 : 0);
                    addScoreNote(ctx, bus, note, when + step * beat, beat * (dance ? 0.36 : 0.42), drop ? 0.43 : 0.3, 'bass');
                });
            }
            if (drop) {
                const notes = [chord[0] + 24, chord[1] + 24, chord[2] + 24, chord[0] + 29, chord[0] + 26, chord[0] + 36];
                const phrase = bar % 2 === 0 ? motif.slice(0, 8) : motif.slice(8);
                phrase.forEach((part, index) => {
                    addScoreNote(ctx, bus, notes[part], when + index * beat * 0.5, beat * 0.44, index % 4 === 0 ? 0.19 : 0.145, 'lead', delay);
                });
            }
            if (quiet) continue;
            const kicks = dance ? [0, 1, 2, 3] : [0, 1.75, 2.5, 3.25];
            kicks.forEach((step) => addScoreDrum(ctx, bus, noise, when + step * beat, 'kick', drop ? 1.06 : 0.8));
            const snares = dance ? [1, 3] : [2];
            snares.forEach((step) => {
                addScoreDrum(ctx, bus, noise, when + step * beat, 'snare', drop ? 0.51 : 0.37);
                addScoreDrum(ctx, bus, noise, when + step * beat + 0.014, 'snare', 0.1);
            });
            if (bar < 4 || bar >= 13 && bar < 18) {
                [3, 3.1667, 3.3333].forEach((step) => addScoreDrum(ctx, bus, noise, when + step * beat, 'hat', 0.048));
            }
            for (let i = 0; i < 8; i++) {
                addScoreDrum(ctx, bus, noise, when + i * beat * 0.5, i % 2 ? 'open' : 'hat', i % 2 ? 0.055 : 0.035);
            }
            if (when >= timing.assembly && when < timing.drop) {
                const steps = when > timing.drop - beat * 4 ? 16 : 8;
                for (let i = 0; i < steps; i++) {
                    addScoreDrum(ctx, bus, noise, when + i * beat * 4 / steps, 'snare', 0.08 + i / steps * 0.15);
                }
            }
        }
        addScoreSweep(ctx, bus, noise, 0, 1.6, false);
        addScoreDrum(ctx, bus, noise, 0, 'boom', 0.7);
        addScoreSweep(ctx, bus, noise, beat * 16, 0.65, false);
        addScoreSweep(ctx, bus, noise, timing.drop - beat * 8, beat * 8, true);
        addScoreSweep(ctx, bus, noise, timing.drop, 2.8, false);
        addScoreDrum(ctx, bus, noise, timing.drop, 'boom', 1.18);
        addScoreDrum(ctx, bus, noise, timing.motto, 'boom', 0.82);
        [62, 65, 69, 74].forEach((note) => addScoreNote(ctx, bus, note, timing.fade - 0.5, 1.4, 0.085, 'pad', delay));
        const buffer = await ctx.startRendering();
        const left = buffer.getChannelData(0);
        const right = buffer.getChannelData(1);
        for (let i = 0; i < buffer.length; i++) {
            const center = (left[i] + right[i]) * 0.5;
            const sides = (left[i] - right[i]) * 0.06;
            left[i] = center + sides;
            right[i] = center - sides;
        }
        let power = 0;
        for (let chan = 0; chan < 2; chan++) {
            const data = buffer.getChannelData(chan);
            for (let i = 0; i < data.length; i++) power += data[i] * data[i];
        }
        if (power > 0) {
            const rms = Math.sqrt(power / (buffer.length * 2));
            const level = 0.24 / rms;
            for (let chan = 0; chan < 2; chan++) {
                const data = buffer.getChannelData(chan);
                for (let i = 0; i < data.length; i++) data[i] = Math.tanh(data[i] * level / 0.96) * 0.96;
            }
        }
        return buffer;
    }

    /* Decode the mastered local anthem and synthesize it if its downloadable asset is unavailable */
    async function getScoreBuffer() {
        const assets = [[timing.track, 'anthem'], ['audio/reserve.mp3?v=1', 'synth']];
        for (const [url, kind] of assets) {
            try {
                const reply = await fetch(url);
                if (!reply.ok) throw new Error('The mastered anthem is unavailable');
                const offline = window.OfflineAudioContext || window.webkitOfflineAudioContext;
                const ctx = new offline(2, 512, 44100);
                const buffer = await ctx.decodeAudioData(await reply.arrayBuffer());
                if (Math.abs(buffer.duration - length) > 0.12) throw new Error('The anthem duration does not match the scene');
                return {buffer, kind};
            } catch (error) {
                console.warn(kind === 'anthem' ? 'Anthem unavailable; loading the reserve score' : 'Rendering the emergency score', error);
            }
        }
        return {buffer: await getScoreRender(), kind: 'synth'};
    }

    /* Keep an original musical score seekable and synchronized with the presentation clock */
    class PhoenixScore {
        constructor() {
            this.audio = null;
            this.shared = false;
            this.source = null;
            this.level = 0.7;
            this.offset = 0;
            this.ready = getScoreBuffer().then(({buffer, kind}) => { this.buffer = buffer; this.kind = kind; return Boolean(buffer); });
        }

        /* Start the rendered track only after a user gesture has enabled sound */
        async setPlay(offset) {
            const ready = await this.ready;
            if (!ready) return false;
            const audioctx = window.AudioContext || window.webkitAudioContext;
            if (!this.audio) {
                const shared = window.PhoenixBridge.getAudio();
                this.shared = Boolean(shared);
                this.audio = shared || new audioctx();
                this.gain = this.audio.createGain();
                this.fade = this.audio.createGain();
                this.fade.connect(this.gain);
                this.gain.connect(this.audio.destination);
            }
            await this.audio.resume();
            this.setStop();
            if (offset >= Math.min(length, this.buffer.duration)) return false;
            this.offset = Math.max(0, offset);
            this.source = this.audio.createBufferSource();
            this.source.buffer = this.buffer;
            this.source.connect(this.fade);
            this.gain.gain.setValueAtTime(this.level, this.audio.currentTime);
            const now = this.audio.currentTime;
            this.fade.gain.cancelScheduledValues(now);
            this.fade.gain.setValueAtTime(1, now);
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
