<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Crypto\BlockchainNodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        $checks = [
            'database'     => $this->checkDatabase(),
            'cache'        => $this->checkCache(),
            'queue'        => $this->checkQueue(),
            'node_service' => $this->checkNodeService(),
        ];

        $healthy = collect($checks)->every(
            static fn (array $check): bool => ($check['status'] ?? 'failed') === 'ok'
        );

        return api_response(
            $healthy,
            $healthy ? 'System healthy.' : 'System degraded.',
            [
                'timestamp' => now()->toIso8601String(),
                'environment' => config('app.env'),
                'checks' => $checks,
            ],
            null,
            $healthy ? 200 : 503
        );
    }

    public function admin(): JsonResponse
    {
        return api_response(true, 'Admin route accessible.');
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size('default');
            return ['status' => 'ok', 'connection' => config('queue.default'), 'depth' => $size];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'connection' => config('queue.default')];
        }
    }

    private function checkNodeService(): array
    {
        $url = config('crypto.node_service.url');
        if (! $url) {
            return ['status' => 'unconfigured'];
        }
        try {
            app(BlockchainNodeService::class)->getNativePrice(\App\Enums\ChainType::ETH);
            return ['status' => 'ok', 'url' => $url];
        } catch (\Throwable) {
            return ['status' => 'degraded', 'url' => $url];
        }
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (\Throwable $exception) {
            report($exception);

            return ['status' => 'failed'];
        }
    }

    private function checkCache(): array
    {
        $key = 'health-check:' . now()->timestamp;

        try {
            Cache::put($key, 'ok', now()->addSeconds(10));

            return [
                'status' => Cache::get($key) === 'ok' ? 'ok' : 'failed',
                'store' => config('cache.default'),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'status' => 'failed',
                'store' => config('cache.default'),
            ];
        } finally {
            Cache::forget($key);
        }
    }
}
