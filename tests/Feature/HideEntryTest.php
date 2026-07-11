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
