<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the submit page', function () {
    $this->get('/submit')->assertInertia(fn (Assert $page) => $page->component('submit'));
});
