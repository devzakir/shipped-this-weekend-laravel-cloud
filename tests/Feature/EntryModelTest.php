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
