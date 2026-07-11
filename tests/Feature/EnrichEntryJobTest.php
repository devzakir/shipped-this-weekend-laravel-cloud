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
        'status' => EntryStatus::Live,
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

it('throws so the queue retries when microlink fails, staying live without a screenshot', function () {
    Http::fake(['api.microlink.io*' => Http::response([], 500)]);

    $entry = makeEntry();

    expect(fn () => (new EnrichEntryJob($entry))->handle())->toThrow(\RuntimeException::class);

    $entry->refresh();

    expect($entry->screenshot_url)->toBeNull()
        ->and($entry->status)->toBe(EntryStatus::Live);
});
