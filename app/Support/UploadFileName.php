<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Names an uploaded file for the public disk. The name is random and the
 * extension comes from the detected MIME type, never from the client file
 * name. Thus a file with image content cannot be stored as `.html`.
 */
final class UploadFileName
{
    public static function for(UploadedFile $file): string
    {
        return Str::uuid()->toString().'.'.($file->guessExtension() ?? 'bin');
    }
}
