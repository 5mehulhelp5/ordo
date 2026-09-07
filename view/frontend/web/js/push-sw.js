/**
 * Ordo Automation — Web Push service worker.
 *
 * Served through Controller\Track\PushServiceWorker (see that class's own docblock for why not
 * as a plain static asset) with `Service-Worker-Allowed: /`, registered from tracker.js with
 * `{scope: '/'}` — so it can show a notification no matter which page was open when the push
 * arrived.
 *
 * Deliberately dependency-free, matching tracker.js — a service worker has no DOM/window, so
 * nothing here could reuse tracker.js's helpers even if this were bundled together with it.
 */
self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        // A push arrived with no payload, or one this module didn't itself encrypt/format -
        // still acknowledge it with a generic notification rather than silently dropping it,
        // since a "your browser received an update" style push with a blank body would look
        // like a bug to the visitor.
        data = {};
    }

    var title = data.title || 'Notification';
    var options = {
        body: data.body || '',
        data: { url: data.url || '/' }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                if (clientList[i].url === url && 'focus' in clientList[i]) {
                    return clientList[i].focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
            return undefined;
        })
    );
});

/**
 * The browser itself decided this subscription needs replacing (key rotation, expiry) - it
 * hands the service worker a brand-new subscription directly, without ever going through
 * tracker.js/window at all, so this is the only place that can register the replacement.
 *
 * event.newSubscription is frequently absent even when a replacement is genuinely needed
 * (observed on both Chrome and Firefox) - per spec, the service worker is then expected to
 * re-subscribe itself using the old subscription's own options, not treat the event as a no-op.
 */
self.addEventListener('pushsubscriptionchange', function (event) {
    var subscriptionPromise = event.newSubscription
        ? Promise.resolve(event.newSubscription)
        : self.registration.pushManager.subscribe(event.oldSubscription.options);

    event.waitUntil(
        subscriptionPromise
            .then(function (subscription) {
                return fetch('/ordo/track/registerpushsubscription', {
                    method: 'POST',
                    body: new URLSearchParams({
                        endpoint: subscription.endpoint,
                        p256dh: arrayBufferToBase64Url(subscription.getKey('p256dh')),
                        auth: arrayBufferToBase64Url(subscription.getKey('auth'))
                    })
                });
            })
            .catch(function () {})
    );
});

function arrayBufferToBase64Url(buffer) {
    var bytes = new Uint8Array(buffer);
    var binary = '';
    for (var i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
