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
