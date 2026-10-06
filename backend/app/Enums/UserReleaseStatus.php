<?php

namespace App\Enums;

enum UserReleaseStatus: string
{
    case WantToListen = 'want_to_listen';
    case Listening = 'listening';
    case Listened = 'listened';
}
