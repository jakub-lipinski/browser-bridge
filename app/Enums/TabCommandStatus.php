<?php

namespace App\Enums;

enum TabCommandStatus: string
{
    case Pending = 'pending';
    case Opened = 'opened';
    case Dismissed = 'dismissed';
}
