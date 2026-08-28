/**
 * Same-origin SharedWorker for FrankenPHP hot reload (dev only).
 *
 * Holds a single Mercure EventSource and fans out file-change events to every
 * connected tab, avoiding HTTP/1.1 per-tab connection slot exhaustion.
 *
 * Served at /_nowo/hot-reload/shared-worker.js by HotReloadAssetSubscriber.
 */
/* eslint-disable no-var -- classic SharedWorker scope */
'use strict';

/** @type {EventSource|null} */
var eventSource = null;
/** @type {string|null} */
var mercureUrl = null;
/** @type {Set<MessagePort>} */
var ports = new Set();

/**
 * @param {MessagePort} except
 * @param {object} message
 */
function broadcast(except, message) {
    ports.forEach(function (port) {
        if (port === except) {
            return;
        }

        try {
            port.postMessage(message);
        } catch (e) {
            ports.delete(port);
        }
    });
}

/**
 * @param {object} message
 */
function broadcastAll(message) {
    broadcast(null, message);
}

function closeEventSource() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
}

function ensureEventSource() {
    if (!mercureUrl || eventSource) {
        return;
    }

    try {
        eventSource = new EventSource(mercureUrl);
    } catch (error) {
        broadcastAll({
            type: 'error',
            message: error && error.message ? error.message : 'EventSource failed',
        });

        return;
    }

    eventSource.onmessage = function (event) {
        broadcastAll({ type: 'file-changes', data: event.data });
    };

    eventSource.onerror = function () {
        closeEventSource();
        broadcastAll({ type: 'error', message: 'Mercure EventSource error' });

        // Soft reconnect: browsers fire onerror during transient drops.
        if (ports.size > 0 && mercureUrl) {
            setTimeout(ensureEventSource, 1500);
        }
    };

    broadcastAll({ type: 'ready' });
}

/**
 * @param {MessagePort} port
 */
function detachPort(port) {
    ports.delete(port);

    if (ports.size === 0) {
        closeEventSource();
        mercureUrl = null;
    }
}

self.onconnect = function (event) {
    var port = event.ports[0];
    ports.add(port);

    port.onmessage = function (messageEvent) {
        var payload = messageEvent.data;

        if (!payload || typeof payload !== 'object') {
            return;
        }

        if (payload.type === 'hello' && typeof payload.mercureUrl === 'string') {
            mercureUrl = payload.mercureUrl;
            ensureEventSource();
            port.postMessage({ type: 'ready' });

            return;
        }

        if (payload.type === 'bye') {
            detachPort(port);
        }
    };

    port.onmessageerror = function () {
        detachPort(port);
    };

    port.start();
};
