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

it('rejects a url that returns 201 instead of 200', function () {
    Http::fake(['created.laravel.cloud' => Http::response('created', 201)]);

    $this->post(route('entries.store'), validPayload(['url' => 'https://created.laravel.cloud']))
        ->assertSessionHasErrors('url');
    expect(Entry::count())->toBe(0);
});
