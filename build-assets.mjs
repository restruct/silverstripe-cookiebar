// Builds client/dist with Vite (replaces laravel-mix, 2026-11). Run it through package.json:
//   yarn build   minified, no source maps (what gets committed)
//   yarn dev     unminified, with inline source maps
//   yarn watch   as dev, rebuilding on change
//
// Why a script and not a vite.config file: a classic (non-module) script must be built as an IIFE,
// and Rolldown builds an IIFE from ONE entry only ("multiple inputs are not supported when
// output.codeSplitting is false"). So every classic script is its own build, and the styles are
// another. Vite's JS API runs them one after another from one shared config; `vite build` on the
// command line can only run one.
//
// Output names and paths are the ones laravel-mix produced, because PHP loads them by plain path
// (ContentControllerExtension: Requirements::css and Requirements::javascriptTemplate) and composer
// extra.expose publishes client/dist/css and client/dist/js. So: no hashes, explicit file names,
// no manifest, and client/dist is never emptied.
import { build } from 'vite';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('.', import.meta.url));
const args = process.argv.slice(2);
const watch = args.includes('--watch');
const dev = watch || args.includes('--dev');

// One entry per output file; the key is the output file name (without extension)
const parts = [
    {
        // Styles: one CSS file per .scss entry (Vite drops the empty JS chunks of CSS-only entries)
        input: {
            'cookiebar': 'client/src/sass/cookiebar.scss',
            'cookiebar-layout-sans-bs': 'client/src/sass/cookiebar-layout-sans-bs.scss',
        },
    },
    {
        // CookieBar.js is injected as an inline classic <script> by javascriptTemplate(), so it must
        // not be an ES module: an IIFE keeps its top-level consts out of the global scope, as the
        // webpack bundle did. js-cookie is bundled in (it is not provided by the page).
        input: { 'CookieBar': 'client/src/js/CookieBar.js' },
        format: 'iife',
    },
];

const config = (part) => ({
    // Everything is configured here; do not pick up a vite.config file
    configFile: false,
    root,
    mode: dev ? 'development' : 'production',
    // Relative URLs in the CSS, like mix with processCssUrls: false (no url()s in the CSS today)
    base: './',
    // No public/ dir: nothing besides the build output is copied into client/dist
    publicDir: false,
    define: part.define ?? {},
    css: {
        preprocessorOptions: {
            scss: {
                // cookiebar-layout-sans-bs.scss pulls Bootstrap in with @import, which Dart Sass
                // 1.80+ flags on every build (it is removed in Dart Sass 3); the CSS is unaffected.
                // quietDeps silences what Bootstrap's own sources trigger.
                silenceDeprecations: ['import'],
                quietDeps: true,
            },
        },
    },
    build: {
        outDir: 'client/dist',
        // client/dist holds every part's output; a part must never wipe another's files
        emptyOutDir: false,
        // `build` is minified without source maps (as `mix --production` was); `dev` is not
        // minified and inlines its source maps, so no stray .map files land in client/dist
        minify: !dev,
        cssMinify: !dev,
        sourcemap: dev ? 'inline' : false,
        // Keep one CSS file per entry, never inlined into JS
        cssCodeSplit: true,
        // Browser floor for the JS and (through cssTarget, which follows target) the CSS minifier.
        // Vite 8's default (chrome111/safari16.4) would rewrite media queries to range syntax,
        // `(width>=576px)`, which Safari before 16.4 ignores, dropping the bar's column layout
        // on older iPhones. This list is Vite 6's default ("modules").
        target: ['es2020', 'edge88', 'firefox78', 'chrome87', 'safari14'],
        watch: watch ? {} : null,
        rolldownOptions: {
            input: part.input,
            output: {
                format: part.format ?? 'es',
                entryFileNames: 'js/[name].js',
                assetFileNames: 'css/[name][extname]',
                // Vite drops licence comments when it minifies; keep them (js-cookie's MIT notice).
                // webpack moved them to CookieBar.js.LICENSE.txt, this keeps them in the file itself
                comments: { legal: true },
            },
        },
    },
});

for (const part of parts) {
    await build(config(part));
}
