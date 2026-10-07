import { build } from 'esbuild'
import { readdirSync, unlinkSync } from 'node:fs'

const out = '../assets'
const langs = {
    php: ['@codemirror/lang-php', 'php'],
    html: ['@codemirror/lang-html', 'html'],
    css: ['@codemirror/lang-css', 'css'],
    js: ['@codemirror/lang-javascript', 'javascript'],
    json: ['@codemirror/lang-json', 'json'],
    sql: ['@codemirror/lang-sql', 'sql'],
    xml: ['@codemirror/lang-xml', 'xml'],
    markdown: ['@codemirror/lang-markdown', 'markdown'],
}

for (const file of readdirSync(out)) if (/^(core|lang-[a-z]+|chunk-\w+)\.js$/.test(file)) unlinkSync(out + '/' + file)

await build({
    entryPoints: ['entry.js'],
    bundle: true,
    minify: true,
    format: 'iife',
    globalName: 'CM6',
    outfile: out + '/cm6.bundle.js',
})

const split = await build({
    metafile: true,
    entryPoints: Object.fromEntries([['core', 'core.js'], ...Object.keys(langs).map((key) => ['lang-' + key, 'lang:' + key])]),
    bundle: true,
    minify: true,
    format: 'esm',
    splitting: true,
    outdir: out,
    chunkNames: 'chunk-[hash]',
    plugins: [{
        name: 'lang',
        setup(run) {
            run.onResolve({ filter: /^lang:/ }, (arg) => ({ path: arg.path.slice(5), namespace: 'lang' }))
            run.onLoad({ filter: /.*/, namespace: 'lang' }, (arg) => ({
                contents: 'export { ' + langs[arg.path][1] + ' as language } from \'' + langs[arg.path][0] + '\'',
                resolveDir: process.cwd(),
            }))
        },
    }],
})

for (const [file, meta] of Object.entries(split.metafile.outputs)) {
    if (meta.imports.some((one) => one.path.endsWith('/core.js'))) throw new Error(file + ' imports the core entry: a versioned core would load twice')
}
