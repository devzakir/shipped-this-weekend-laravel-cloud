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
    $new->forceFill(['created_at' => now()->addMinute()])->save();

    $this->get('/?tab=new')->assertInertia(fn (Assert $page) => $page
        ->where('entries.0.host', 'new.laravel.cloud')
        ->where('tab', 'new')
    );
});
