/**
 * The smallest service worker that makes this installable, and nothing more.
 *
 * Chrome will not offer "install app" without one — which is why the prompt
 * stopped appearing and the icon on a phone was only ever a browser shortcut.
 * The manifest, the icons and the start_url were all already right; this was
 * the missing piece.
 *
 * **It deliberately caches nothing.** A service worker that caches an Inertia
 * app's shell is how you get the classic post-deploy failure: the cached HTML
 * keeps asking for asset filenames that the new build renamed, and every
 * screen 404s until the user clears their browser. Every request goes straight
 * to the network, exactly as if this file did not exist — the fetch handler is
 * here because the install criteria require one to exist, not because there is
 * anything worth intercepting yet.
 *
 * skipWaiting + clients.claim so a future version of this file replaces the
 * old one on the next visit instead of waiting for every tab to close.
 */
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        // Anything a previous version of this file may have stored is dropped
        // on activation, so a caching mistake can never outlive one deploy.
        caches.keys()
            .then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', () => {
    // Intentionally empty: not calling respondWith() lets the browser handle
    // the request normally.
});
