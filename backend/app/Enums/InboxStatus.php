<?php

namespace App\Enums;

enum InboxStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Converted = 'converted';
    case Dismissed = 'dismissed';
}
