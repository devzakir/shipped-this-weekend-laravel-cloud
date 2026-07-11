<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Models\Entry;
use App\Support\Seo;
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

        $base = rtrim((string) config('app.url'), '/');

        $jsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => $base.'/',
                'description' => 'A live gallery of side projects shipped on Laravel Cloud this weekend.',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => config('app.name'),
                'url' => $base.'/',
                'about' => 'Side projects shipped on Laravel Cloud',
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'itemListElement' => $entries->take(20)->values()
                        ->map(fn (array $e, int $i) => [
                            '@type' => 'ListItem',
                            'position' => $i + 1,
                            'name' => $e['title'] ?: $e['host'],
                            'url' => $e['url'],
                        ])->all(),
                ],
            ],
        ];

        return Inertia::render('gallery', [
            'entries' => $entries,
            'tab' => $tab,
            'seo' => Seo::page(
                'Shipped This Weekend — Side Projects Built on Laravel Cloud',
                'A live gallery of side projects shipped on Laravel Cloud this weekend. Paste your laravel.cloud URL, get an auto-generated card, and collect upvotes from the community.',
                '/',
                $jsonLd,
            ),
        ]);
    }
}
