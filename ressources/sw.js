const CACHE_NAME = self.__SW_CACHE_NAME ?? "v1";
const VERSION = self.__SW_VERSION ?? "1";
const DEBUG = self.__SW_DEBUG ?? true;
const ENABLE_CLIENT_CACHE = self.__SW_ENABLE_CLIENT_CACHE ?? true;
const CACHE_MANIFEST = self.__SW_CACHE_MANIFEST ?? [];

let debugLog = console.log.bind(self.console);
if (!DEBUG) {
    debugLog = () => {};
}

async function addResourcesToCache(resources) {
    const cache = await caches.open(CACHE_NAME);
    await cache.addAll(resources);
}

async function putInCache(request, response) {
    const cache = await caches.open(CACHE_NAME);
    await cache.put(request, response);
}

async function deleteCache(key) {
    await caches.delete(key);
}

async function deleteOldCaches() {
    const cacheKeepList = [CACHE_NAME];
    const keyList = await caches.keys();
    const cachesToDelete = keyList.filter(
        (key) => !cacheKeepList.includes(key)
    );
    await Promise.all(cachesToDelete.map(deleteCache));
}

async function enableNavigationPreload() {
    if (self.registration.navigationPreload) {
        // Enable navigation preloads!
        await self.registration.navigationPreload.enable();
    }
}

async function cacheFirst({ request, preloadResponsePromise, fallbackUrl }) {
    // Try to get the resource from the cache
    const responseFromCache = await caches.match(request);
    if (responseFromCache) {
        debugLog(`Cached: ${responseFromCache.url}`);
        return responseFromCache;
    }

    // Try to use (and cache) the preloaded response, if it's there
    // @link https://web.dev/navigation-preload/
    if (preloadResponsePromise) {
        const preloadResponse = await preloadResponsePromise;
        if (preloadResponse) {
            debugLog(`Preloaded: ${preloadResponse.url}`);
            if (ENABLE_CLIENT_CACHE) {
                putInCache(request, preloadResponse.clone());
            }
            return preloadResponse;
        }
    }

    // Try to get the resource from the network
    try {
        const responseFromNetwork = await fetch(request);
        // response may be used only once we need to save clone to put one copy in cache and serve second one
        if (ENABLE_CLIENT_CACHE) {
            putInCache(request, responseFromNetwork.clone());
        }
        return responseFromNetwork;
    } catch (error) {
        if (fallbackUrl) {
            const fallbackResponse = await caches.match(fallbackUrl);
            if (fallbackResponse) {
                return fallbackResponse;
            }
        }
        // when even the fallback response is not available, there is nothing we can do, but we must always return a Response object
        return new Response("Network error happened", {
            status: 408,
            headers: { "Content-Type": "text/plain" },
        });
    }
}

self.addEventListener("fetch", (event) => {
    // Skip cross-origin requests, like those for Google Analytics.
    if (event.request.url.startsWith(self.location.origin)) {
        event.respondWith(
            cacheFirst({
                request: event.request,
                preloadResponsePromise: event.preloadResponse,
                fallbackUrl: null,
            })
        );
    } else {
        debugLog(`Skipped: ${event.request.url}`);
    }
});

// The service worker lifecycle starts with registering the service worker.
// The browser then attempts to download and parse the service worker file.
// This will happen for a new service or if the service worker is updated.
// If parsing succeeds, its install event is fired. The install event only fires once.
self.addEventListener("install", (event) => {
    debugLog("Service worker installed");
    event.waitUntil(addResourcesToCache(CACHE_MANIFEST));
});

// After the installation, the service worker is not yet in control of its clients, including your PWA.
// It needs to be activated first. When the service worker is ready to control its clients, the activate event will fire.
// If the service worker is updated, the activate event is delayed until all clients are disconnected (=> tabs closed)
// or until skipWaiting is called. The activate event only happens once.
self.addEventListener("activate", (event) => {
    debugLog("Service worker activated");
    // The waitUntil() method must be initially called within the event callback, but after that it can be called multiple times, until all the promises passed to it settle.
    event.waitUntil(enableNavigationPreload());
    event.waitUntil(deleteOldCaches());
});

let getVersionPort = null;
self.addEventListener("message", (event) => {
    if (!event.data || !event.data.type) {
        return;
    }

    // - type is a required unique string identifying the message. The format should be in uppercase with underscores separating words (for example, CACHE_URLS).
    // - meta is an optional string representing the name of the Workbox package sending the message, and is usually omitted.
    // - payload is an optional parameter representing the data you want to send. It can be any data type.
    const eventType = event.data.type;
    switch (eventType) {
        // request self.skipWaiting() from browser (this is needed by workbox, see messageSkipWaiting())
        case "SKIP_WAITING":
            self.skipWaiting();
            break;
        // init port
        case "INIT_PORT":
            debugLog("port initialized");
            getVersionPort = event.ports[0];
            break;
        // get version
        case "GET_VERSION":
            if (getVersionPort) {
                getVersionPort.postMessage(VERSION);
            } else {
                event.ports[0].postMessage(VERSION);
            }
            break;
    }
});

// @link https://flaviocopes.com/push-api/
// @link https://web.dev/push-notifications-handling-messages/
self.addEventListener("push", function (event) {
    if (!event.data) {
        debugLog("This push event has no data.");
        return;
    }
    if (!self.registration) {
        debugLog("Service worker does not control the page");
        return;
    }
    if (!self.registration.pushManager) {
        debugLog("Push is not supported");
        return;
    }

    const eventText = event.data.text();
    // Specify default options
    let options = {};
    let title = "";

    // Support both plain text notification and json
    if (eventText.substr(0, 1) === "{") {
        let eventData;
        try {
            eventData = JSON.parse(eventText);
        } catch (error) {
            debugLog("Invalid push data", eventText);
            return;
        }

        title = eventData.title;

        // Set specific options
        // @link https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerRegistration/showNotification#parameters
        if (eventData.options) {
            options = Object.assign(options, eventData.options);
        }

        // Check expiration if specified
        if (eventData.expires && Date.now() > eventData.expires) {
            debugLog("Push notification has expired");
            return;
        }
    } else {
        title = eventText;
    }

    // Warning: this can fail silently if notifications are disabled at system level
    // The promise itself resolve to undefined and is not helpful to see if it has been displayed properly
    if (Notification.permission === "granted") {
        const promiseChain = self.registration.showNotification(title, options);
        // With this, the browser will keep the service worker running until the promise you passed in has settled.
        event.waitUntil(promiseChain);
    } else {
        debugLog("Notifications are not granted", event);
    }
});

// This will fire only once when the sw is initially loaded
debugLog("Service worker loaded");
