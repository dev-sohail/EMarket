<?php

namespace App\Services\Shared;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class ImageCompression
{
    /**
     * Hard ceiling on either axis, checked from the file header.
     *
     * GD allocates roughly four bytes per pixel, so a 3 MB PNG of
     * 50 000 x 50 000 decodes to ~10 GB and takes the worker down. The header
     * is cheap to read, so the check has to happen on getimagesize() output,
     * not on the decoded bitmap.
     */
    public const MAX_SOURCE_DIMENSION = 12000;

    /**
     * Ceiling on the total pixel count, which is what actually drives the GD
     * allocation. 16 MP is about 64 MB for the source bitmap, inside a default
     * 128M memory_limit, and still well above any image this store resizes
     * (the largest call site resizes to 1400px).
     */
    public const MAX_SOURCE_PIXELS = 16000000;

    public function compress(UploadedFile $file, int $maxWidth = 1200, int $quality = 80): UploadedFile
    {
        $realPath = $file->getRealPath();
        $mimeType = $file->getMimeType();

        $isPng = str_contains($mimeType, 'png');
        $isGif = str_contains($mimeType, 'gif');
        $isWebp = str_contains($mimeType, 'webp');

        [$origW, $origH] = $this->readDimensions($realPath);

        if ($origW <= $maxWidth) {
            return $file;
        }

        if ($isPng) {
            $src = @imagecreatefrompng($realPath);
        } elseif ($isGif) {
            $src = @imagecreatefromgif($realPath);
        } elseif ($isWebp) {
            $src = @imagecreatefromwebp($realPath);
        } else {
            $src = @imagecreatefromjpeg($realPath);
        }

        if (! $src) {
            return $file;
        }

        $newW = $maxWidth;
        $newH = max(1, (int) round($origH * ($maxWidth / $origW)));

        $dst = imagecreatetruecolor($newW, $newH);

        if ($isPng || $isGif || $isWebp) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $tempPath = tempnam(sys_get_temp_dir(), 'img_');

        if ($isPng) {
            $newPath = $tempPath.'.png';
            imagepng($dst, $newPath, 6);
            $newMime = 'image/png';
        } elseif ($isGif) {
            $newPath = $tempPath.'.gif';
            imagegif($dst, $newPath);
            $newMime = 'image/gif';
        } elseif ($isWebp) {
            $newPath = $tempPath.'.webp';
            imagewebp($dst, $newPath, $quality);
            $newMime = 'image/webp';
        } else {
            $newPath = $tempPath.'.jpg';
            imagejpeg($dst, $newPath, $quality);
            $newMime = 'image/jpeg';
        }

        imagedestroy($src);
        imagedestroy($dst);

        if (is_file($tempPath)) {
            @unlink($tempPath);
        }

        return new UploadedFile(
            $newPath,
            $file->getClientOriginalName(),
            $newMime,
            null,
            true
        );
    }

    public function compressMultiple(array $files, int $maxWidth = 1200, int $quality = 80): array
    {
        return array_map(fn ($file) => $this->compress($file, $maxWidth, $quality), $files);
    }

    /**
     * Read the pixel dimensions from the file header, refusing anything that
     * would exhaust memory if it were decoded.
     *
     * @return array{int, int}
     */
    protected function readDimensions(string $realPath): array
    {
        $size = @getimagesize($realPath);

        if ($size === false) {
            throw new InvalidArgumentException('The uploaded file is not a readable image.');
        }

        [$width, $height] = $size;

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('The uploaded file reports no usable dimensions.');
        }

        if ($width > self::MAX_SOURCE_DIMENSION || $height > self::MAX_SOURCE_DIMENSION) {
            throw new InvalidArgumentException('Image dimensions are too large.');
        }

        if ($width * $height > self::MAX_SOURCE_PIXELS) {
            throw new InvalidArgumentException('Image dimensions are too large.');
        }

        return [$width, $height];
    }
}
