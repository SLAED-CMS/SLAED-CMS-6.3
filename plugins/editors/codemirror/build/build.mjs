import { build } from 'esbuild'

await build({
    entryPoints: ['entry.js'],
    bundle: true,
    minify: true,
    format: 'iife',
    globalName: 'CM6',
    outfile: '../assets/cm6.bundle.js',
})
