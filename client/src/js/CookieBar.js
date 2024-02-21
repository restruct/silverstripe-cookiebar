import Cookies from 'js-cookie'

/**
 *
 * @type {string}
 */
const cookieAcceptKey = 'Restruct_CookiesAccepted';
const cookieAcceptExpireDays = 365;
let cookiesAccepted = Cookies.get(cookieAcceptKey);

document.addEventListener("DOMContentLoaded", function(event) {

  // insert cookiebar into body (fallback to legacy #cookiebarholder in case of overridden template)
  let cookieAcceptTemplate = document.getElementById('cookiebar-template');
  if(!cookieAcceptTemplate) cookieAcceptTemplate = document.querySelector('#cookiebarholder');
  if (typeof (cookiesAccepted) === 'undefined' || null === cookiesAccepted) {
    document.querySelector('body')
      // https://developer.mozilla.org/en-US/docs/Web/API/Element/insertAdjacentHTML
      .insertAdjacentHTML('afterbegin', cookieAcceptTemplate.innerHTML);
  } else {
      // Run optional 'SiteConfig.CookieBarRunOnConsent' script/code
      triggerCookieBarRunOnConsent();
  }

  // process cookie accept
  document.getElementById('acceptcookies')
    .addEventListener('click', function(event) {
      event.preventDefault();
      Cookies.set(cookieAcceptKey, 'true', {expires: cookieAcceptExpireDays, path: '/'});
      cookiesAccepted = Cookies.get(cookieAcceptKey);

      // Run optional 'SiteConfig.CookieBarRunOnConsent' script/code
      triggerCookieBarRunOnConsent();

      fadeOut(document.getElementById('cookiebar'), 400);
    });

  // Run optional 'SiteConfig.CookieBarRunOnConsent' script/code IF DEFINED
  function triggerCookieBarRunOnConsent() {
      if (typeof cookieBarRunOnConsent === 'function') {
          cookieBarRunOnConsent();
      } else {
          console.log('CookieBar: no cookieBarRunOnConsent code defined (optional so OK)...')
      }
  }

  // jQuery fadeOut equivalent https://plainjs.com/javascript/effects/animate-an-element-property-44/
  function fadeOut(el, duration) {
    var s = el.style, step = 25/(duration || 300);
    s.opacity = s.opacity || 1;
    (function fade() { (s.opacity -= step) < 0 ? s.display = "none" : setTimeout(fade, 25); })();
  }
});
