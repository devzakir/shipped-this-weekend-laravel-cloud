<?php

namespace App\Jobs;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class EnrichEntryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(public Entry $entry) {}

    public function handle(): void
    {
        $key = config('services.microlink.key');
        $endpoint = config('services.microlink.endpoint');

        try {
            $response = Http::timeout(20)
                ->when($key, fn ($http) => $http->withHeaders(['x-api-key' => $key]))
                ->get($endpoint, [
                    'url' => $this->entry->url,
                    'screenshot' => 'true',
                    'meta' => 'true',
                ]);

            if ($response->successful() && $response->json('status') === 'success') {
                $data = $response->json('data', []);
                $this->entry->fill([
                    'title' => data_get($data, 'title') ?: $this->entry->title,
                    'og_image_url' => data_get($data, 'image.url') ?: $this->entry->og_image_url,
                    'screenshot_url' => data_get($data, 'screenshot.url'),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $this->entry->status = EntryStatus::Live;
        $this->entry->save();
    }
}
