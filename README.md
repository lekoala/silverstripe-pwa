# SilverStripe Pwa module

![Build Status](https://github.com/lekoala/silverstripe-pwa/actions/workflows/ci.yml/badge.svg)
[![scrutinizer](https://scrutinizer-ci.com/g/lekoala/silverstripe-pwa/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/lekoala/silverstripe-pwa/)
[![Code coverage](https://codecov.io/gh/lekoala/silverstripe-pwa/branch/master/graph/badge.svg)](https://codecov.io/gh/lekoala/silverstripe-pwa)

## Features

-   Support push notifications
-   Generate a valid manifest
-   Provides a default configurable service worker that can cache assets

## Generate icons

Use the cli `npm run generate-assets(-root)` (if you have a `app/images/logo.svg` file) or upload a zip file with the required icons

Note: pay attention to [maskable icons](https://web.dev/maskable-icon/). You can test their
size in the developer console. You can test them in [maskable.app](https://maskable.app/)

## Subscribe to push notifications

client.js provides some base code for this. You need to put somewhere a checkbox that can be used
to toggle push notifications on/off.

```html
<label
    ><input type="checkbox" class="js-push-toggle" /> Enable
    notifications</label
>
```

This is dealt with by client.js. You can replace client code by your custom code or disable it entirely

```yml
LeKoala\SsPwa\ServiceWorkerController:
    enable_client_js: false
    custom_client_path: "/my/custom/path"
```

## Service worker

This module provides a default service worker. You can use your own if needed

```yml
LeKoala\SsPwa\ServiceWorkerController:
    custom_sw_path: "/my/custom/path"
```

## Use vapid keys

You can generate the vapid keys with this [online tool](https://tools.reactpwa.com/vapid) or with the cli `npm run gen-push-keys`

You need to save them either in your .env file or as YML config.

```
PUSH_PUBLIC_KEY='BK1Zt63e94HgYNm-s9aquI85AUdDRz3uKMxue7woQVZv0_3txywXPgyYd2WPJetayKYq3E_AObBGD9rHWOL_...'
PUSH_PRIVATE_KEY='1nKKQnzGRMNit-TuvklqoTY_ENO8eAybp1MsZWx0...'
```

## Sending push notifications

You can use the following piece of code

```php
PushSubscription::sendPushNotifications($where, $data);
```

## Credits

Thanks to https://github.com/a2nt/silverstripe-progressivewebapp for some inspiration :-)

## Compatibility

Tested with ^4.11

## Maintainer

LeKoala - thomas@lekoala.be
