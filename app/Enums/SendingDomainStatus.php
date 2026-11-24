<?php

namespace App\Enums;

enum SendingDomainStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
