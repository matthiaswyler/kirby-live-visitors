# Kirby Live Visitors

Display live visitor presence on your Kirby site using real-time data from [Plausible Analytics](https://plausible.io), combined with a lightweight server-side presence layer.

Shows individual visual elements (dots) for each live visitor, with location-based coloring and smooth enter/exit animations. Privacy-first by design — no cookies, no client-side storage, no personal data stored at rest.

## Requirements

- Kirby 5+ (tested with Kirby 5)
- PHP 8.1+
- Plausible Analytics account with a **Stats API key**

## Installation

### Download

Download and copy this folder to `site/plugins/kirby-live-visitors`.

### Git submodule

```bash
git submodule add https://github.com/matthiaswyler/kirby-live-visitors.git site/plugins/kirby-live-visitors
```

### Composer

```bash
composer require matthiaswyler/kirby-live-visitors
```

## Setup

### 1. Get a Plausible Stats API Key

1. Log into your Plausible account
2. Go to **Settings → API Keys**
3. Create a new **Stats API** key

### 2. Configure the Plugin

Add to your `site/config/config.php`:

```php
'matthiaswyler.live-visitors' => [
    'apiKey' => 'your-plausible-stats-api-key',
],
```

The `siteId` defaults to your site's hostname. Override it if your Plausible domain differs:

```php
'matthiaswyler.live-visitors' => [
    'apiKey' => 'your-plausible-stats-api-key',
    'siteId' => 'example.com',
],
```

### 3. Add the Snippet

Include the snippet in your template or layout, ideally before `</body>`:

```php
<?php snippet('live-visitors') ?>
```

The snippet renders nothing for logged-in Panel users or on the error page.

## Configuration Options

| Option | Default | Description |
| --- | --- | --- |
| `apiKey` | `null` | **(Required)** Plausible Stats API key |
| `siteId` | auto-detected | Plausible site domain. Defaults to the Kirby site hostname |
| `baseUrl` | `https://plausible.io` | Plausible instance URL. Change for self-hosted |
| `interval` | `30` | Frontend polling interval in seconds |
| `cacheTtl` | `1` | Server-side Plausible cache TTL in minutes |
| `presenceTtl` | `30` | Seconds a heartbeat keeps a visitor "present" |
| `dimensions` | `['visit:country_name', 'visit:city_name']` | Plausible dimensions for visitor breakdown |

### Available Dimensions

You can configure which Plausible dimensions to query:

- `visit:country_name` — Visitor country
- `visit:city_name` — Visitor city
- `visit:region_name` — Visitor region
- `event:page` — Current page path
- `visit:device` — Device type (Desktop, Mobile, Tablet)
- `visit:browser` — Browser name
- `visit:os` — Operating system

Example with more dimensions:

```php
'matthiaswyler.live-visitors' => [
    'apiKey'     => 'your-key',
    'dimensions' => ['visit:country_name', 'visit:city_name', 'event:page'],
],
```

### Self-Hosted Plausible

```php
'matthiaswyler.live-visitors' => [
    'apiKey'  => 'your-key',
    'baseUrl' => 'https://analytics.example.com',
],
```

## Styling

The widget uses CSS custom properties for easy theming. Override them in your stylesheet:

```css
.live-visitors {
    --lv-bottom: 1rem;
    --lv-left: 1rem;
    --lv-bg: rgba(0, 0, 0, 0.05);
    --lv-bg-blur: 12px;
    --lv-radius: 100px;
    --lv-padding: 0.5rem 0.875rem;
    --lv-gap: 0.375rem;
    --lv-dot-size: 0.5rem;
    --lv-font-size: 0.6875rem;
    --lv-color: rgba(0, 0, 0, 0.5);
}
```

Dark mode is supported automatically via `prefers-color-scheme`. Override for custom dark mode:

```css
[data-theme="dark"] .live-visitors {
    --lv-bg: rgba(255, 255, 255, 0.08);
    --lv-color: rgba(255, 255, 255, 0.5);
}
```

## How It Works

The plugin combines two sources into a single live count:

```
                    ┌── Plausible /api/v2/query (realtime) ──┐
Browser ──poll──> Kirby API route                            │
   │                    │  (server-side cache, 1 min TTL)    │
   │                    └── aggregate counts by dimension ───┘
   │
   └──heartbeat──> Kirby presence store (salted IP+UA hash)
                        │  short-lived JSON, TTL-gated
                        └── one entry per visitor (not per tab)

           total = max(plausibleRealtime, activePresence)
```

1. **Plausible realtime** — the route proxies Plausible's Stats API v2 (`date_range: "realtime"`, last 5 min) and caches the aggregate response server-side to respect rate limits.
2. **Presence** — the frontend sends a small `heartbeat` every 15s. The server derives a presence id and keeps the visitor "present" for `presenceTtl` seconds. This gives immediate, per-visitor dots even before Plausible's realtime figures update.
3. The reported total is `max()` of the two, so a visitor is never double-counted.

### Presence Identity (no client storage)

The heartbeat carries **no client-side identifier**. Instead, the server derives a stable presence id:

```
id = sha256( daily_random_salt | IP | User-Agent )   (truncated)
```

- Raw IP addresses and User-Agent strings are **never stored** — only the truncated hash is written to the presence file.
- The salt is **random and rotates every day**, so ids are non-reversible and cannot be correlated across days.
- Because the id is derived from IP + User-Agent, multiple tabs and reloads from the same visitor collapse into a **single** presence entry (this fixes the common "one visitor counted per tab" inflation).
- Known bots/crawlers are filtered out by User-Agent before any processing.

This is the same salted-hash technique Plausible uses for its own visitor counting.

## Privacy & GDPR

This plugin is designed to be deployable **without a consent banner**:

### No Cookies, No Client-Side Storage

The plugin sets no cookies and writes nothing to `localStorage` or `sessionStorage`. Since nothing is stored on or read from the visitor's device for this feature, the ePrivacy Directive's consent requirement for device storage does not apply.

### Personal Data

- **Plausible data** is fully aggregate (e.g. "3 visitors from Zurich") and privacy-first — Plausible uses no cookies, no fingerprinting, and stores no PII.
- **Presence** briefly processes the visitor's IP and User-Agent server-side only to derive a **non-reversible, daily-rotating hash**. The raw IP and User-Agent are never persisted. Only the truncated hash, the current page path, and a timestamp are stored, in a short-lived JSON file that is purged automatically (default: entries expire after 30s of inactivity and are pruned within 120s).

Under GDPR, this transient processing to produce a pseudonymised, non-reversible identifier — with no cross-day correlation and no cookies — rests on **legitimate interest** (showing live presence), consistent with the widely accepted legal basis for privacy-first analytics like Plausible.

> Not legal advice. If your jurisdiction or DPA takes a stricter view, you can omit the snippet or reduce `presenceTtl`.

### Data Flow

- **Plausible → Kirby**: aggregate counts by dimension
- **Browser → Kirby**: a heartbeat containing only the current page path (no identifier)
- **Stored at rest**: a short-lived server cache (Plausible response) and a TTL-gated presence file (salted hash + page + timestamp)

### Self-Hosted Plausible

If using self-hosted Plausible, the same guarantees apply and data never leaves your infrastructure.

## Rate Limits

Plausible's Stats API has a default rate limit of **600 requests per hour**. With the default 1-minute server-side cache, the plugin makes at most **60 requests per hour** — well within limits regardless of how many visitors are polling the frontend.

## License

MIT
