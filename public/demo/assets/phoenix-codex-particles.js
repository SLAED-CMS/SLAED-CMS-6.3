(function () {
    'use strict';
    const vertex = `
        precision highp float;
        attribute vec3 origin;
        attribute vec2 bird;
        attribute vec2 logo;
        attribute vec2 mark;
        attribute float seed;
        uniform float clock;
        uniform float ratio;
        uniform float dimmer;
        uniform vec2 bounds;
        uniform vec4 birdbox;
        uniform vec4 logobox;
        uniform vec4 markbox;
        uniform vec3 blue;
        uniform vec3 pale;
        uniform vec3 warm;
        varying vec4 color;
        void main() {
            float birth = smoothstep(15.1 + seed * 0.9, 18.8 + seed * 0.4, clock);
            float merge = smoothstep(23.8 + seed * 0.3, 26.8, clock);
            float arch = smoothstep(12.2, 14.0, clock) * (1.0 - smoothstep(14.8, 16.2, clock));
            float turn = clock * 0.45 + seed * 62.832;
            float radius = 0.16 + origin.z * 0.67;
            vec2 flow = vec2(cos(turn) * radius * bounds.x, sin(turn) * radius * bounds.y * 0.65);
            flow += vec2(bounds.x * 0.5, bounds.y * 0.48);
            flow += origin.xy * bounds * (0.18 + 0.17 * sin(clock * 0.6 + seed * 9.0));
            vec2 stamp = markbox.xy + mark * markbox.zw;
            flow = mix(flow, stamp, arch * 0.95);
            vec2 target = birdbox.xy + bird * birdbox.zw;
            vec2 finalpos = logobox.xy + logo * logobox.zw;
            vec2 point = mix(flow, target, birth);
            point = mix(point, finalpos, merge);
            float drift = (1.0 - merge) * birth * 0.7;
            point += vec2(sin(clock * 2.1 + seed * 55.0), cos(clock * 2.7 + seed * 39.0)) * drift;
            float pulse = 0.5 + 0.5 * sin(clock * 7.0 + seed * 130.0);
            float alpha = smoothstep(10.8, 13.0, clock) * (1.0 - smoothstep(27.2, 28.8, clock));
            vec3 tone = mix(blue, pale, pow(seed, 4.0) * 0.82);
            if (seed > 0.984 && clock < 20.0) tone = warm;
            color = vec4(tone, alpha * (0.21 + pulse * 0.43) * dimmer);
            gl_Position = vec4(point.x / bounds.x * 2.0 - 1.0, 1.0 - point.y / bounds.y * 2.0, 0.0, 1.0);
            float flare = smoothstep(14.0, 19.0, clock);
            gl_PointSize = (2.0 + seed * 1.8 + flare * 2.1 + pulse * 0.5) * ratio;
        }
    `;
    const fragment = `
        precision mediump float;
        varying vec4 color;
        void main() {
            float dist = length(gl_PointCoord - vec2(0.5));
            float glow = max(0.0, 1.0 - dist * 2.0);
            glow = pow(glow, 1.35);
            gl_FragColor = vec4(color.rgb, color.a * glow);
        }
    `;

    /* Sample only visible pixels of a local image into normalized particle targets */
    function getPixelTargets(image, count, alpha) {
        const canvas = document.createElement('canvas');
        const wide = 960;
        const high = Math.round(wide * image.naturalHeight / image.naturalWidth);
        canvas.width = wide;
        canvas.height = high;
        const ctx = canvas.getContext('2d', {willReadFrequently: true});
        ctx.drawImage(image, 0, 0, wide, high);
        const pixels = ctx.getImageData(0, 0, wide, high).data;
        const points = [];
        for (let yy = 0; yy < high; yy += 2) {
            for (let xx = 0; xx < wide; xx += 2) {
                const off = (yy * wide + xx) * 4;
                if (pixels[off + 3] > alpha) points.push([xx / wide - 0.5, yy / high - 0.5]);
            }
        }
        if (!points.length) throw new Error('The presentation image has no visible pixels');
        return Array.from({length: count}, (_, index) => points[(index * 701 + Math.floor(index / 13) * 113) % points.length]);
    }

    /* Compile one isolated GPU shader and preserve useful diagnostics */
    function getCloudShader(gl, type, source) {
        const shader = gl.createShader(type);
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(shader));
        return shader;
    }

    /* Render one reusable particle cloud with SVG and phoenix image targets */
    class PhoenixCloud {
        constructor(canvas, images) {
            this.canvas = canvas;
            this.count = innerWidth < 600 ? 6500 : 16000;
            this.bird = getPixelTargets(images.bird, this.count, 120);
            this.logo = getPixelTargets(images.logo, this.count, 150);
            this.mark = getPixelTargets(images.mark, this.count, 150);
            this.gl = canvas.getContext('webgl', {alpha: true, antialias: false, premultipliedAlpha: true});
            this.colors = [];
            this.ratio = 1;
            if (!this.gl) {
                this.ctx = canvas.getContext('2d');
                this.count = Math.min(this.count, 3500);
                return;
            }
            const gl = this.gl;
            const prog = gl.createProgram();
            gl.attachShader(prog, getCloudShader(gl, gl.VERTEX_SHADER, vertex));
            gl.attachShader(prog, getCloudShader(gl, gl.FRAGMENT_SHADER, fragment));
            gl.linkProgram(prog);
            if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(prog));
            this.prog = prog;
            gl.useProgram(prog);
            this.props = {};
            ['clock', 'ratio', 'dimmer', 'bounds', 'birdbox', 'logobox', 'markbox', 'blue', 'pale', 'warm'].forEach((name) => {
                this.props[name] = gl.getUniformLocation(prog, name);
            });
            const data = new Float32Array(this.count * 10);
            for (let i = 0; i < this.count; i++) {
                const off = i * 10;
                const seed = ((i * 8191) % 32749) / 32749;
                data[off] = Math.sin(i * 71.19);
                data[off + 1] = Math.cos(i * 41.31);
                data[off + 2] = seed;
                data[off + 3] = this.bird[i][0];
                data[off + 4] = this.bird[i][1];
                data[off + 5] = this.logo[i][0];
                data[off + 6] = this.logo[i][1];
                data[off + 7] = this.mark[i][0];
                data[off + 8] = this.mark[i][1];
                data[off + 9] = seed;
            }
            const buffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
            gl.bufferData(gl.ARRAY_BUFFER, data, gl.STATIC_DRAW);
            [['origin', 3, 0], ['bird', 2, 3], ['logo', 2, 5], ['mark', 2, 7], ['seed', 1, 9]].forEach(([name, size, off]) => {
                const loc = gl.getAttribLocation(prog, name);
                gl.enableVertexAttribArray(loc);
                gl.vertexAttribPointer(loc, size, gl.FLOAT, false, 40, off * 4);
            });
            gl.enable(gl.BLEND);
        }

        /* Match the backing surface to the viewport without reallocating particle targets */
        setResize(wide, high) {
            this.wide = wide;
            this.high = high;
            this.ratio = Math.min(devicePixelRatio || 1, 1.5);
            this.canvas.width = Math.round(wide * this.ratio);
            this.canvas.height = Math.round(high * this.ratio);
            if (this.gl) this.gl.viewport(0, 0, this.canvas.width, this.canvas.height);
            else this.ctx.setTransform(this.ratio, 0, 0, this.ratio, 0, 0);
        }

        /* Use actual SLAED theme colors in both additive and normal blending */
        setTheme(colors, dark) {
            this.colors = colors;
            this.dark = dark;
            if (!this.gl) return;
            const gl = this.gl;
            gl.useProgram(this.prog);
            gl.blendFuncSeparate(gl.SRC_ALPHA, dark ? gl.ONE : gl.ONE_MINUS_SRC_ALPHA, gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
            gl.uniform3fv(this.props.blue, colors[0].map((num) => num / 255));
            gl.uniform3fv(this.props.pale, colors[1].map((num) => num / 255));
            gl.uniform3fv(this.props.warm, colors[2].map((num) => num / 255));
        }

        /* Morph the same particles between architecture, wings and the true wordmark */
        setFrame(time, birdbox, logobox, markbox) {
            if (!this.gl) {
                this.setCanvasFrame(time, birdbox, logobox);
                return;
            }
            const gl = this.gl;
            gl.clear(gl.COLOR_BUFFER_BIT);
            if (time < 10.8 || time > 28.8) return;
            gl.useProgram(this.prog);
            gl.uniform1f(this.props.clock, time);
            gl.uniform1f(this.props.ratio, this.ratio);
            gl.uniform1f(this.props.dimmer, this.dark ? 1 : 0.9);
            gl.uniform2f(this.props.bounds, this.wide, this.high);
            gl.uniform4fv(this.props.birdbox, birdbox);
            gl.uniform4fv(this.props.logobox, logobox);
            gl.uniform4fv(this.props.markbox, markbox);
            gl.drawArrays(gl.POINTS, 0, this.count);
        }

        /* Preserve the complete visual story when GPU rendering is unavailable */
        setCanvasFrame(time, birdbox, logobox) {
            const ctx = this.ctx;
            const rise = Math.max(0, Math.min(1, (time - 15) / 4));
            const merge = Math.max(0, Math.min(1, (time - 23.8) / 3));
            const alpha = Math.max(0, Math.min(1, (time - 11) / 2)) * (1 - Math.max(0, Math.min(1, (time - 27.2) / 1.6)));
            ctx.clearRect(0, 0, this.wide, this.high);
            if (alpha < 0.005) return;
            ctx.fillStyle = 'rgb(' + this.colors[0].join(',') + ')';
            ctx.globalAlpha = alpha * 0.75;
            for (let i = 0; i < this.count; i++) {
                const turn = i * 2.39 + time;
                const seed = i / this.count;
                const born = [birdbox[0] + this.bird[i][0] * birdbox[2], birdbox[1] + this.bird[i][1] * birdbox[3]];
                const end = [logobox[0] + this.logo[i][0] * logobox[2], logobox[1] + this.logo[i][1] * logobox[3]];
                let xx = this.wide * (0.5 + Math.cos(turn) * seed * 0.65);
                let yy = this.high * (0.48 + Math.sin(turn) * seed * 0.55);
                xx += (born[0] - xx) * rise;
                yy += (born[1] - yy) * rise;
                xx += (end[0] - xx) * merge;
                yy += (end[1] - yy) * merge;
                ctx.fillRect(xx, yy, 1.6, 1.6);
            }
            ctx.globalAlpha = 1;
        }
    }
    window.PhoenixCloud = PhoenixCloud;
})();
