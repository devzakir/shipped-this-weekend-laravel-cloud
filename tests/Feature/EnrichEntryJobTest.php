<?php

use App\Enums\EntryStatus;
use App\Jobs\EnrichEntryJob;
use App\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeEntry(): Entry
{
    return Entry::create([
        'url' => 'https://myapp.laravel.cloud',
        'host' => 'myapp.laravel.cloud',
        'tagline' => 'demo',
        'author_name' => 'Zakir',
        'status' => EntryStatus::Live,
    ]);
}

it('populates title + og image from the page HTML, and stores a screenshot', function () {
    Storage::fake('public');
    Http::fake([
        'myapp.laravel.cloud' => Http::response('<html><head><title>Fallback</title><meta property="og:title" content="My Cool App"><meta property="og:image" content="https://cdn/og.png"></head><body>hi</body></html>', 200),
        'image.thum.io*' => Http::response('PNGBYTES', 200),
    ]);

    $entry = makeEntry();
    (new EnrichEntryJob($entry))->handle();
    $entry->refresh();

    expect($entry->title)->toBe('My Cool App')
        ->and($entry->og_image_url)->toBe('https://cdn/og.png')
        ->and($entry->screenshot_url)->not->toBeNull()
        ->and($entry->status)->toBe(EntryStatus::Live);

    Storage::disk('public')->assertExists("screenshots/{$entry->id}.png");
});

it('falls back to <title> when og:title absent', function () {
    Storage::fake('public');
    Http::fake([
        'myapp.laravel.cloud' => Http::response('<html><head><title>Just Title</title></head><body>hi</body></html>', 200),
        'image.thum.io*' => Http::response('PNGBYTES', 200),
    ]);

    $entry = makeEntry();
    (new EnrichEntryJob($entry))->handle();
    $entry->refresh();

    expect($entry->title)->toBe('Just Title');
});

it('stays live with host title when metadata + screenshot both fail', function () {
    Storage::fake('public');
    Http::fake([
        'myapp.laravel.cloud' => Http::response('', 200),
        'image.thum.io*' => Http::response('', 500),
    ]);

    $entry = makeEntry();
    (new EnrichEntryJob($entry))->handle();
    $entry->refresh();

    expect($entry->title)->toBe($entry->host)
        ->and($entry->screenshot_url)->toBeNull()
        ->and($entry->status)->toBe(EntryStatus::Live);
});

it('throws to retry when the page fetch itself errors', function () {
    Http::fake([
        'myapp.laravel.cloud' => fn () => throw new ConnectionException('down'),
    ]);

    $entry = makeEntry();

    expect(fn () => (new EnrichEntryJob($entry))->handle())->toThrow(ConnectionException::class);
});
