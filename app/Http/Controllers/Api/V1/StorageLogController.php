<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class StorageLogController extends Controller
{
    /**
     * GET /api/v1/admin/storage-logs
     * Returns a list of all .log files in the storage/logs directory.
     */
    public function index(): JsonResponse
    {
        $logPath = storage_path('logs');
        $files = collect(File::files($logPath))
            ->filter(fn ($file) => $file->getExtension() === 'log')
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => $file->getMTime(),
            ])
            ->sortByDesc('modified_at')
            ->values();

        return api_response(true, 'Log files retrieved', ['files' => $files]);
    }

    /**
     * GET /api/v1/admin/storage-logs/tail
     * Tails the specified log file. If `last_size` is provided, returns content after that byte offset.
     */
    public function tail(Request $request): JsonResponse
    {
        $filename = $request->query('filename', 'laravel.log');
        $lastSize = (int) $request->query('last_size', 0);

        // Basic security check to prevent directory traversal
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.log$/', $filename)) {
            return api_response(false, 'Invalid filename', [], null, 400);
        }

        $filePath = storage_path('logs/' . $filename);

        if (!File::exists($filePath)) {
            return api_response(false, 'Log file not found', [], null, 404);
        }

        $currentSize = File::size($filePath);
        $content = '';

        if ($lastSize < $currentSize) {
            $file = fopen($filePath, 'r');
            
            // If the file is smaller than last_size (e.g. log rotation or truncated), we start from 0
            if ($lastSize > 0 && $lastSize < $currentSize) {
                fseek($file, $lastSize);
            } else if ($lastSize === 0) {
                // If it's the first load and the file is large, we might only want the last N bytes (e.g., 50KB)
                $maxInitialBytes = 50 * 1024;
                if ($currentSize > $maxInitialBytes) {
                    fseek($file, $currentSize - $maxInitialBytes);
                    // Discard the first partial line
                    fgets($file);
                }
            }
            
            while (!feof($file)) {
                $content .= fread($file, 8192);
            }
            fclose($file);
        } else if ($lastSize > $currentSize) {
            // File was truncated or rotated
            $lastSize = 0;
            $currentSize = File::size($filePath);
            $content = File::get($filePath);
        }

        return api_response(true, 'Log tail retrieved', [
            'content' => mb_convert_encoding($content, 'UTF-8', 'UTF-8'),
            'size' => $currentSize,
        ]);
    }
}
