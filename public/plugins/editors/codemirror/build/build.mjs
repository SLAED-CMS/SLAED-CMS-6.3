import { build } from 'esbuild'
import { readdirSync, unlinkSync } from 'node:fs'

const out = '../assets'
const langs = {
    php: 'export { php as language } from \'@codemirror/lang-php\'',
    html: 'export { html as language } from \'@codemirror/lang-html\'',
    css: 'export { css as language } from \'@codemirror/lang-css\'',
    js: 'export { javascript as language } from \'@codemirror/lang-javascript\'',
    json: 'export { json as language } from \'@codemirror/lang-json\'',
    sql: 'export { sql as language } from \'@codemirror/lang-sql\'',
    xml: 'export { xml as language } from \'@codemirror/lang-xml\'',
    // GitHub Markdown as the base, the dialect Toast UI writes: tables, strikethrough and task lists highlight as well
    markdown: 'import { markdown, markdownLanguage } from \'@codemirror/lang-markdown\'\nexport const language = () => markdown({ base: markdownLanguage })',
    // The official legacy mode of ini files; Apache and robots.txt have no grammar anywhere, so those two are written here
    ini: 'import { StreamLanguage } from \'@codemirror/language\'\nimport { properties } from \'@codemirror/legacy-modes/mode/properties\'\n'
        + 'export const language = () => StreamLanguage.define(properties)',
    apache: 'export { language } from \'./apache.js\'',
    robots: 'export { language } from \'./robots.js\'',
}

for (const file of readdirSync(out)) if (/^(core|lang-[a-z]+|chunk-\w+)\.js$/.test(file)) unlinkSync(out + '/' + file)

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
            run.onLoad({ filter: /.*/, namespace: 'lang' }, (arg) => ({ contents: langs[arg.path], resolveDir: process.cwd() }))
        },
    }],
})

for (const [file, meta] of Object.entries(split.metafile.outputs)) {
    if (meta.imports.some((one) => one.path.endsWith('/core.js'))) throw new Error(file + ' imports the core entry: a versioned core would load twice')
}
