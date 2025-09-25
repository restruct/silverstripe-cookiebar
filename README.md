# Cookiebar Module #

Configurable notice about cookies, a link to a page about them and an 'accept' link to close the notice (based on the cookiebar module by Aram Balakjian & Steve Heyes).

<img width="804" alt="Screenshot 2022-07-02 at 14 13 29" src="https://user-images.githubusercontent.com/1005986/177000331-e8613a6f-8ce9-4920-a8fe-e012c78ac50b.png">

## Installation

Install via `composer require` and add $CookieBar just before the closing body tag:

```
...
    $CookieBar
</body>
```

The included template uses bootstrap for its layout. In case the site theme does not use bootstrap, a 'sans-bs' CSS file can be included which provides a fallback layout (see config).

## Configuration
The texts and image can be controlled from the CMS (SiteConfig).  
Config options (with their defaults):
```yml
Restruct\CookieBar\Controls\CookieBarController:
  sans_bs_css: false # include no-bootstrap version CSS
  cookie_name: 'cookie_consent' # name of cookie with timestamp of consent
  cookie_age: 365 # cookie expiration in days
  cookie_refresh: true # refresh consent cookie upon each request
```

Prefab CSS inclusion can be blocked altogether from `_config.php`:
```php
Requirements::block('restruct/silverstripe-cookiebar:client/dist/css/cookiebar.css');
```
