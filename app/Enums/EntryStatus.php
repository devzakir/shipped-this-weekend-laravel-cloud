<?php

namespace App\Enums;

enum EntryStatus: string
{
    case Pending = 'pending';
    case Live = 'live';
    case Hidden = 'hidden';
}
