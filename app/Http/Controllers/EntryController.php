<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Http\Requests\StoreEntryRequest;
use App\Jobs\EnrichEntryJob;
use App\Models\Entry;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class EntryController extends Controller
{
    public function store(StoreEntryRequest $request)
    {
        $url = $request->string('url');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // Server-side reachability check.
        $ok = false;
        try {
            $ok = Http::timeout(10)->get($url)->successful();
        } catch (\Throwable) {
            $ok = false;
        }

        if (! $ok) {
            throw ValidationException::withMessages([
                'url' => 'We could not reach that URL (it must return HTTP 200).',
            ]);
        }

        $entry = Entry::create([
            'url' => $url,
            'host' => $host,
            'tagline' => $request->string('tagline'),
            'author_name' => $request->string('author_name'),
            'x_handle' => $request->input('x_handle'),
            'status' => EntryStatus::Live,
        ]);

        EnrichEntryJob::dispatch($entry);

        return redirect()->back()->with('flash', [
            'submittedEntryId' => $entry->id,
        ]);
    }
}
