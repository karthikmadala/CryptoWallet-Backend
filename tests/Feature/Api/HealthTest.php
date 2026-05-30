<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_consistent_json_payload(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'timestamp',
                    'environment',
                    'checks' => [
                        'database' => ['status'],
                        'cache' => ['status', 'store'],
                        'queue' => ['status', 'connection'],
                    ],
                ],
                'errors',
            ]);
    }
}
