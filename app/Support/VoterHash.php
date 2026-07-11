<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class VoterHash
{
    public static function for(Request $request): string
    {
        $cookie = $request->cookie('stw_voter');

        if (! $cookie) {
            $cookie = Str::random(32);
            Cookie::queue('stw_voter', $cookie, 60 * 24 * 365); // 1 year, signed by Laravel
        }

        return hash('sha256', $request->ip().'|'.$cookie);
    }
}
