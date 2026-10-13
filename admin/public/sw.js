// Ocean View Flats - Admin Console Pass-Through Network-Only Service Worker
// Satisfies standalone PWA installability while guaranteeing live operational data
// and zero client-side storage of authenticated HTML, CSRF tokens, or Guest records.

self.addEventListener("install", () => {
	self.skipWaiting();
});

self.addEventListener("activate", (event) => {
	event.waitUntil(self.clients.claim());
});

self.addEventListener("fetch", (event) => {
	event.respondWith(fetch(event.request));
});
