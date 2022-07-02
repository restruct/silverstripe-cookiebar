# Cookiebar Module #

## Overview ##

This module provides a configurable notice about cookies, a link to a page about them and an 'accept' link to close the notice. It's based on the cookiebar module by Aram Balakjian & Steve Heyes, adapted to be cache-able.

<img width="804" alt="Screenshot 2022-07-02 at 14 13 29" src="https://user-images.githubusercontent.com/1005986/177000331-e8613a6f-8ce9-4920-a8fe-e012c78ac50b.png">

## Requirements

 * SilverStripe 4 or newer

## Installation

Unpack and copy the cookiebar folder into your SilverStripe project (You can call the folder whatever you like).

Now add $CookieBar just before the closing body tag

	...

		$CookieBar
	</body>

	...

Run "dev/build" in your browser, for example: "http://www.mysite.com/dev/build?flush=all"

## Layout

The included template uses bootstrap for its layout. 
In case the site theme does not use bootstrap, there's an optional 'sans-bs' CSS file to include which provides a fallback layout:

```php
Requirements::css('restruct/silverstripe-cookiebar:client/dist/css/cookiebar-layout-sans-bs.css');
```

Fallback layout (without bootstrap):
<img width="803" alt="Screenshot 2022-07-02 at 14 07 06" src="https://user-images.githubusercontent.com/1005986/177000356-4fcc628c-21ee-4e04-8767-83f22af068fb.png">
