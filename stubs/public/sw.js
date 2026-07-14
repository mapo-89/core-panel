"use strict";

const CACHE_PREFIX = "core-panel-";
const CACHE_NAME = "core-panel-offline-cache-v1";
const OFFLINE_URL = '/offline.html';

const filesToCache = [
    OFFLINE_URL,
];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(filesToCache)),
    );
});

self.addEventListener("fetch", (event) => {
    if (event.request.mode === "navigate") {
        event.respondWith(
            fetch(event.request)
                .catch(() => caches.match(OFFLINE_URL)),
        );

        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then((response) => response || fetch(event.request)),
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (
                        cacheName.startsWith(CACHE_PREFIX) &&
                        cacheName !== CACHE_NAME
                    ) {
                        return caches.delete(cacheName);
                    }

                    return undefined;
                }),
            );
        }),
    );
});
