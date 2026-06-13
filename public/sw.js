// Service worker minimal

self.addEventListener("install", () => {
    self.skipWaiting();
});

self.addEventListener("activate", (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener("fetch", () => {
    // Pas d'interception : comportement réseau normal.
    // (Le simple fait d'avoir un SW avec un handler fetch satisfait Chrome.)
});
