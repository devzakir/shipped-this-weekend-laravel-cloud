# Shipped This Weekend

A public gallery for weekend apps built on **Laravel Cloud**. Paste a `*.laravel.cloud` URL, it auto-enriches into a card (title, description, live screenshot), and the community upvotes the best ones.

Login-free and frictionless by design — no account needed to submit or vote.

## How it works

1. **Submit** — paste a live `*.laravel.cloud` URL on `/submit`.
2. **Enrich** — a queued job hits [Microlink](https://microlink.io) to pull the page title, OG description, and a live screenshot.
3. **Vote** — visitors upvote entries. Browse by **Top** or **New**.
4. **Moderate** — abusive entries are hidden via a signed admin link (no admin UI to protect).

## Stack

- **Laravel 12** (framework 13.x) + **Inertia** + **React 19** + **Tailwind 4** — official Laravel React starter kit
- **SQLite** for local dev, **Postgres** in production (Laravel Cloud)
- **Managed queue** for the `EnrichEntryJob` enrichment worker
- **Microlink API** for URL metadata + screenshots

## Local setup

```bash
git clone https://github.com/devzakir/shipped-this-weekend-laravel-cloud.git
cd shipped-this-weekend-laravel-cloud

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# terminal 1
php artisan queue:work
# terminal 2
composer dev   # serves app + vite
```

Set `MICROLINK_API_KEY` in `.env` for enrichment (falls back to the free tier if unset — rate-limited).

## Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/` | Gallery (Top / New) |
| GET | `/submit` | Submit form |
| POST | `/entries` | Create entry (throttled 10/min) |
| POST | `/entries/{entry}/vote` | Upvote (throttled 30/min) |
| GET | `/admin/entries/{entry}/hide` | Signed hide link |

## Testing

```bash
php artisan test
```

## License

MIT
