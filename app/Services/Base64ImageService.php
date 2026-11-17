<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Base64ImageService
{
    private const MAX_BYTES = 2097152; // 2 MB (2 * 1024 * 1024)

    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Decode, validate, and store a base64 encoded image securely.
     *
     * @param string $base64Data
     * @param string $directory (e.g. 'attendances', 'signatures')
     * @param string $prefix (e.g. 'checkin', 'notulis')
     * @param string $fieldForError (for validation error key)
     * @param string $disk (default: 'public')
     * @return string Stored file path (e.g. 'attendances/checkin_random.jpg')
     * @throws ValidationException
     */
    public static function store(
        string $base64Data,
        string $directory,
        string $prefix = 'img',
        string $fieldForError = 'photo',
        string $disk = 'public'
    ): string {
        // Must match data URL pattern
        if (!preg_match('/^data:image\/[a-zA-Z0-9\+\-\.]+;base64,(.+)$/s', $base64Data, $matches)) {
            throw ValidationException::withMessages([
                $fieldForError => ['Format gambar tidak valid atau bukan base64 image yang didukung.']
            ]);
        }

        $encodedPayload = $matches[1];

        // Strict base64 decoding
        $decoded = base64_decode($encodedPayload, true);
        if ($decoded === false) {
            throw ValidationException::withMessages([
                $fieldForError => ['Format base64 gambar rusak atau tidak valid.']
            ]);
        }

        // Limit size (max 2 MB)
        if (strlen($decoded) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                $fieldForError => ['Ukuran file gambar melebihi batas maksimum 2 MB.']
            ]);
        }

        // Verify image content with getimagesizefromstring
        $imageInfo = @getimagesizefromstring($decoded);
        if ($imageInfo === false || empty($imageInfo['mime'])) {
            throw ValidationException::withMessages([
                $fieldForError => ['File yang diunggah bukan gambar valid.']
            ]);
        }

        $mime = strtolower($imageInfo['mime']);
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw ValidationException::withMessages([
                $fieldForError => ['Tipe gambar tidak diizinkan. Hanya format JPEG, PNG, dan WebP yang diperbolehkan.']
            ]);
        }

        $extension = self::ALLOWED_MIMES[$mime];
        $randomName = Str::random(32);
        $fileName = rtrim($directory, '/') . '/' . $prefix . '_' . $randomName . '.' . $extension;

        Storage::disk($disk)->put($fileName, $decoded);

        return $fileName;
    }
}
