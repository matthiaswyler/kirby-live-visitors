(function () {
    'use strict';

    var container = document.getElementById('live-visitors');
    if (!container) return;

    var apiUrl            = container.dataset.api;
    var interval          = (parseInt(container.dataset.interval, 10) || 30) * 1000;
    var heartbeatInterval = (parseInt(container.dataset.heartbeat, 10) || 15) * 1000;
    var activeTimeout     = (parseInt(container.dataset.activeTimeout, 10) || 60) * 1000;
    var dotsEl  = container.querySelector('.live-visitors__dots');
    var countEl = container.querySelector('.live-visitors__count');
    var current = [];

    // --- Heartbeat ---
    // Counts *active* visitors only. A heartbeat is sent solely when the tab is
    // visible and the visitor has interacted within `activeTimeout`. This keeps
    // idle/background/kiosk tabs from inflating the count. The heartbeat
    // carries no data, and no client-side identifier is stored. The server
    // derives a non-reversible presence id from IP + User-Agent (salted,
    // rotated daily).

    var lastActive = Date.now();

    function markActive() {
        lastActive = Date.now();
    }

    ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart', 'wheel'].forEach(function (evt) {
        addEventListener(evt, markActive, { passive: true });
    });

    function isVisible() {
        return document.visibilityState === 'visible';
    }

    function isActive() {
        return isVisible() && (Date.now() - lastActive) <= activeTimeout;
    }

    function heartbeat() {
        if (!isActive()) return;

        navigator.sendBeacon
            ? navigator.sendBeacon(apiUrl + '/heartbeat')
            : fetch(apiUrl + '/heartbeat', {
                  method: 'POST',
                  credentials: 'same-origin',
                  keepalive: true,
              }).catch(function () {});
    }

    heartbeat();
    setInterval(heartbeat, heartbeatInterval);

    // --- Display ---

    function hashHue(str) {
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return Math.abs(hash % 360);
    }

    function render(data) {
        var presence       = data.presence || [];
        var geo            = data.geo || [];
        var plausibleTotal = data.total || 0;

        var items = presence.map(function (s) {
            return {
                id:    s.id,
                label: 'Browsing',
                hue:   hashHue(s.id),
            };
        });

        var extra = plausibleTotal - items.length;
        if (extra > 0) {
            var geoPool = [];
            for (var g = 0; g < geo.length; g++) {
                for (var c = 0; c < geo[g].count; c++) {
                    geoPool.push(geo[g]);
                }
            }
            // Ids are keyed on the location (not the position in the list),
            // so a dot keeps its colour when the mix of locations changes.
            var seen = {};
            for (var e = 0; e < extra; e++) {
                var entry = geoPool[e] || {};
                var label = [entry.city, entry.country].filter(Boolean).join(', ') || 'Visitor';
                seen[label] = (seen[label] || 0) + 1;
                items.push({
                    id:    'geo-' + label + '-' + seen[label],
                    label: label,
                    hue:   hashHue(entry.country || 'anon'),
                });
            }
        }

        var total  = Math.max(items.length, plausibleTotal);
        var oldIds = {};
        var newIds = {};

        current.forEach(function (v) { oldIds[v.id] = true; });
        items.forEach(function (v) { newIds[v.id] = true; });

        current.forEach(function (v) {
            if (!newIds[v.id]) {
                var dot = dotsEl.querySelector('[data-vid="' + CSS.escape(v.id) + '"]');
                if (dot) {
                    // Drop the id so a visitor who returns while this dot is
                    // still fading out (or hidden) gets a fresh dot.
                    dot.removeAttribute('data-vid');
                    dot.classList.remove('live-visitors__dot--enter');
                    dot.classList.add('live-visitors__dot--exit');
                    dot.addEventListener('animationend', function () { dot.remove(); }, { once: true });
                }
            }
        });

        items.forEach(function (v) {
            var existing = dotsEl.querySelector('[data-vid="' + CSS.escape(v.id) + '"]');
            if (existing) {
                existing.title = v.label;
            } else {
                var dot = document.createElement('span');
                dot.className = 'live-visitors__dot live-visitors__dot--enter';
                dot.dataset.vid = v.id;
                dot.title = v.label;
                dot.style.setProperty('--dot-hue', v.hue);
                dot.style.setProperty('--dot-delay', (Math.random() * 2).toFixed(2) + 's');
                dotsEl.appendChild(dot);
            }
        });

        countEl.textContent = total > 0 ? total + ' live' : '';
        container.classList.toggle('live-visitors--active', total > 0);

        current = items;
    }

    // Polls only while the tab is visible. Hidden tabs would otherwise keep
    // hitting the API for a widget nobody is looking at.
    var pollTimer = null;
    var polling   = false;

    function poll() {
        clearTimeout(pollTimer);
        if (polling) return;
        polling = true;

        fetch(apiUrl, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error(res.status);
                return res.json();
            })
            .then(function (data) { render(data); })
            .catch(function () {})
            .finally(function () {
                polling = false;
                if (isVisible()) pollTimer = setTimeout(poll, interval);
            });
    }

    // Coming back to the tab counts as activity: announce presence and
    // refresh right away instead of waiting for the next tick.
    document.addEventListener('visibilitychange', function () {
        if (isVisible()) {
            markActive();
            heartbeat();
            poll();
        } else {
            clearTimeout(pollTimer);
        }
    });

    function start() {
        if (isVisible()) poll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
