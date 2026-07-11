# Shipped This Weekend

A public gallery for weekend apps built on **Laravel Cloud**. Paste a `*.laravel.cloud` URL, it auto-enriches into a card (title, live screenshot), and the community upvotes the best ones.

Login-free and frictionless by design — no account needed to submit or vote.

## How it works

1. **Submit** — paste a live `*.laravel.cloud` URL on `/submit`. The entry is born live immediately.
2. **Enrich** — a queued job self-parses the page's OG metadata (title, `og:image`) and generates a screenshot once via [thum.io](https://www.thum.io), then **self-stores** the image. No paid API, no per-view cost — enrichment is non-fatal, so cards always render.
3. **Vote** — visitors upvote entries. Browse by **Top** or **New**.
4. **Moderate** — abusive entries are hidden via a signed admin link (no admin UI to protect).

## Stack

- **Laravel** (framework 13.x) + **Inertia** + **React 19** + **Tailwind 4** — official Laravel React starter kit
- **SQLite** for local dev, **Cloudflare D1** in production (via `ntanduy/cloudflare-d1-database`, REST mode)
- **Database queue** for the `EnrichEntryJob` enrichment worker
- **thum.io** for one-time screenshot generation; OG metadata parsed in-app (no key)

## Local setup

```bash
git clone https://github.com/devzakir/shipped-this-weekend-laravel-cloud.git
cd shipped-this-weekend-laravel-cloud

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link   # serve self-stored screenshots from the public disk

# terminal 1
php artisan queue:work
# terminal 2
composer dev   # serves app + vite
```

Local dev uses SQLite and the `public` filesystem disk — no API keys required. `SCREENSHOT_ENDPOINT` defaults to `https://image.thum.io`.

## Production (Laravel Cloud + Cloudflare D1)

The database is **Cloudflare D1**, accessed over its REST API — local stays SQLite, D1 is production-only. Set these in the Laravel Cloud environment:

```
DB_CONNECTION=d1
CLOUDFLARE_TOKEN=            # Cloudflare API token with D1:Edit
CLOUDFLARE_ACCOUNT_ID=
CLOUDFLARE_D1_DATABASE_ID=

SESSION_DRIVER=cookie        # keep sessions/cache off D1 (avoids per-request HTTP)
CACHE_STORE=file
QUEUE_CONNECTION=database     # jobs table on D1 + a queue:work worker

# Screenshots persist on Cloudflare R2 (S3-compatible) so they survive
# redeploys and are shared across instances.
SCREENSHOT_DISK=r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=                 # https://<account_id>.r2.cloudflarestorage.com
R2_PUBLIC_URL=               # bucket public r2.dev URL or custom domain
```

Migrations run over the REST connection:

```bash
php artisan migrate --database=d1 --force
```

Deploys are push-to-deploy on `main`. A `queue:work` background worker processes `EnrichEntryJob`.

> **Screenshots:** stored on **Cloudflare R2** in production (`SCREENSHOT_DISK=r2`, see the `r2` disk in `config/filesystems.php`) for durable, cross-instance storage. Local dev uses the `public` disk. Enrichment is non-fatal, so a card renders even if a screenshot is missing (it falls back to the parsed `og:image`).

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
