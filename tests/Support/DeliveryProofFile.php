<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

class DeliveryProofFile
{
    public static function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aCk0AAAAASUVORK5CYII='));
    }
}
