<?php

namespace App\Jobs;

use App\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class EnrichEntryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(public Entry $entry) {}

    public function handle(): void
    {
        try {
            $response = Http::timeout(15)->get($this->entry->url);
        } catch (\Throwable $e) {
            report($e);

            throw $e;
        }

        $title = null;
        $ogImageUrl = null;

        try {
            [$title, $ogImageUrl] = $this->parseMetadata((string) $response->body());
        } catch (\Throwable $e) {
            report($e);
        }

        $this->entry->fill([
            'title' => $title ?: $this->entry->host,
            'og_image_url' => $ogImageUrl,
        ]);

        try {
            $this->generateAndStoreScreenshot();
        } catch (\Throwable $e) {
            report($e);
        }

        $this->entry->save();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseMetadata(string $html): array
    {
        if (trim($html) === '') {
            return [null, null];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content')->item(0)?->nodeValue;
        $titleTag = $xpath->query('//title')->item(0)?->textContent;
        $title = trim((string) $ogTitle) !== '' ? trim($ogTitle) : (trim((string) $titleTag) !== '' ? trim($titleTag) : null);

        $ogImage = $xpath->query('//meta[@property="og:image"]/@content')->item(0)?->nodeValue;
        $ogImage = trim((string) $ogImage) !== '' ? trim($ogImage) : null;

        if ($ogImage !== null) {
            $ogImage = $this->resolveUrl($ogImage);
        }

        return [$title, $ogImage];
    }

    private function resolveUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $parts = parse_url($this->entry->url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? $this->entry->host;

        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $scheme.'://'.$host.$url;
        }

        return $scheme.'://'.$host.'/'.$url;
    }

    private function generateAndStoreScreenshot(): void
    {
        $endpoint = rtrim(config('services.screenshot.endpoint'), '/');
        $thumbUrl = $endpoint.'/get/width/1200/crop/900/'.$this->entry->url;

        $response = Http::timeout(30)->get($thumbUrl);

        if (! $response->successful() || $response->body() === '') {
            return;
        }

        $disk = config('services.screenshot.disk');
        $path = "screenshots/{$this->entry->id}.png";

        Storage::disk($disk)->put($path, $response->body());

        $this->entry->screenshot_url = Storage::disk($disk)->url($path);
    }
}
