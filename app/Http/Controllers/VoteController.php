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
            // Unique violation → already voted. Ignore.
        }

        $response = redirect()->back();

        // Attach the generated cookie (if any) or existing cookie to response
        if ($cookie = VoterHash::getGeneratedCookie()) {
            $response->cookie('stw_voter', $cookie, 60 * 24 * 365);
        } elseif ($cookie = $request->cookie('stw_voter')) {
            $response->cookie('stw_voter', $cookie, 60 * 24 * 365);
        }

        return $response;
    }
}
