// Service worker NutriPetit
// Stratégie volontairement simple et sûre :
//  - navigations : réseau d'abord, page hors-ligne en repli ;
//  - /assets/* (fingerprintés, immuables) : cache d'abord ;
//  - tout le reste (dont /app/scan/*) : réseau normal, jamais mis en cache
//    (un score ne doit jamais être servi périmé depuis un cache SW).

const CACHE_VERSION = "np-v2";
const OFFLINE_URL = "/offline.html";
const PRECACHE = [OFFLINE_URL, "/manifest.json", "/icons/icon-192.png"];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE)),
    );
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE_VERSION)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener("fetch", (event) => {
    const request = event.request;

    if (request.method !== "GET") {
        return;
    }

    // Navigations : réseau d'abord, fallback hors-ligne.
    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    // Assets fingerprintés : cache d'abord, réseau en complément.
    const url = new URL(request.url);
    if (url.origin === self.location.origin && url.pathname.startsWith("/assets/")) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches
                            .open(CACHE_VERSION)
                            .then((cache) => cache.put(request, copy));
                    }

                    return response;
                });
            }),
        );
    }
});
