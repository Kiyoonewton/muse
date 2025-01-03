<?php

namespace App\Helper;

use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    /**
     * Upload image to s3 bucket
     *
     * @param string $folder
     * @param string $file
     *
     * @return string|array
     */
    public static function uploadToS3($folder, $file)
    {
        try {
            $file = Storage::disk('s3')->put($folder, $file, 'public');
            $imageUrl = Storage::disk('s3')->url($file);
        } catch (Exception $e) {
            return [
                'message' => $e->getMessage(),
            ];
        }

        return $imageUrl;
    }
}
