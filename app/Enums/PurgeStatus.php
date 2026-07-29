<?php

namespace App\Enums;

enum PurgeStatus: string
{
    case Eligible = 'eligible';
    case Pending = 'purge_pending';
    case PhysicalDeleted = 'physical_deleted';
    case Purged = 'purged';
}
