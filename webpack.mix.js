const mix = require('laravel-mix');
const webpack = require('webpack');

mix.options({
    processCssUrls: false,
});

mix.setPublicPath('client/dist');
mix.js(['client/src/js/CookieBar.js'], 'client/dist/js/');
mix.sass('client/src/sass/cookiebar.scss', 'client/dist/css/');
mix.sass('client/src/sass/cookiebar-layout-sans-bs.scss', 'client/dist/css/');
