const mix = require('laravel-mix');
const webpack = require('webpack');

mix.options({
    processCssUrls: false,
});

mix.setPublicPath('client/dist');
mix.sass('client/src/sass/cookiebar.scss', 'client/dist/css/');
mix.js(['client/src/js/CookieBar.js'], 'client/dist/js/');
