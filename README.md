# Kirby Live Visitors

Display live visitor presence on your Kirby site using real-time data from [Plausible Analytics](https://plausible.io).

Shows individual visual elements (dots) for each live visitor, with location-based coloring and smooth enter/exit animations. Fully GDPR-compliant — no cookies, no personal data.

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

## Configuration Options

| Option | Default | Description |
| --- | --- | --- |
| `apiKey` | `null` | **(Required)** Plausible Stats API key |
| `siteId` | auto-detected | Plausible site domain. Defaults to the Kirby site hostname |
| `baseUrl` | `https://plausible.io` | Plausible instance URL. Change for self-hosted |
| `dateRange` | `realtime` | Plausible date range. `realtime` = last 5 minutes |
| `interval` | `30` | Frontend polling interval in seconds |
| `cacheTtl` | `1` | Server-side cache TTL in minutes |
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

```
Browser ──poll──> Kirby API route ──proxy──> Plausible /api/v2/query (realtime)
                       │
                  Server-side cache (1 min TTL)
                       │
                  Returns aggregate visitor counts
                  by configured dimensions
                       │
              Frontend JS expands counts into
              individual visual elements
```

1. The plugin registers a Kirby API route at `/api/live-visitors`
2. This route proxies requests to Plausible's Stats API v2 with `date_range: "realtime"`
3. Responses are cached server-side (configurable TTL) to stay within Plausible's rate limits
4. Frontend JavaScript polls the Kirby endpoint and renders one visual element per visitor
5. Visitors are grouped by dimensions (country, city, etc.) — the aggregate counts are expanded into individual DOM elements

## GDPR Compliance

This plugin is **fully GDPR-compliant** by design:

### No Cookies

The plugin does not set any cookies. No consent banner required.

### No Personal Data

All data comes from Plausible Analytics, which is privacy-first:

- Plausible does not use cookies or fingerprinting
- Plausible does not collect or store any personal data or PII
- No cross-site or cross-device tracking
- All data is aggregate (visitor counts by dimension), never individual

The Kirby API route only passes through aggregate statistics. No IP addresses, user agents, or identifiers are exposed.

### Data Flow

- **Plausible → Kirby**: Aggregate counts (e.g., "3 visitors from Zurich")
- **Kirby → Browser**: Same aggregate counts, transformed for display
- **No data stored**: Only a short-lived server cache (configurable, default 1 minute)

### Legal Basis

No legal basis needed under GDPR — the plugin processes no personal data. Plausible Analytics itself operates under legitimate interest without requiring consent (confirmed by multiple EU DPAs).

### Self-Hosted Plausible

If using self-hosted Plausible, the same privacy guarantees apply. Data never leaves your infrastructure.

## Rate Limits

Plausible's Stats API has a default rate limit of **600 requests per hour**. With the default 1-minute server-side cache, the plugin makes at most **60 requests per hour** — well within limits regardless of how many visitors are polling the frontend.

## License

MIT
