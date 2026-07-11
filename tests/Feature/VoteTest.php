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

    // First vote
    $this->post(route('entries.vote', $entry));

    // Second vote - get the generated cookie and pass it manually
    // (simulating what the test client should do automatically)
    $cookie = \App\Support\VoterHash::getGeneratedCookie();
    $this->withCookie('stw_voter', $cookie ?? '')
        ->post(route('entries.vote', $entry)); // same session/cookie + IP

    expect($entry->fresh()->votes_count)->toBe(1)
        ->and($entry->votes()->count())->toBe(1);
});
