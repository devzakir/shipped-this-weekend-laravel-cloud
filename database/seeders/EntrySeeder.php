<?php

namespace Database\Seeders;

use App\Enums\EntryStatus;
use App\Models\Entry;
use Illuminate\Database\Seeder;

class EntrySeeder extends Seeder
{
    public function run(): void
    {
        $seed = [
            ['url' => 'https://shippedthisweekend.laravel.cloud', 'title' => 'Shipped This Weekend', 'tagline' => 'The gallery you are looking at.', 'author_name' => 'Zakir', 'x_handle' => '@heyzakir', 'votes_count' => 12],
            // Add ~9 more real weekend entries here before launch.
        ];

        foreach ($seed as $row) {
            Entry::updateOrCreate(
                ['url' => $row['url']],
                array_merge($row, [
                    'host' => parse_url($row['url'], PHP_URL_HOST),
                    'status' => EntryStatus::Live,
                ]),
            );
        }
    }
}
