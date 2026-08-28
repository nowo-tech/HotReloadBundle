/**
 * Bundle Hot Reload client (dev only) — classic script, ES5-ish / var-friendly.
 *
 * Modes (from #nowo-hot-reload-config JSON.mode):
 *   shared_worker — SharedWorker holds one Mercure SSE; visible tabs morph;
 *                   hidden tabs queue one pending update and apply on focus.
 *                   Falls back to visibility if SharedWorker is missing.
 *   visibility    — EventSource only while document.visibilityState === 'visible'
 *   always        — EventSource always open per tab (reconnect on error)
 *
 * Config: { mode, workerUrl, idiomorphUrl, preserveSelectors, preserveObserve }
 * Mercure URL: meta[name="frankenphp-hot-reload:url"]
 */
/* eslint-disable no-var -- classic script for broad browser support */
'use strict';

(function () {
    var DEFAULT_IDIOMORPH_URL = 'https://cdn.jsdelivr.net/npm/idiomorph@0.7.4';
    var SHARED_WORKER_NAME = 'nowo-hot-reload';
    var CONFIG_ID = 'nowo-hot-reload-config';

    function readConfig() {
        var node = document.getElementById(CONFIG_ID);
        if (!node || !node.textContent) {
            return {};
        }
        try {
            return JSON.parse(node.textContent);
        } catch (e) {
            return {};
        }
    }

    function mercureUrl() {
        var meta = document.querySelector('meta[name="frankenphp-hot-reload:url"]');
        return meta && meta.content ? meta.content : null;
    }

    function absoluteMercureUrl(raw, base) {
        try {
            return new URL(raw, base || window.location.href).href;
        } catch (e) {
            return raw;
        }
    }

    function reloadUrl(href) {
        var url = new URL(href, window.location.origin);
        url.searchParams.set('reload', String(Date.now()));
        return url.toString();
    }

    function fetchMorphDocument() {
        var url = new URL(window.location.href);
        url.searchParams.set('frankenphp_hot_reloading', 'true');
        url.searchParams.set('reload', String(Date.now()));

        return fetch(url.toString(), { headers: { Accept: 'text/html' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(response.status + ' when fetching ' + url.toString());
                }
                return response.text();
            })
            .then(function (html) {
                return new DOMParser().parseFromString(html, 'text/html');
            });
    }

    function fileStem(path) {
        var parts = path.split('/');
        var name = parts[parts.length - 1] || path;
        return name.split('.')[0] || name;
    }

    function normalizeStylesheetHref(href) {
        return href.replace(/-[a-z0-9]+\.(\w+)(\?.*)?$/, '.$1');
    }

    function reloadMatchingStylesheets(pattern) {
        return fetchMorphDocument().then(function (doc) {
            var links = Array.prototype.slice.call(
                doc.head.querySelectorAll("link[rel='stylesheet']"),
            );
            var current = Array.prototype.slice.call(
                document.querySelectorAll("link[rel='stylesheet']"),
            );

            return Promise.all(
                links.map(function (link) {
                    var href = link.getAttribute('href');
                    if (!href || !pattern.test(href)) {
                        return Promise.resolve();
                    }

                    var existing = null;
                    var i;
                    for (i = 0; i < current.length; i++) {
                        if (
                            normalizeStylesheetHref(current[i].href) ===
                            normalizeStylesheetHref(href)
                        ) {
                            existing = current[i];
                            break;
                        }
                    }

                    return new Promise(function (resolve) {
                        var clone = existing || link.cloneNode(true);
                        clone.onload = function () {
                            resolve();
                        };
                        clone.onerror = function () {
                            resolve();
                        };
                        clone.setAttribute('href', reloadUrl(href));
                        if (!clone.parentNode) {
                            document.head.appendChild(clone);
                        }
                    });
                }),
            );
        });
    }

    function morphReload() {
        if (typeof window.Idiomorph !== 'object') {
            window.location.reload();
            return Promise.resolve();
        }

        return fetchMorphDocument().then(function (doc) {
            window.Idiomorph.morph(document.body, doc.body, {
                callbacks: {
                    beforeNodeMorphed: function (oldNode) {
                        return !(
                            oldNode &&
                            oldNode.nodeType === 1 &&
                            oldNode.hasAttribute('data-frankenphp-hot-reload-preserve')
                        );
                    },
                },
            });

            if (typeof window.Stimulus === 'object' && window.Stimulus.controllers) {
                window.Stimulus.controllers.forEach(function (controller) {
                    window.Stimulus.unload(controller.identifier);
                    window.Stimulus.register(controller.identifier, controller.constructor);
                });
            }
        });
    }

    function handleFileChanges(events) {
        var chain = Promise.resolve();
        var i;
        for (i = 0; i < events.length; i++) {
            (function (event) {
                chain = chain.then(function () {
                    var path = event.associated_path_name || event.path_name;
                    if (!path) {
                        return;
                    }
                    var parts = path.split('.');
                    var extension = parts[parts.length - 1] || '';
                    if (extension === 'css') {
                        return reloadMatchingStylesheets(new RegExp(fileStem(path)));
                    }
                    return morphReload();
                });
            })(events[i]);
            // Only first actionable change (matches official client behaviour)
            break;
        }
        return chain;
    }

    function loadScript(src) {
        var existing = document.querySelector('script[src="' + src + '"]');
        if (existing) {
            return Promise.resolve();
        }

        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = function () {
                resolve();
            };
            script.onerror = function () {
                reject(new Error('Failed to load ' + src));
            };
            document.body.appendChild(script);
        });
    }

    function bootPreserveMarkers(config) {
        var selectors = config.preserveSelectors || [];
        var observe = config.preserveObserve !== false;

        if (!selectors.length) {
            return;
        }

        function mark() {
            selectors.forEach(function (selector) {
                document.querySelectorAll(selector).forEach(function (el) {
                    el.setAttribute('data-frankenphp-hot-reload-preserve', '');
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', mark);
        } else {
            mark();
        }

        if (observe && typeof MutationObserver !== 'undefined') {
            new MutationObserver(mark).observe(document.documentElement, {
                childList: true,
                subtree: true,
            });
        }
    }

    function parseFileChangeData(data) {
        var parsed = JSON.parse(data);
        return Array.isArray(parsed) ? parsed : [parsed];
    }

    function createChangeSink(ensureIdiomorph) {
        var pending = null;
        var applying = false;

        function run(events) {
            applying = true;
            return ensureIdiomorph()
                .then(function () {
                    return handleFileChanges(events);
                })
                .catch(function () {
                    window.location.reload();
                })
                .then(function () {
                    applying = false;
                }, function () {
                    applying = false;
                });
        }

        return {
            apply: function (events) {
                if (document.visibilityState !== 'visible') {
                    pending = events;
                    return;
                }
                pending = null;
                run(events);
            },
            flushPending: function () {
                if (document.visibilityState !== 'visible' || !pending || applying) {
                    return;
                }
                var events = pending;
                pending = null;
                run(events);
            },
        };
    }

    function createVisibilityClient(config, sink, requireVisible) {
        var eventSource = null;
        var connecting = false;
        var idiomorphUrl = config.idiomorphUrl || DEFAULT_IDIOMORPH_URL;

        function disconnect() {
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
        }

        function connect() {
            var url = mercureUrl();
            if (!url || eventSource || connecting) {
                return Promise.resolve();
            }
            if (requireVisible && document.visibilityState !== 'visible') {
                return Promise.resolve();
            }

            connecting = true;
            var loadIdiomorph =
                typeof window.Idiomorph !== 'object'
                    ? loadScript(idiomorphUrl)
                    : Promise.resolve();

            return loadIdiomorph
                .then(function () {
                    eventSource = new EventSource(url);
                    eventSource.onmessage = function (event) {
                        sink.apply(parseFileChangeData(event.data));
                    };
                    eventSource.onerror = function () {
                        disconnect();
                        if (!requireVisible) {
                            setTimeout(function () {
                                connect();
                            }, 1500);
                        }
                    };
                })
                .then(
                    function () {
                        connecting = false;
                    },
                    function () {
                        connecting = false;
                    },
                );
        }

        return { connect: connect, disconnect: disconnect };
    }

    function tryCreateSharedWorker(url) {
        if (typeof SharedWorker === 'undefined') {
            return null;
        }
        try {
            return new SharedWorker(url, { name: SHARED_WORKER_NAME });
        } catch (e) {
            return null;
        }
    }

    function createSharedWorkerClient(config, sink, workerUrl) {
        var worker = tryCreateSharedWorker(workerUrl);
        if (!worker) {
            return null;
        }

        var idiomorphUrl = config.idiomorphUrl || DEFAULT_IDIOMORPH_URL;
        var port = null;
        var started = false;

        function ensureIdiomorph() {
            if (typeof window.Idiomorph === 'object') {
                return Promise.resolve();
            }
            return loadScript(idiomorphUrl);
        }

        function disconnect() {
            if (!port) {
                return;
            }
            try {
                port.postMessage({ type: 'bye' });
            } catch (e) {
                // port already closed
            }
            port.close();
            port = null;
            started = false;
        }

        function connect() {
            var raw = mercureUrl();
            if (!raw || started) {
                return Promise.resolve();
            }
            started = true;

            return ensureIdiomorph().then(function () {
                port = worker.port;
                port.onmessage = function (event) {
                    var message = event.data;
                    if (!message || typeof message !== 'object') {
                        return;
                    }
                    if (message.type === 'file-changes') {
                        sink.apply(parseFileChangeData(message.data));
                    }
                };
                port.start();
                port.postMessage({
                    type: 'hello',
                    mercureUrl: absoluteMercureUrl(raw),
                });
                window.addEventListener('pagehide', disconnect);
            });
        }

        return { connect: connect, disconnect: disconnect };
    }

    function init() {
        var url = mercureUrl();
        if (!url) {
            return;
        }

        var config = readConfig();
        var mode = config.mode || 'visibility';
        bootPreserveMarkers(config);

        var idiomorphUrl = config.idiomorphUrl || DEFAULT_IDIOMORPH_URL;
        var sink = createChangeSink(function () {
            if (typeof window.Idiomorph === 'object') {
                return Promise.resolve();
            }
            return loadScript(idiomorphUrl);
        });

        function start() {
            if (mode === 'shared_worker') {
                var workerUrl = config.workerUrl || '/_nowo/hot-reload/shared-worker.js';
                var shared = createSharedWorkerClient(config, sink, workerUrl);
                if (shared) {
                    shared.connect();
                    document.addEventListener('visibilitychange', function () {
                        if (document.visibilityState === 'visible') {
                            shared.connect();
                            sink.flushPending();
                        }
                    });
                    return;
                }
                mode = 'visibility';
            }

            if (mode === 'always') {
                var alwaysClient = createVisibilityClient(config, sink, false);
                alwaysClient.connect();
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') {
                        sink.flushPending();
                    }
                });
                return;
            }

            // visibility (default fallback)
            var visibilityClient = createVisibilityClient(config, sink, true);
            function syncConnection() {
                if (document.visibilityState === 'visible') {
                    visibilityClient.connect();
                    sink.flushPending();
                } else {
                    visibilityClient.disconnect();
                }
            }
            syncConnection();
            document.addEventListener('visibilitychange', syncConnection);
        }

        if (document.readyState === 'complete') {
            start();
        } else {
            window.addEventListener('load', start);
        }
    }

    init();
})();
