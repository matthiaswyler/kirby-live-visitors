(function () {
    'use strict';

    var container = document.getElementById('live-visitors');
    if (!container) return;

    var apiUrl   = container.dataset.api;
    var interval = (parseInt(container.dataset.interval, 10) || 30) * 1000;
    var dotsEl  = container.querySelector('.live-visitors__dots');
    var countEl = container.querySelector('.live-visitors__count');
    var current = [];

    // --- Heartbeat ---

    var token = sessionStorage.getItem('lv-token');
    if (!token) {
        token = Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
        sessionStorage.setItem('lv-token', token);
    }

    function heartbeat() {
        navigator.sendBeacon
            ? navigator.sendBeacon(
                  apiUrl + '/heartbeat',
                  new Blob(
                      [JSON.stringify({ token: token, page: location.pathname })],
                      { type: 'application/json' }
                  )
              )
            : fetch(apiUrl + '/heartbeat', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ token: token, page: location.pathname }),
                  keepalive: true,
              }).catch(function () {});
    }

    heartbeat();
    setInterval(heartbeat, 15000);

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
                label: s.page || 'Browsing',
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
            for (var e = 0; e < extra; e++) {
                var entry = geoPool[e] || {};
                var label = [entry.city, entry.country].filter(Boolean).join(', ') || 'Visitor';
                items.push({
                    id:    'geo-' + e,
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
                dot.style.animationDelay = (Math.random() * 2).toFixed(2) + 's';
                dotsEl.appendChild(dot);
            }
        });

        countEl.textContent = total > 0 ? total + ' live' : '';
        container.classList.toggle('live-visitors--active', total > 0);

        current = items;
    }

    function poll() {
        fetch(apiUrl, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error(res.status);
                return res.json();
            })
            .then(function (data) { render(data); })
            .catch(function () {})
            .finally(function () { setTimeout(poll, interval); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', poll);
    } else {
        poll();
    }
})();
