<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use App\Models\Vote;
use App\Support\VoterHash;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteController extends Controller
{
    public function store(Request $request, Entry $entry)
    {
        $hash = VoterHash::for($request);

        try {
            DB::transaction(function () use ($entry, $hash) {
                Vote::create(['entry_id' => $entry->id, 'voter_hash' => $hash]);
                $entry->increment('votes_count');
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            // Unique violation → already voted. Ignore.
        }

        return redirect()->back();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [19, 1062], true)
            || $e->getCode() === '23505';
    }
}
