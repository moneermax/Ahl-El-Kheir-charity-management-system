const CACHE_NAME = "ahl-el-kheir-v2";

self.addEventListener("install", (event) => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)),
        ),
      )
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  // Network-first (always fresh PHP data), cache fallback for offline shell
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request)),
  );
});
