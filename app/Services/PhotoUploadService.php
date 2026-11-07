<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Stores uploaded photos on the public disk and produces resized variants
 * (full, thumb) via Intervention Image.
 */
class PhotoUploadService
{
    public const VARIANTS = [
        'full' => 1200,
        'thumb' => 400,
    ];

    /**
     * @return array{path: string, thumb_path: string}
     */
    public function store(UploadedFile $file, string $directory = 'meals'): array
    {
        $manager = new ImageManager(new Driver);
        $base = $directory.'/'.now()->format('Ymd-His').'-'.str()->random(8);

        $paths = [];
        foreach (self::VARIANTS as $variant => $width) {
            $image = $manager->decode($file->getRealPath())->scaleDown(width: $width);
            $path = "{$base}-{$variant}.jpg";
            $encoded = $image->encode(new JpegEncoder(quality: 85));
            Storage::disk('public')->put($path, $encoded->toString());
            $paths[$variant] = $path;
        }

        return [
            'path' => $paths['full'],
            'thumb_path' => $paths['thumb'],
        ];
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);

        // Remove matching sibling variants (e.g. ...-thumb.jpg for ...-full.jpg).
        if (preg_match('/^(?<base>.+)-(full|thumb)\.jpg$/', $path, $m)) {
            foreach (array_keys(self::VARIANTS) as $variant) {
                Storage::disk('public')->delete("{$m['base']}-{$variant}.jpg");
            }
        }
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
