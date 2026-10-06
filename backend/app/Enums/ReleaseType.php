<?php

namespace App\Enums;

enum ReleaseType: string
{
    case Album = 'album';
    case Ep = 'ep';
    case Single = 'single';
    case Unknown = 'unknown';
}
