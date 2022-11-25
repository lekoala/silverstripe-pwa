const DEBUG = self.__SW_DEBUG ?? true;
const PUSH_PUBLIC_KEY = self.__SW_PUSH_PUBLIC_KEY ?? null;
let debugLog = console.log.bind(window.console);
if (!DEBUG) {
    debugLog = () => {};
}
let updateAsked = false;

function promptForUpdate() {
    return confirm("An update is available. Reload now ?");
}

/**
 * @param {ServiceWorker} worker
 */
function trackInstalling(worker) {
    worker.addEventListener("statechange", function () {
        if (worker.state === "installed") {
            updateReady(worker);
        }
    });
}

/**
 * @param {ServiceWorker} worker
 */
function updateReady(worker) {
    // This can be called twice
    if (updateAsked) {
        return;
    }
    updateAsked = true;
    // @link https://whatwebcando.today/articles/handling-service-worker-updates/
    if (promptForUpdate()) {
        worker.postMessage({ type: "SKIP_WAITING" });
    }
}

function watchControllerChange() {
    // Ensure refresh is only called once on service worker change.
    // This works around a bug in "force update on reload".
    let refreshing = false;
    navigator.serviceWorker.addEventListener("controllerchange", function () {
        if (refreshing) {
            return;
        }
        window.location.reload();
        refreshing = true;
    });
}

/**
 * @param {ServiceWorker} worker
 */
function sendMessages(worker) {
    // @link https://felixgerschau.com/how-to-communicate-with-service-workers/
    // At this point, a Service Worker is controlling the current page
    const messageChannel = new MessageChannel();

    // First we initialize the channel by sending the port to the Service Worker (this also transfers the ownership of the port)
    worker.postMessage(
        {
            type: "INIT_PORT",
        },
        [messageChannel.port2]
    );

    messageChannel.port1.onmessage = (event) => {
        debugLog(`Service worker version: ${event.data}`);
    };

    worker.postMessage({
        type: "GET_VERSION",
    });
}

/**
 * @param {ServiceWorkerRegistration} registration
 */
function checkPreloadState(registration) {
    if (!registration.navigationPreload) {
        return;
    }
    registration.navigationPreload.getState().then((state) => {
        debugLog(
            `navigation preload: ${
                state.enabled ? "enabled" : "disabled"
            }, header value: ${state.headerValue}`
        );
    });
}

async function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) {
        return;
    }
    try {
        const registration = await navigator.serviceWorker.register("/sw.js");
        if (!registration) {
            return;
        }

        if (registration.installing) {
            debugLog("Service worker installing");
            trackInstalling(registration.installing);
        } else if (registration.waiting) {
            debugLog("Service worker installed");
            updateReady(registration.waiting);
        } else if (registration.active) {
            debugLog("Service worker active");
        }

        registration.addEventListener("updatefound", () => {
            trackInstalling(registration.installing);
        });

        checkPreloadState(registration);

        navigator.serviceWorker.ready.then((registration) => {
            // At this point, a Service Worker is controlling the current page
            watchControllerChange();
            sendMessages(registration.active);
        });
    } catch (error) {
        console.error(`Registration failed with ${error}`);
    }
}

// @link https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers
registerServiceWorker();

function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, "+")
        .replace(/_/g, "/");

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

async function enableNotifications(registration) {
    if (!("Notification" in window)) {
        alert("Your browser does not support notifications");
        return false;
    }
    if (!PUSH_PUBLIC_KEY) {
        alert("Push server is not configured");
        return false;
    }

    const status = await Notification.requestPermission();
    if (status === "granted") {
        const registration = await navigator.serviceWorker.getRegistration();
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(PUSH_PUBLIC_KEY),
        });
        if (!subscription) {
            return false;
        }
        const response = await fetch("/__push/addPushSubscription", {
            body: JSON.stringify(subscription),
            method: "POST",
            headers: {
                "content-type": "application/json",
            },
        });
        return response.ok;
    } else {
        alert("You need to allow notifications in your browser");
    }
    return false;
}

async function disableNotifications(registration) {
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
        return false;
    }
    const response = await fetch("/__push/removePushSubscription", {
        body: JSON.stringify(subscription),
        method: "POST",
        headers: {
            "content-type": "application/json",
        },
    });
    if (response.ok) {
        subscription.unsubscribe();
    }
    return response.ok;
}

async function configurePushButton(registration) {
    const pushToggle = document.querySelector(".js-push-toggle");
    if (!pushToggle) {
        return;
    }
    pushToggle.disabled = false;

    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
        pushToggle.checked = true;
    }
    pushToggle.addEventListener("change", (event) => {
        if (pushToggle.checked) {
            const result = enableNotifications(registration);
            if (!result) {
                pushToggle.checked = false;
                if (subscription) {
                    subscription.unsubscribe();
                }
            }
        } else {
            disableNotifications(registration);
        }
    });
}

navigator.serviceWorker.ready.then((registration) => {
    configurePushButton(registration);
});
