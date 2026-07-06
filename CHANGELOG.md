# Changelog

All notable changes to this project are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

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
