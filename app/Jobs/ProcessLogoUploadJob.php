<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProcessLogoUploadJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $diskPath,
    ) {}

    public function handle(): void
    {
        $fullPath = Storage::disk('public')->path($this->diskPath);

        if (! file_exists($fullPath)) {
            Log::warning('ProcessLogoUploadJob: file not found', ['path' => $this->diskPath]);
            return;
        }

        try {
            $manager = new ImageManager(new Driver());
            $manager->read($fullPath)
                ->scaleDown(width: 512, height: 512)
                ->save($fullPath);
        } catch (\Throwable $e) {
            Log::error('ProcessLogoUploadJob: optimisation failed', [
                'path'  => $this->diskPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
