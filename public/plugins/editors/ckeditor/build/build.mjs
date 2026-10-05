import { build } from 'esbuild'

await build({
    entryPoints: ['entry.js'],
    bundle: true,
    minify: true,
    format: 'iife',
    globalName: 'CK5',
    outfile: '../assets/ckeditor.bundle.js',
})
