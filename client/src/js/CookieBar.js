import Cookies from 'js-cookie'

/**
 *
 * @type {string}
 */
const key = 'Restruct_CookiesAccepted';

const expDays = 365;

$(document).ready(function () {
    let keyValue = Cookies.get(key);
    if (typeof (keyValue) === 'undefined' || null === keyValue) {
        $('body').prepend($('#cookiebar'));
    }
    $(document).on('click', '#acceptcookies', function (e) {
        e.preventDefault();
        Cookies.set(key, 'true', {expires: expDays, path: '/'})
        let keyValue = Cookies.get(key);

        $('#cookiebar').fadeOut();
    });
});
