<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    public function storeLogo(UploadedFile $file, ?string $existing): array
    {
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('ico/logos', $filename, 'public');

        return [
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ];
    }

    public function storeWhitepaper(UploadedFile $file, ?string $existing): array
    {
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $ext      = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $ext;
        $path     = $file->storeAs('ico/whitepapers', $filename, 'public');

        return [
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ];
    }

    public function storeCustomLogo(UploadedFile $file, ?string $existing): array
    {
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs('branding', $filename, 'public');

        return [
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'size'          => $file->getSize(),
        ];
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
