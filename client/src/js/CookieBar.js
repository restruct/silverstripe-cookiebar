import Cookies from 'js-cookie'

/**
 *
 * @type {string}
 */
const cookieAcceptKey = '$ConsentCookieKey';
const cookieAcceptExpireDays = $ConsentExpiration;
let cookiesAccepted = Cookies.get(cookieAcceptKey);

document.addEventListener("DOMContentLoaded", function(event) {

  // insert cookiebar into body (fallback to legacy #cookiebarholder in case of overridden template)
  let cookieAcceptTemplate = document.getElementById('cookiebar-template');
  if(!cookieAcceptTemplate) cookieAcceptTemplate = document.querySelector('#cookiebarholder');
  if (typeof cookiesAccepted === 'undefined' || cookiesAccepted === null) {
    document.querySelector('body')
      // https://developer.mozilla.org/en-US/docs/Web/API/Element/insertAdjacentHTML
      .insertAdjacentHTML('afterbegin', cookieAcceptTemplate.innerHTML);

    // process cookie accept
    document.getElementById('acceptcookies')
      .addEventListener('click', function(event) {
          event.preventDefault();
          Cookies.set(cookieAcceptKey, Math.floor(Date.now()), {expires: cookieAcceptExpireDays, path: '/'});
          cookiesAccepted = Cookies.get(cookieAcceptKey);
          triggerCookieBarRunIfConsent(); // Run optional 'SiteConfig.CookieBarRunOnConsent' script/code
          fadeOut(document.getElementById('cookiebar'), 400);
      });
  } else {
      // Run optional 'SiteConfig.CookieBarRunOnConsent' script/code
      triggerCookieBarRunIfConsent();
  }



  // Run optional 'SiteConfig.CookieBarRunOnConsent' script/code IF DEFINED
  function triggerCookieBarRunIfConsent() {
      if (typeof cookieBarRunIfConsent === 'function') {
          cookieBarRunIfConsent();
      }
  }

  // jQuery fadeOut equivalent https://plainjs.com/javascript/effects/animate-an-element-property-44/
  function fadeOut(el, duration) {
    var s = el.style, step = 25/(duration || 300);
    s.opacity = s.opacity || 1;
    (function fade() { (s.opacity -= step) < 0 ? s.display = "none" : setTimeout(fade, 25); })();
  }
});
