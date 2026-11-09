# Changelog

All notable changes to this project are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.4.0]

### Added

- **Abuse limits for the public heartbeat endpoint.**
  - Heartbeats sent from other websites are rejected (`Sec-Fetch-Site:
    cross-site`, or an `Origin` from another host).
  - `maxPerIp` option (default `3`): rotating the User-Agent can no longer
    create unlimited dots from one IP.
  - `maxVisitors` option (default `100`): caps the presence store in total.
  - Visitors already present always refresh. Rejected heartbeats return
    `{"ok": false}` and don't rewrite the presence file.

### Changed

- Presence entries also store a salted hash of the IP alone (same daily salt),
  which the per-IP limit needs. Raw IPs are still never stored.
- `PresenceStore` takes the limits in its constructor, and `heartbeat()`
  takes the IP hash and returns whether the heartbeat was accepted.

## [1.3.0]

### Changed

- **The public API no longer reveals where visitors are.** It used to return
  each visitor's current page together with an id that stayed the same all
  day, so anyone polling `GET /api/live-visitors` could follow a visitor
  through the site. Each `presence` entry now only has a random `id`, which
  lives only while the visitor stays present. A visitor who returns later gets
  a new one. Dot tooltips read "Browsing".
- **Pages are no longer sent or stored.** The heartbeat has an empty body and
  the presence file keeps only the salted hash, the random id and a timestamp.
  Request bodies from older widgets (e.g. in cached pages) are ignored.

### Upgrade notes

- `presence` entries in the `GET /api/live-visitors` response no longer have
  a `page` key. Only relevant if you built your own frontend on this endpoint.
- `PresenceStore::heartbeat()` now only takes the token.

## [1.2.1]

### Fixed

- **The Plausible response is now actually cached.** The plugin never registered
  its cache, so Kirby used a no-op cache and every poll from every tab called
  Plausible directly, quickly exhausting the Stats API rate limit.
- **Polls no longer see an empty presence list during a heartbeat.** Reads now
  take a shared lock, so they can't land between the file being truncated and
  rewritten.
- **A malformed heartbeat can no longer wipe all presence data.** The `page`
  value must be a URL path, and the file is only rewritten after the new
  content was encoded successfully.
- The dot enter animation plays again. The random delay that offsets the pulse
  was also delaying the enter and exit animations.
- Dots for Plausible-only visitors keep their colour when the mix of locations
  changes.
- A visitor returning while their dot was still fading out gets a fresh dot.
- Removed the `curl_close()` call, which is deprecated in PHP 8.5.

### Changed

- Polling pauses while the tab is hidden. Returning to the tab sends a heartbeat
  and refreshes immediately.
- The heartbeat interval is derived from `presenceTtl` (`presenceTtl / 2`,
  minimum 5 seconds; 15s with the default), and expired entries are removed
  after `presenceTtl` instead of a fixed 120 seconds.

## [1.2.0]

### Changed

- **Only *active* visitors are counted.** The heartbeat now fires only while the
  tab is visible and the visitor has interacted within `activeTimeout` seconds
  (default 60). Idle, backgrounded, and kiosk/pinned tabs stop counting, so the
  live count reflects engaged visitors rather than any open tab.

### Added

- `activeTimeout` option (seconds, default `60`) to tune how long after the last
  interaction a tab is still considered active.

## [1.1.0]

### Changed

- **Presence identity is now derived server-side.** The heartbeat no longer sends
  a client-generated token, and the frontend no longer uses `sessionStorage`.
  The server computes a stable, non-reversible id from
  `sha256(daily_random_salt | IP | User-Agent)`. Raw IP/User-Agent are never
  stored and the salt rotates daily.

### Fixed

- **Multiple tabs/reloads no longer inflate the count.** Because the presence id
  is derived from IP + User-Agent instead of a per-tab token, all tabs from the
  same visitor now collapse into a single presence entry.

### Notes

- No configuration changes required; the update is transparent to consumers.
- Docs updated to document the presence layer and clarify the privacy/GDPR model.

## [1.0.0]

- Initial release: live visitor widget backed by Plausible realtime data with a
  client-token presence layer.
