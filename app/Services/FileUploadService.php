<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    public function storeLogo(UploadedFile $file, ?string $existing): array
    {
        return $this->storeFile($file, 'ico/logos', $existing);
    }

    public function storeWhitepaper(UploadedFile $file, ?string $existing): array
    {
        return $this->storeFile($file, 'ico/whitepapers', $existing);
    }

    public function storeCustomLogo(UploadedFile $file, ?string $existing): array
    {
        return $this->storeFile($file, 'branding', $existing);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function storeFile(UploadedFile $file, string $directory, ?string $existing): array
    {
        $filename = Str::uuid() . '.' . ($file->guessExtension() ?? 'bin');
        $path     = $file->storeAs($directory, $filename, 'public');

        if ($path === false) {
            throw new \RuntimeException("Failed to store file in {$directory}. Disk may be full or unwritable.");
        }

        // Delete old file only after new one is safely stored
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        return [
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ];
    }
}
