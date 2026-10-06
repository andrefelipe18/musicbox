<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves user avatars from the blobatar.dev HTTP API.
 *
 * @see https://blobatar.dev/
 */
class BlobatarProvider implements Contracts\AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        return 'https://blobatar.dev/avatar/'.md5($record->getAuthIdentifier()).'?size=128';
    }
}
