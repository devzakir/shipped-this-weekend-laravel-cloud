<?php

namespace App\Http\Controllers;

use App\Enums\EntryStatus;
use App\Models\Entry;

class AdminEntryController extends Controller
{
    public function hide(Entry $entry)
    {
        $entry->update(['status' => EntryStatus::Hidden]);

        return redirect()->route('gallery');
    }
}
