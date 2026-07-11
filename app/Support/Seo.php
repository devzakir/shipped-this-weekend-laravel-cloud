<?php

namespace App\Support;

class Seo
{
    /**
     * Build the server-rendered SEO payload passed as an Inertia prop and
     * consumed by the root Blade view (so crawlers see meta without JS).
     *
     * @param  array<int, array<string, mixed>>  $jsonLd
     * @return array<string, mixed>
     */
    public static function page(string $title, string $description, string $path = '/', array $jsonLd = []): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $base.$path,
            'image' => $base.'/og-image.png',
            'siteName' => config('app.name'),
            'jsonLd' => $jsonLd,
        ];
    }
}
