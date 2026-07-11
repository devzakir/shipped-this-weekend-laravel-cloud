# Shipped This Weekend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A single-page public gallery where anyone pastes a `*.laravel.cloud` URL, it auto-enriches (title + live screenshot) into a card, and the community upvotes — Top/New tabs.

**Architecture:** Laravel 12 + Inertia + React (official React starter kit) + Tailwind. Postgres. Submit → validate → persist `pending`/`live` → dispatch `EnrichEntryJob` on the managed queue → Microlink API fills title/OG/screenshot → card flips from skeleton to real shot via Inertia polling. Voting is login-free, deduped by `voter_hash` (hashed IP + signed cookie).

**Tech Stack:** Laravel 12, Inertia 2, React 19, Tailwind 4, Postgres, Laravel queue (database driver local, managed queue on Cloud), Microlink HTTP API, Pest.

## Global Constraints

- **Deploy target:** `shippedthisweekend.laravel.cloud`.
- **DB portability:** Local dev + tests use SQLite (zero-setup, matches Zakir's standing workflow); **production is Postgres** on Laravel Cloud. Therefore all schema goes through Eloquent migrations that must be Postgres-safe — no SQLite-only assumptions, and any long index/unique name MUST pass an explicit short name ≤ 64 chars. Never treat "passes on SQLite" as proof it's prod-safe.
- **Hostname hard rule:** submitted URL hostname MUST end in `.laravel.cloud` — reject otherwise.
- **Unique vote constraint name:** `votes_entry_voter_unique`.
- **Inertia rule:** controllers hit by Inertia forms return `redirect()->back()` (never `response()->json()`); pass data via `->with('flash', [...])`.
- **Microlink:** free tier ~50 req/day. Key optional via env `MICROLINK_API_KEY`. Config under `config/services.php` → `services.microlink`. Launch-day ops note (NOT code): line up a paid tier before announcing.
- **Copy:** hero line is exactly `Shipped on Laravel Cloud this weekend.`
- **Out of scope (do not build):** auth, comments, categories/search, admin dashboard UI, editing entries.
- **Commits:** frequent, one per task minimum. No `Co-Authored-By` trailers.

---

### Task 1: Scaffold Laravel 12 + React starter kit

**Files:**
- Create: entire Laravel app at repo root (installer writes into `.`).
- Modify: `.env`, `.env.example`, `config/database.php` (default connection), `config/queue.php` (default), `config/services.php`.

**Interfaces:**
- Produces: a booting Laravel 12 app with Inertia+React+Tailwind, Postgres default connection, `database` queue default, Pest installed. Route file `routes/web.php`. React entry at `resources/js/app.tsx`, pages under `resources/js/pages/`.

- [ ] **Step 1: Scaffold into repo root**

The repo already contains `docs/` and `.git/`. Install into a temp dir then move, to avoid the installer refusing a non-empty dir.

```bash
cd /Users/devzakir/Dev
laravel new stw-tmp --react --pest --no-interaction
# move app files into the existing repo (preserve docs/ and .git/)
rsync -a --exclude='.git' stw-tmp/ shipped-this-weekend/
rm -rf stw-tmp
cd shipped-this-weekend
```

- [ ] **Step 2: Install JS deps and verify it builds**

```bash
npm install
npm run build
```
Expected: Vite build completes with no errors.

- [ ] **Step 3: Configure SQLite (local) + queue in `.env`**

Local uses SQLite. Create the db file and set `.env`:

```bash
touch database/database.sqlite
```

```
APP_NAME="Shipped This Weekend"
DB_CONNECTION=sqlite
# (leave DB_HOST/PORT/DATABASE/USERNAME/PASSWORD commented out — SQLite ignores them)
QUEUE_CONNECTION=database
MICROLINK_API_KEY=
```

`config/database.php` default stays `sqlite` locally. **Production (Laravel Cloud) overrides `DB_CONNECTION=pgsql` via its own env** — do not hardcode pgsql anywhere. Mirror the non-secret keys into `.env.example`.

- [ ] **Step 4: Register Microlink config**

In `config/services.php`, add inside the returned array:

```php
'microlink' => [
    'key' => env('MICROLINK_API_KEY'),
    'endpoint' => env('MICROLINK_ENDPOINT', 'https://api.microlink.io'),
],
```

- [ ] **Step 5: Create queue + migrate**

```bash
php artisan queue:table
php artisan migrate
```
Expected: migrations run against Postgres with no errors.

- [ ] **Step 6: Verify app boots**

```bash
php artisan test
```
Expected: default starter tests PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 12 + React starter kit with Postgres and queue"
```

---

### Task 2: Migrations + Eloquent models

**Files:**
- Create: `database/migrations/XXXX_create_entries_table.php`
- Create: `database/migrations/XXXX_create_votes_table.php`
- Create: `app/Models/Entry.php`
- Create: `app/Models/Vote.php`
- Test: `tests/Feature/EntryModelTest.php`

**Interfaces:**
- Produces:
  - `Entry` model, table `entries`, fillable/casts, `votes(): HasMany`, `casts status` to `App\Enums\EntryStatus`.
  - `Vote` model, table `votes`, belongsTo `Entry`.
  - Enum `App\Enums\EntryStatus` with cases `Pending`, `Live`, `Hidden` (string values `pending`,`live`,`hidden`).
  - Unique constraint on votes `(entry_id, voter_hash)` named `votes_entry_voter_unique`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/EntryModelTest.php`:

```php
<?php

use App\Enums\EntryStatus;
use App\Models\Entry;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an entry with defaults and casts status', function () {
    $entry = Entry::create([
        'url' => 'https://demo.laravel.cloud',
        'host' => 'demo.laravel.cloud',
        'tagline' => 'A demo',
        'author_name' => 'Zakir',
        'status' => EntryStatus::Pending,
    ]);

    expect($entry->status)->toBe(EntryStatus::Pending)
        ->and($entry->votes_count)->toBe(0);
});

it('enforces unique url', function () {
    Entry::create(['url' => 'https://a.laravel.cloud', 'host' => 'a.laravel.cloud', 'tagline' => 't', 'author_name' => 'z', 'status' => EntryStatus::Live]);
    Entry::create(['url' => 'https://a.laravel.cloud', 'host' => 'a.laravel.cloud', 'tagline' => 't', 'author_name' => 'z', 'status' => EntryStatus::Live]);
})->throws(Illuminate\Database\QueryException::class);

it('blocks duplicate vote for same entry and voter_hash', function () {
    $entry = Entry::create(['url' => 'https://b.laravel.cloud', 'host' => 'b.laravel.cloud', 'tagline' => 't', 'author_name' => 'z', 'status' => EntryStatus::Live]);
    Vote::create(['entry_id' => $entry->id, 'voter_hash' => 'hash1']);
    Vote::create(['entry_id' => $entry->id, 'voter_hash' => 'hash1']);
})->throws(Illuminate\Database\QueryException::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EntryModelTest`
Expected: FAIL (class `App\Enums\EntryStatus` / `App\Models\Entry` not found).

- [ ] **Step 3: Create the enum**

`app/Enums/EntryStatus.php`:

```php
<?php

namespace App\Enums;

enum EntryStatus: string
{
    case Pending = 'pending';
    case Live = 'live';
    case Hidden = 'hidden';
}
```

- [ ] **Step 4: Create the entries migration**

`database/migrations/XXXX_create_entries_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique();
            $table->string('host')->index();
            $table->string('title')->nullable();
            $table->string('tagline', 80);
            $table->string('author_name');
            $table->string('x_handle')->nullable();
            $table->text('og_image_url')->nullable();
            $table->text('screenshot_url')->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
```

- [ ] **Step 5: Create the votes migration**

`database/migrations/XXXX_create_votes_table.php` (timestamp AFTER entries so the FK target exists):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->string('voter_hash');
            $table->timestamps();
            $table->unique(['entry_id', 'voter_hash'], 'votes_entry_voter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
```

- [ ] **Step 6: Create the models**

`app/Models/Entry.php`:

```php
<?php

namespace App\Models;

use App\Enums\EntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    protected $fillable = [
        'url', 'host', 'title', 'tagline', 'author_name',
        'x_handle', 'og_image_url', 'screenshot_url', 'votes_count', 'status',
    ];

    protected $casts = [
        'status' => EntryStatus::class,
        'votes_count' => 'integer',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
```

`app/Models/Vote.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vote extends Model
{
    protected $fillable = ['entry_id', 'voter_hash'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class);
    }
}
```

- [ ] **Step 7: Migrate + run tests**

Run: `php artisan migrate && php artisan test --filter=EntryModelTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: entries and votes schema, models, status enum"
```

---

### Task 3: EnrichEntryJob (Microlink)

**Files:**
- Create: `app/Jobs/EnrichEntryJob.php`
- Test: `tests/Feature/EnrichEntryJobTest.php`

**Interfaces:**
- Consumes: `App\Models\Entry`, `config('services.microlink')`.
- Produces: `EnrichEntryJob` (implements `ShouldQueue`), constructor `__construct(public Entry $entry)`, `public int $tries = 3;`, `public array $backoff = [10, 30];`. On success sets `title`, `og_image_url`, `screenshot_url`, `status = live`. On Microlink failure: keeps existing fields, still sets `status = live`, no screenshot.

- [ ] **Step 1: Write the failing test**

`tests/Feature/EnrichEntryJobTest.php`:

```php
<?php

use App\Enums\EntryStatus;
use App\Jobs\EnrichEntryJob;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeEntry(): Entry
{
    return Entry::create([
        'url' => 'https://demo.laravel.cloud',
        'host' => 'demo.laravel.cloud',
        'tagline' => 'demo',
        'author_name' => 'Zakir',
        'status' => EntryStatus::Pending,
    ]);
}

it('populates title, og image and screenshot from microlink', function () {
    Http::fake([
        'api.microlink.io*' => Http::response([
            'status' => 'success',
            'data' => [
                'title' => 'My Cool App',
                'image' => ['url' => 'https://cdn/og.png'],
                'screenshot' => ['url' => 'https://cdn/shot.png'],
            ],
        ], 200),
    ]);

    $entry = makeEntry();
    (new EnrichEntryJob($entry))->handle();
    $entry->refresh();

    expect($entry->title)->toBe('My Cool App')
        ->and($entry->og_image_url)->toBe('https://cdn/og.png')
        ->and($entry->screenshot_url)->toBe('https://cdn/shot.png')
        ->and($entry->status)->toBe(EntryStatus::Live);
});

it('goes live without screenshot when microlink fails', function () {
    Http::fake(['api.microlink.io*' => Http::response([], 500)]);

    $entry = makeEntry();
    (new EnrichEntryJob($entry))->handle();
    $entry->refresh();

    expect($entry->screenshot_url)->toBeNull()
        ->and($entry->status)->toBe(EntryStatus::Live);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EnrichEntryJobTest`
Expected: FAIL (`App\Jobs\EnrichEntryJob` not found).

- [ ] **Step 3: Implement the job**

`app/Jobs/EnrichEntryJob.php`:

```php
<?php

namespace App\Jobs;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class EnrichEntryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30];

    public function __construct(public Entry $entry) {}

    public function handle(): void
    {
        $key = config('services.microlink.key');
        $endpoint = config('services.microlink.endpoint');

        try {
            $response = Http::timeout(20)
                ->when($key, fn ($http) => $http->withHeaders(['x-api-key' => $key]))
                ->get($endpoint, [
                    'url' => $this->entry->url,
                    'screenshot' => 'true',
                    'meta' => 'true',
                ]);

            if ($response->successful() && $response->json('status') === 'success') {
                $data = $response->json('data', []);
                $this->entry->fill([
                    'title' => data_get($data, 'title') ?: $this->entry->title,
                    'og_image_url' => data_get($data, 'image.url') ?: $this->entry->og_image_url,
                    'screenshot_url' => data_get($data, 'screenshot.url'),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $this->entry->status = EntryStatus::Live;
        $this->entry->save();
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=EnrichEntryJobTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: EnrichEntryJob pulls title/og/screenshot from Microlink with graceful failure"
```

---

### Task 4: Submit endpoint + validation

**Files:**
- Create: `app/Http/Requests/StoreEntryRequest.php`
- Create: `app/Http/Controllers/EntryController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SubmitEntryTest.php`

**Interfaces:**
- Consumes: `Entry`, `EnrichEntryJob`, `StoreEntryRequest`.
- Produces:
  - `POST /entries` → `EntryController@store`, named `entries.store`.
  - Request validates: `url` required/url/unique:entries/host ends in `.laravel.cloud`; `tagline` required/max:80; `author_name` required; `x_handle` nullable; honeypot field `website` must be empty.
  - Server fetches URL; non-200 → validation error on `url`. Clean 200 → `status = live`, else `pending`.
  - Persists entry with parsed `host`, dispatches `EnrichEntryJob`, `redirect()->back()->with('flash', ['submittedEntryId' => $id])`.
  - Rate limit: `throttle:10,1` on the route.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SubmitEntryTest.php`:

```php
<?php

use App\Jobs\EnrichEntryJob;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => Queue::fake());

function validPayload(array $overrides = []): array
{
    return array_merge([
        'url' => 'https://myapp.laravel.cloud',
        'tagline' => 'My weekend app',
        'author_name' => 'Zakir',
        'x_handle' => '@zakir',
        'website' => '', // honeypot
    ], $overrides);
}

it('accepts a valid laravel.cloud url and dispatches enrich', function () {
    Http::fake(['myapp.laravel.cloud' => Http::response('ok', 200)]);

    $this->post(route('entries.store'), validPayload())
        ->assertRedirect();

    expect(Entry::where('url', 'https://myapp.laravel.cloud')->exists())->toBeTrue();
    Queue::assertPushed(EnrichEntryJob::class);
});

it('rejects a non-laravel.cloud url', function () {
    $this->post(route('entries.store'), validPayload(['url' => 'https://evil.com']))
        ->assertSessionHasErrors('url');
    expect(Entry::count())->toBe(0);
});

it('rejects a url that does not return 200', function () {
    Http::fake(['down.laravel.cloud' => Http::response('nope', 404)]);

    $this->post(route('entries.store'), validPayload(['url' => 'https://down.laravel.cloud']))
        ->assertSessionHasErrors('url');
    expect(Entry::count())->toBe(0);
});

it('rejects when honeypot is filled', function () {
    Http::fake(['myapp.laravel.cloud' => Http::response('ok', 200)]);

    $this->post(route('entries.store'), validPayload(['website' => 'spam']))
        ->assertSessionHasErrors('website');
    expect(Entry::count())->toBe(0);
});

it('rejects a duplicate url', function () {
    Http::fake(['myapp.laravel.cloud' => Http::response('ok', 200)]);
    Entry::create(['url' => 'https://myapp.laravel.cloud', 'host' => 'myapp.laravel.cloud', 'tagline' => 't', 'author_name' => 'z', 'status' => 'live']);

    $this->post(route('entries.store'), validPayload())
        ->assertSessionHasErrors('url');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SubmitEntryTest`
Expected: FAIL (route `entries.store` not defined).

- [ ] **Step 3: Create the form request**

`app/Http/Requests/StoreEntryRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required', 'url', 'max:2048',
                Rule::unique('entries', 'url'),
                function (string $attribute, mixed $value, \Closure $fail) {
                    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                    if (! str_ends_with($host, '.laravel.cloud')) {
                        $fail('The URL must be a *.laravel.cloud address.');
                    }
                },
            ],
            'tagline' => ['required', 'string', 'max:80'],
            'author_name' => ['required', 'string', 'max:100'],
            'x_handle' => ['nullable', 'string', 'max:50'],
            'website' => ['prohibited'], // honeypot: must be empty/absent
        ];
    }

    public function messages(): array
    {
        return ['website.prohibited' => 'Spam detected.'];
    }
}
```

Note: `prohibited` fails when the field is present AND non-empty; an empty string passes. Confirm the honeypot test sends `''` (passes) vs `'spam'` (fails).

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/EntryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Http\Requests\StoreEntryRequest;
use App\Jobs\EnrichEntryJob;
use App\Models\Entry;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class EntryController extends Controller
{
    public function store(StoreEntryRequest $request)
    {
        $url = $request->string('url');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // Server-side reachability check.
        $ok = false;
        try {
            $ok = Http::timeout(10)->get($url)->successful();
        } catch (\Throwable) {
            $ok = false;
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'url' => 'We could not reach that URL (it must return HTTP 200).',
            ]);
        }

        $entry = Entry::create([
            'url' => $url,
            'host' => $host,
            'tagline' => $request->string('tagline'),
            'author_name' => $request->string('author_name'),
            'x_handle' => $request->input('x_handle'),
            'status' => EntryStatus::Live,
        ]);

        EnrichEntryJob::dispatch($entry);

        return redirect()->back()->with('flash', [
            'submittedEntryId' => $entry->id,
        ]);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php` add:

```php
use App\Http\Controllers\EntryController;

Route::post('/entries', [EntryController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('entries.store');
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=SubmitEntryTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: submit endpoint with hostname gate, 200 check, honeypot, rate limit"
```

---

### Task 5: Voting endpoint + anti-abuse

**Files:**
- Create: `app/Http/Controllers/VoteController.php`
- Create: `app/Support/VoterHash.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/VoteTest.php`

**Interfaces:**
- Consumes: `Entry`, `Vote`.
- Produces:
  - `App\Support\VoterHash::for(Request $request): string` — `hash('sha256', ip . '|' . signedCookieValue)`; sets a signed cookie `stw_voter` (random 32 hex) if absent, queued on the response.
  - `POST /entries/{entry}/vote` → `VoteController@store`, named `entries.vote`, middleware `throttle:30,1`.
  - Creates a `Vote` (entry_id + voter_hash); duplicate caught → no-op. Increments `entries.votes_count` atomically only on fresh insert. `redirect()->back()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/VoteTest.php`:

```php
<?php

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function liveEntry(): Entry
{
    return Entry::create([
        'url' => 'https://v.laravel.cloud', 'host' => 'v.laravel.cloud',
        'tagline' => 't', 'author_name' => 'z', 'status' => 'live',
    ]);
}

it('increments vote count on first vote', function () {
    $entry = liveEntry();

    $this->post(route('entries.vote', $entry))->assertRedirect();

    expect($entry->fresh()->votes_count)->toBe(1);
});

it('does not double-count the same voter', function () {
    $entry = liveEntry();

    $this->post(route('entries.vote', $entry));
    $this->post(route('entries.vote', $entry)); // same session/cookie + IP

    expect($entry->fresh()->votes_count)->toBe(1)
        ->and($entry->votes()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=VoteTest`
Expected: FAIL (route not defined).

- [ ] **Step 3: Create the voter hash helper**

`app/Support/VoterHash.php`:

```php
<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class VoterHash
{
    public static function for(Request $request): string
    {
        $cookie = $request->cookie('stw_voter');

        if (! $cookie) {
            $cookie = Str::random(32);
            Cookie::queue('stw_voter', $cookie, 60 * 24 * 365); // 1 year, signed by Laravel
        }

        return hash('sha256', $request->ip() . '|' . $cookie);
    }
}
```

- [ ] **Step 4: Create the controller**

`app/Http/Controllers/VoteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Vote;
use App\Support\VoterHash;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteController extends Controller
{
    public function store(Request $request, Entry $entry)
    {
        $hash = VoterHash::for($request);

        try {
            DB::transaction(function () use ($entry, $hash) {
                Vote::create(['entry_id' => $entry->id, 'voter_hash' => $hash]);
                $entry->increment('votes_count');
            });
        } catch (QueryException $e) {
            // Unique violation → already voted. Ignore.
        }

        return redirect()->back();
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/web.php`:

```php
use App\Http\Controllers\VoteController;

Route::post('/entries/{entry}/vote', [VoteController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('entries.vote');
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=VoteTest`
Expected: PASS. (Test client reuses the session cookie across requests, so the second vote hits the unique constraint.)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: login-free voting deduped by hashed IP + signed cookie"
```

---

### Task 6: Gallery page (Top/New tabs, card grid, screenshot polling)

**Files:**
- Create: `app/Http/Controllers/GalleryController.php`
- Modify: `routes/web.php` (root route → gallery)
- Create: `resources/js/pages/gallery.tsx`
- Create: `resources/js/components/entry-card.tsx`
- Create: `resources/js/types/entry.ts`
- Test: `tests/Feature/GalleryTest.php`

**Interfaces:**
- Consumes: `Entry`, `EntryStatus`.
- Produces:
  - `GET /` → `GalleryController@index`, named `gallery`. Query param `tab` in `top|new` (default `top`). Renders Inertia page `gallery` with props `{ entries: EntryResource[], tab, flash }`.
  - Only `status = live` entries shown. `top` orders by `votes_count desc, created_at desc`; `new` by `created_at desc`. Limit 100.
  - Each entry serialized: `{ id, url, host, title, tagline, author_name, x_handle, og_image_url, screenshot_url, votes_count, has_pending_shot }` where `has_pending_shot = screenshot_url === null`.
  - React `EntryCard` renders pulsing skeleton when `screenshot_url` null, else the shot. Vote button posts to `entries.vote` with optimistic bump. Cards with pending shots trigger `router.reload({ only: ['entries'] })` on a 3s interval until all shots resolve.

- [ ] **Step 1: Write the failing test**

`tests/Feature/GalleryTest.php`:

```php
<?php

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the gallery with only live entries ordered by votes on top tab', function () {
    Entry::create(['url' => 'https://a.laravel.cloud', 'host' => 'a.laravel.cloud', 'tagline' => 'a', 'author_name' => 'z', 'status' => 'live', 'votes_count' => 5]);
    Entry::create(['url' => 'https://b.laravel.cloud', 'host' => 'b.laravel.cloud', 'tagline' => 'b', 'author_name' => 'z', 'status' => 'live', 'votes_count' => 10]);
    Entry::create(['url' => 'https://c.laravel.cloud', 'host' => 'c.laravel.cloud', 'tagline' => 'c', 'author_name' => 'z', 'status' => 'pending', 'votes_count' => 99]);

    $this->get('/')->assertInertia(fn (Assert $page) => $page
        ->component('gallery')
        ->has('entries', 2)
        ->where('entries.0.host', 'b.laravel.cloud') // higher votes first
        ->where('tab', 'top')
    );
});

it('orders by recency on new tab', function () {
    $old = Entry::create(['url' => 'https://old.laravel.cloud', 'host' => 'old.laravel.cloud', 'tagline' => 'o', 'author_name' => 'z', 'status' => 'live', 'votes_count' => 100]);
    $new = Entry::create(['url' => 'https://new.laravel.cloud', 'host' => 'new.laravel.cloud', 'tagline' => 'n', 'author_name' => 'z', 'status' => 'live', 'votes_count' => 1]);
    $new->update(['created_at' => now()->addMinute()]);

    $this->get('/?tab=new')->assertInertia(fn (Assert $page) => $page
        ->where('entries.0.host', 'new.laravel.cloud')
        ->where('tab', 'new')
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GalleryTest`
Expected: FAIL (route `/` still renders starter welcome, component mismatch).

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/GalleryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'new' ? 'new' : 'top';

        $query = Entry::query()->where('status', EntryStatus::Live);

        $query = $tab === 'new'
            ? $query->orderByDesc('created_at')
            : $query->orderByDesc('votes_count')->orderByDesc('created_at');

        $entries = $query->limit(100)->get()->map(fn (Entry $e) => [
            'id' => $e->id,
            'url' => $e->url,
            'host' => $e->host,
            'title' => $e->title,
            'tagline' => $e->tagline,
            'author_name' => $e->author_name,
            'x_handle' => $e->x_handle,
            'og_image_url' => $e->og_image_url,
            'screenshot_url' => $e->screenshot_url,
            'votes_count' => $e->votes_count,
            'has_pending_shot' => $e->screenshot_url === null,
        ]);

        return Inertia::render('gallery', [
            'entries' => $entries,
            'tab' => $tab,
        ]);
    }
}
```

- [ ] **Step 4: Point root route at the gallery**

In `routes/web.php`, replace the existing `Route::get('/', ...)` welcome route with:

```php
use App\Http\Controllers\GalleryController;

Route::get('/', [GalleryController::class, 'index'])->name('gallery');
```

- [ ] **Step 5: Create the TS type**

`resources/js/types/entry.ts`:

```ts
export interface Entry {
    id: number;
    url: string;
    host: string;
    title: string | null;
    tagline: string;
    author_name: string;
    x_handle: string | null;
    og_image_url: string | null;
    screenshot_url: string | null;
    votes_count: number;
    has_pending_shot: boolean;
}
```

- [ ] **Step 6: Create the EntryCard component**

`resources/js/components/entry-card.tsx`:

```tsx
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { Entry } from '@/types/entry';

export function EntryCard({ entry }: { entry: Entry }) {
    const [votes, setVotes] = useState(entry.votes_count);
    const [voted, setVoted] = useState(false);

    const vote = () => {
        if (voted) return;
        setVoted(true);
        setVotes((v) => v + 1); // optimistic
        router.post(
            `/entries/${entry.id}/vote`,
            {},
            { preserveScroll: true, only: [], onError: () => { setVoted(false); setVotes((v) => v - 1); } },
        );
    };

    const shot = entry.screenshot_url ?? entry.og_image_url;

    return (
        <div className="group flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900">
            <a href={entry.url} target="_blank" rel="noopener noreferrer" className="block aspect-[16/10] overflow-hidden bg-neutral-100 dark:bg-neutral-800">
                {shot ? (
                    <img src={shot} alt={entry.title ?? entry.host} className="h-full w-full object-cover transition group-hover:scale-[1.02]" loading="lazy" />
                ) : (
                    <div className="h-full w-full animate-pulse bg-gradient-to-br from-neutral-200 to-neutral-100 dark:from-neutral-800 dark:to-neutral-700" />
                )}
            </a>
            <div className="flex flex-1 flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h3 className="truncate font-semibold text-neutral-900 dark:text-neutral-100">{entry.title ?? entry.host}</h3>
                        <p className="truncate text-sm text-neutral-500">{entry.tagline}</p>
                    </div>
                    <button
                        onClick={vote}
                        disabled={voted}
                        className="flex shrink-0 flex-col items-center rounded-lg border border-neutral-200 px-3 py-1.5 text-sm font-medium transition hover:border-orange-400 hover:text-orange-500 disabled:opacity-60 dark:border-neutral-700"
                        aria-label="Upvote"
                    >
                        <span>▲</span>
                        <span>{votes}</span>
                    </button>
                </div>
                <p className="mt-auto text-xs text-neutral-400">by {entry.author_name}</p>
            </div>
        </div>
    );
}
```

- [ ] **Step 7: Create the gallery page**

`resources/js/pages/gallery.tsx`:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { EntryCard } from '@/components/entry-card';
import type { Entry } from '@/types/entry';

interface Props {
    entries: Entry[];
    tab: 'top' | 'new';
}

export default function Gallery({ entries, tab }: Props) {
    const pending = entries.some((e) => e.has_pending_shot);

    useEffect(() => {
        if (!pending) return;
        const id = setInterval(() => router.reload({ only: ['entries'] }), 3000);
        return () => clearInterval(id);
    }, [pending]);

    return (
        <>
            <Head title="Shipped This Weekend" />
            <div className="mx-auto min-h-screen max-w-6xl px-4 py-10">
                <header className="mb-8 text-center">
                    <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">Shipped on Laravel Cloud this weekend.</h1>
                    <p className="mt-2 text-neutral-500">Paste your <code>laravel.cloud</code> URL. Get a card. Get votes.</p>
                    <Link href="/submit" className="mt-5 inline-block rounded-lg bg-orange-500 px-5 py-2.5 font-medium text-white transition hover:bg-orange-600">
                        Submit your app
                    </Link>
                </header>

                <div className="mb-6 flex justify-center gap-2">
                    <TabLink active={tab === 'top'} href="/?tab=top">Top</TabLink>
                    <TabLink active={tab === 'new'} href="/?tab=new">New</TabLink>
                </div>

                {entries.length === 0 ? (
                    <p className="py-20 text-center text-neutral-400">No entries yet. Be the first.</p>
                ) : (
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {entries.map((e) => <EntryCard key={e.id} entry={e} />)}
                    </div>
                )}
            </div>
        </>
    );
}

function TabLink({ active, href, children }: { active: boolean; href: string; children: React.ReactNode }) {
    return (
        <Link
            href={href}
            className={`rounded-full px-4 py-1.5 text-sm font-medium transition ${active ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900' : 'text-neutral-500 hover:bg-neutral-100 dark:hover:bg-neutral-800'}`}
        >
            {children}
        </Link>
    );
}
```

- [ ] **Step 8: Run tests + build**

Run: `php artisan test --filter=GalleryTest && npm run build`
Expected: PASS + clean build.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: gallery page with Top/New tabs, card grid, screenshot polling, optimistic votes"
```

---

### Task 7: Submit page + share loop

**Files:**
- Create: `resources/js/pages/submit.tsx`
- Modify: `routes/web.php` (GET /submit)
- Modify: `app/Http/Controllers/EntryController.php` (add `create()` returning Inertia page; after store, redirect to gallery with flash)
- Test: `tests/Feature/SubmitPageTest.php`

**Interfaces:**
- Consumes: `GalleryController` route, `entries.store`.
- Produces:
  - `GET /submit` → `EntryController@create`, named `entries.create`, renders Inertia page `submit`.
  - `submit.tsx`: Inertia `useForm` posting to `entries.store` with fields `url, tagline, author_name, x_handle, website(honeypot hidden)`. Inline errors. On success redirect to `/` (store already redirects back; change store to `redirect()->route('gallery')->with('flash', ['submittedEntryId' => $id])`).
  - Share button on gallery when `flash.submittedEntryId` present: pre-fills an X intent URL with title + gallery URL.

- [ ] **Step 1: Write the failing test**

`tests/Feature/SubmitPageTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the submit page', function () {
    $this->get('/submit')->assertInertia(fn (Assert $page) => $page->component('submit'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SubmitPageTest`
Expected: FAIL (route `/submit` not found).

- [ ] **Step 3: Add the create action**

In `app/Http/Controllers/EntryController.php` add:

```php
use Inertia\Inertia;

public function create()
{
    return Inertia::render('submit');
}
```

And change the end of `store()` to redirect to the gallery:

```php
return redirect()->route('gallery')->with('flash', [
    'submittedEntryId' => $entry->id,
]);
```

- [ ] **Step 4: Register the route**

In `routes/web.php`:

```php
Route::get('/submit', [EntryController::class, 'create'])->name('entries.create');
```

- [ ] **Step 5: Create the submit page**

`resources/js/pages/submit.tsx`:

```tsx
import { Head, useForm } from '@inertiajs/react';

export default function Submit() {
    const { data, setData, post, processing, errors } = useForm({
        url: '',
        tagline: '',
        author_name: '',
        x_handle: '',
        website: '', // honeypot
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/entries');
    };

    return (
        <>
            <Head title="Submit — Shipped This Weekend" />
            <div className="mx-auto max-w-lg px-4 py-12">
                <h1 className="mb-6 text-2xl font-bold">Submit your weekend ship</h1>
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Your laravel.cloud URL" error={errors.url}>
                        <input type="url" required value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder="https://yourapp.laravel.cloud" className="input" />
                    </Field>
                    <Field label="Tagline (max 80)" error={errors.tagline}>
                        <input type="text" required maxLength={80} value={data.tagline} onChange={(e) => setData('tagline', e.target.value)} className="input" />
                    </Field>
                    <Field label="Your name" error={errors.author_name}>
                        <input type="text" required value={data.author_name} onChange={(e) => setData('author_name', e.target.value)} className="input" />
                    </Field>
                    <Field label="X handle (optional)" error={errors.x_handle}>
                        <input type="text" value={data.x_handle} onChange={(e) => setData('x_handle', e.target.value)} placeholder="@you" className="input" />
                    </Field>
                    {/* honeypot */}
                    <input type="text" tabIndex={-1} autoComplete="off" value={data.website} onChange={(e) => setData('website', e.target.value)} className="hidden" aria-hidden="true" />
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-orange-500 px-5 py-2.5 font-medium text-white transition hover:bg-orange-600 disabled:opacity-60">
                        {processing ? 'Submitting…' : 'Submit'}
                    </button>
                </form>
            </div>
        </>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium">{label}</span>
            {children}
            {error && <span className="mt-1 block text-sm text-red-500">{error}</span>}
        </label>
    );
}
```

Add to `resources/css/app.css` an `.input` utility:

```css
@layer components {
    .input {
        @apply w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-neutral-900 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100;
    }
}
```

- [ ] **Step 6: Add the share CTA to the gallery**

In `resources/js/pages/gallery.tsx`, read `flash` from `usePage().props` and, when `flash.submittedEntryId` is set, render a banner above the grid with an X share intent link:

```tsx
// inside Gallery, after imports add usePage
import { usePage } from '@inertiajs/react';
// ...
const { props } = usePage<{ flash?: { submittedEntryId?: number } }>();
const justSubmitted = props.flash?.submittedEntryId;
const submitted = entries.find((e) => e.id === justSubmitted);
// ...render when submitted:
{submitted && (
    <div className="mb-6 rounded-xl border border-orange-200 bg-orange-50 p-4 text-center dark:border-orange-900/40 dark:bg-orange-950/30">
        <p className="font-medium">You're live 🎉 Share your card:</p>
        <a
            className="mt-2 inline-block rounded-lg bg-black px-4 py-2 text-sm font-medium text-white"
            target="_blank"
            rel="noopener noreferrer"
            href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(`I shipped "${submitted.title ?? submitted.host}" on Laravel Cloud this weekend 🚀`)}&url=${encodeURIComponent(window.location.origin)}`}
        >
            Share on X
        </a>
    </div>
)}
```

Ensure `flash` is shared to Inertia: in `app/Http/Middleware/HandleInertiaRequests.php` `share()`, add `'flash' => fn () => ['submittedEntryId' => $request->session()->get('flash.submittedEntryId')]` — OR simpler, share the whole flash bag. Verify the starter's `HandleInertiaRequests` and wire `flash` there.

- [ ] **Step 7: Run tests + build**

Run: `php artisan test --filter=SubmitPageTest && npm run build`
Expected: PASS + clean build.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: submit page and post-submit share-on-X loop"
```

---

### Task 8: Admin signed hide link

**Files:**
- Create: `app/Http/Controllers/AdminEntryController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/HideEntryTest.php`

**Interfaces:**
- Produces: `GET /admin/entries/{entry}/hide` → `AdminEntryController@hide`, named `admin.entries.hide`, protected by `signed` middleware. Sets `status = hidden`, redirects to gallery. Generate links with `URL::signedRoute('admin.entries.hide', $entry)`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/HideEntryTest.php`:

```php
<?php

use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('hides an entry via a valid signed link', function () {
    $entry = Entry::create(['url' => 'https://h.laravel.cloud', 'host' => 'h.laravel.cloud', 'tagline' => 't', 'author_name' => 'z', 'status' => 'live']);

    $url = URL::signedRoute('admin.entries.hide', $entry);
    $this->get($url)->assertRedirect();

    expect($entry->fresh()->status->value)->toBe('hidden');
});

it('rejects an unsigned hide request', function () {
    $entry = Entry::create(['url' => 'https://h2.laravel.cloud', 'host' => 'h2.laravel.cloud', 'tagline' => 't', 'author_name' => 'z', 'status' => 'live']);

    $this->get(route('admin.entries.hide', $entry))->assertForbidden();
    expect($entry->fresh()->status->value)->toBe('live');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HideEntryTest`
Expected: FAIL (route not defined).

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/AdminEntryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Models\Entry;

class AdminEntryController extends Controller
{
    public function hide(Entry $entry)
    {
        $entry->update(['status' => EntryStatus::Hidden]);

        return redirect()->route('gallery');
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`:

```php
use App\Http\Controllers\AdminEntryController;

Route::get('/admin/entries/{entry}/hide', [AdminEntryController::class, 'hide'])
    ->middleware('signed')
    ->name('admin.entries.hide');
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=HideEntryTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: signed admin hide link (no admin UI)"
```

---

### Task 9: Seed ~10 real entries

**Files:**
- Create: `database/seeders/EntrySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces: `EntrySeeder` inserting ~10 `status = live` entries with real-looking `.laravel.cloud` hosts, taglines, authors, and placeholder `screenshot_url` (nullable is fine — cards will show skeleton then be re-enriched). Idempotent via `updateOrCreate` on `url`.

- [ ] **Step 1: Create the seeder**

`database/seeders/EntrySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Database\Seeder;

class EntrySeeder extends Seeder
{
    public function run(): void
    {
        $seed = [
            ['url' => 'https://shippedthisweekend.laravel.cloud', 'title' => 'Shipped This Weekend', 'tagline' => 'The gallery you are looking at.', 'author_name' => 'Zakir', 'x_handle' => '@heyzakir', 'votes_count' => 12],
            // Add ~9 more real weekend entries here before launch.
        ];

        foreach ($seed as $row) {
            Entry::updateOrCreate(
                ['url' => $row['url']],
                array_merge($row, [
                    'host' => parse_url($row['url'], PHP_URL_HOST),
                    'status' => EntryStatus::Live,
                ]),
            );
        }
    }
}
```

- [ ] **Step 2: Wire it into DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php` `run()`, add `$this->call(EntrySeeder::class);`.

- [ ] **Step 3: Run the seeder**

Run: `php artisan db:seed --class=EntrySeeder`
Expected: rows inserted, no errors.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: seed weekend entries so launch isn't a blank page"
```

**Manual pre-launch note:** replace the placeholder with ~10 real weekend entries.

---

### Task 10: Deploy config for Laravel Cloud

**Files:**
- Create/verify: build & deploy settings documented in `docs/superpowers/plans/`. No pipeline edits at deploy time (Cloud auto-deploys on push).

**Interfaces:**
- Produces: an app ready to run on `shippedthisweekend.laravel.cloud` with Postgres, a managed queue worker running `php artisan queue:work`, and env vars `MICROLINK_API_KEY` (optional), `APP_URL`.

- [ ] **Step 1: Verify production build passes locally**

Run: `npm run build && php artisan test`
Expected: clean build + all tests PASS.

- [ ] **Step 2: TypeScript + lint check**

Run: `npm run types 2>/dev/null || npx tsc --noEmit; npm run lint 2>/dev/null || true`
Expected: no TS errors.

- [ ] **Step 3: Confirm queue worker requirement**

The enrich pipeline needs a running worker. On Laravel Cloud, ensure a background process `php artisan queue:work` exists for the environment (managed queue). Document in the PR description; do not edit the pipeline here unless Zakir asks.

- [ ] **Step 4: Commit + push (Cloud auto-deploys)**

```bash
git add -A
git commit -m "chore: production build verified, ready for Laravel Cloud"
git push origin main
```

After push: "pushed — deploys automatically." Then in the Cloud env set `MICROLINK_API_KEY` if using a paid tier, and confirm the queue worker is up.

---

## Self-Review

**Spec coverage:**
- Data model (entries, votes, unique name) → Task 2 ✓
- Auto-enrich pipeline (Microlink, graceful failure, retry/backoff) → Task 3 ✓
- Skeleton→screenshot swap via polling → Task 6 ✓
- Voting + anti-abuse (voter_hash, unique, rate limit, optimistic UI) → Task 5, 6 ✓
- Anti-spam submit (hostname gate, 200 fetch, honeypot, rate limit) → Task 4 ✓
- Gallery page, Top/New tabs, card grid → Task 6 ✓
- Submit page + share loop → Task 7 ✓
- Empty state + seed ~10 → Task 6 (empty state), Task 9 (seed) ✓
- Signed admin hide link → Task 8 ✓
- All testing bullets in spec → Tasks 2–8 tests ✓
- Out-of-scope items → none built ✓

**Open ops items (not code, flagged for Zakir):**
- Microlink free tier (~50/day) will throttle on launch spike — line up paid tier before announcing.
- Screenshot URLs are Microlink-hosted (not self-stored) — post-launch hardening if they expire.

**Placeholder scan:** seeder has one intentional TODO (real entries) flagged as a manual pre-launch step, not a code placeholder. No other placeholders.

**Type consistency:** `EntryStatus` cases, `voter_hash`, `votes_count`, `has_pending_shot`, route names (`entries.store`, `entries.vote`, `entries.create`, `gallery`, `admin.entries.hide`) consistent across tasks.
