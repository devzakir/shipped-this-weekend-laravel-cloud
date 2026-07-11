# Shipped This Weekend — Design

**Date:** 2026-07-11
**Goal:** Win Taylor Otwell's "best side project shipped on Laravel Cloud this weekend" contest by building the tool the contest itself needs — a public gallery of every weekend entry. Optimized for delight + virality in the Laravel/dev crowd, not for JuggleHire.

## The bet

The winning move is to *sell shovels during the gold rush*. Instead of competing as one of hundreds of entries, build the one page Taylor needs to review entries and every other participant wants to be listed on. Taylor has structural reason to see it; every entrant has structural reason to share it.

## What it is

A single-purpose public gallery. Paste your `laravel.cloud` URL → we auto-enrich it (title, OG image, live screenshot) → it appears as a card → the community upvotes. Two tabs: **Top** (by votes) and **New** (by recency).

The gallery lives on a `*.laravel.cloud` subdomain (`shippedthisweekend.laravel.cloud`), which makes it a valid contest entry itself. Meta.

## Stack

- Laravel 12 + Inertia + React + Tailwind (Laravel official starter kit). **Not Mantine** — Tailwind gives faster, fuller design control for a crafted one-page showcase.
- Postgres (native on Laravel Cloud).
- Managed queue (native on Laravel Cloud) for the enrich job.
- Object storage (`s3` disk) in production for self-stored screenshots.
- Deploy target: `shippedthisweekend.laravel.cloud`.

## Data model

**entries**
- `id`
- `url` (unique)
- `host` (parsed hostname, must end in `.laravel.cloud`)
- `title` (from Microlink)
- `tagline` (user-supplied, max 80 chars)
- `author_name`
- `x_handle` (nullable — for share attribution)
- `og_image_url` (nullable)
- `screenshot_url` (nullable)
- `votes_count` (denormalized cache)
- `status` (enum: `pending`, `live`, `hidden`)
- `created_at`, `updated_at`

**votes**
- `id`
- `entry_id`
- `voter_hash` (hash of IP + signed cookie)
- `created_at`
- Unique constraint on `(entry_id, voter_hash)` — short name `votes_entry_voter_unique`.

## Auto-enrich pipeline (the "wow") — fully free, no API key

Entries are born `live` on submit (instant card). `EnrichEntryJob` then, per entry, once:
1. **Fetches the page HTML** and parses metadata itself — `og:title`/`<title>` → `title`, `og:image` (resolved to absolute) → `og_image_url`. Free, unlimited, no third party. If the network fetch throws, the job re-throws so the queue retries (`$tries=3`, `$backoff=[10,30]`); parse failures are non-fatal (fall back to `host` title).
2. **Generates a screenshot once** via **thum.io** (no API key: `https://image.thum.io/get/width/1200/crop/900/{url}`), validates the response is a real PNG (magic-byte check), then **self-stores** the bytes to our own disk (`screenshots/{id}.png`) and sets `screenshot_url = Storage::url(...)`. Because we serve our own copy, gallery views are unlimited/free and thum.io's per-impression limit never bites (hit once per entry, at generation). Screenshot failure is non-fatal — card falls back to `og_image_url`.

Frontend: card shows a pulsing skeleton while `screenshot_url` is null, then swaps to the stored screenshot via polling (capped at 20 attempts). Born-live + non-fatal enrichment means a card is always visible even if the queue lags or every enrich step fails.

**Config:** `services.screenshot` → `SCREENSHOT_ENDPOINT` (default `https://image.thum.io`) + `SCREENSHOT_DISK`. Local disk = `public`; **production = `s3`** (object storage, so stored screenshots persist across ephemeral Cloud instances). No screenshot API key anywhere.

## Voting + anti-abuse

- Frictionless, no login. 1 vote per entry per `voter_hash` (hashed IP + signed cookie).
- Per-IP rate limit on the vote endpoint.
- Optimistic UI: vote count bumps instantly, reconciles on response.
- Deliberately not Fort Knox — frictionless voting = more votes = more sharing, which is the point for a weekend viral tool.

## Anti-spam on submit

- Hard rule: submitted URL hostname must end in `.laravel.cloud`. Filters most junk and keeps it on-theme.
- Server-side fetch of the URL on submit; must return HTTP 200 or it's rejected.
- Honeypot field + submit rate limit per IP.
- Default `status = live` on a clean fetch; `pending` if the fetch looks sketchy.
- One-click hide via a signed admin link (no admin UI needed for the weekend).

## Pages / UX

- **`/` (gallery):** hero line ("Shipped on Laravel Cloud this weekend."), submit button, Top/New tabs, responsive card grid. Each card = screenshot, title, tagline, author, vote button + count, link out to the entry.
- **Submit (modal or `/submit`):** URL, tagline (80 char), your name, X handle (optional). Inline validation. On success, show the user their own card + a "Share your card" button.
- **Share loop:** share button pre-fills a post with the entrant's title + gallery URL. Every entrant becomes a distributor.
- Empty state: seeded with ~10 real entries so launch isn't a blank page.

## Launch loop (why it spreads)

1. Seed ~10 real weekend entries manually.
2. Reply to Taylor's tweet with the gallery URL (which is itself a valid entry).
3. Each submitter gets a pre-filled "share your card" CTA linking their entry + the gallery.

## Testing

- Feature test: submit valid `.laravel.cloud` URL → entry created, enrich job dispatched.
- Feature test: submit non-`laravel.cloud` URL → rejected.
- Feature test: submit URL returning non-200 → rejected.
- Feature test: vote once → count increments; vote twice same `voter_hash` → blocked by unique constraint.
- Feature test: honeypot filled → rejected.
- Unit test: `EnrichEntryJob` with faked Microlink HTTP response populates title/og/screenshot; faked failure leaves entry live without screenshot.
- Pest, `Http::fake()` for Microlink.

## Explicitly out of scope (YAGNI for the weekend)

- User accounts / auth.
- Comments.
- Categories / search.
- Admin dashboard (signed hide link is enough).
- Editing an entry after submit.
