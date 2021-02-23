$(document).ready(function () {
    CookieBar.init();
});
let CookieBar = function () {


    /**
     *
     * @type {string}
     */
    const key = 'Restruct_CookiesAccepted';

    const exdays = '365';

    /**
     * @return {string}
     */
    function GetKey() {
        return 'Restruct_CookiesAccepted';
    }

    return {
        init: function () {
            let _this = this;
            if (null === this.get()) {
                if (document.cookie.indexOf(GetKey() + '=true') == -1) {
                    $('body').prepend($('#cookiebar'));
                }
            }
            $(document).on('click', '#acceptcookies', function (e) {
                e.preventDefault();
                let t = $(this), link = t.attr('href');
                _this.set();
                $('#cookiebar').slideUp(500);
            });
        },
        set: function () {

            let now = new Date();
            now.setFullYear( now.getFullYear() + 1 );
            document.cookie = GetKey() + '=1;SameSite=Strict; expires=' + now.toUTCString();

        },
        get: function () {
            let keyValue = document.cookie.match('(^|;) ?' + GetKey() + '=([^;]*)(;|$)');
            return keyValue ? keyValue[2] : null;
        },

    }
}();
