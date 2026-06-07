<?php

namespace Tests\Feature\Api;

use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_and_admin_can_approve_required_kyc(): void
    {
        Storage::fake('local');

        $document = KycDocument::create([
            'name'        => 'Passport',
            'slug'        => 'passport',
            'description' => 'Government issued ID',
            'is_required' => true,
            'is_active'   => true,
            'sort_order'  => 1,
        ]);

        $user = User::factory()->create([
            'account_status' => 'pending_verification',
        ]);

        Sanctum::actingAs($user);

        $this
            ->postJson("/api/v1/kyc/documents/{$document->id}", [
                'file' => UploadedFile::fake()->image('passport.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.submission.status', KycSubmission::STATUS_PENDING);

        $this->assertDatabaseHas('kyc_submissions', [
            'user_id'         => $user->id,
            'kyc_document_id' => $document->id,
            'status'          => KycSubmission::STATUS_PENDING,
        ]);

        $this->seed(PermissionSeeder::class);
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'role_id' => $adminRole->id]);
        $submission = KycSubmission::firstOrFail();

        Sanctum::actingAs($admin);

        $this
            ->patchJson("/api/v1/admin/kyc/submissions/{$submission->id}/review", [
                'status'      => KycSubmission::STATUS_APPROVED,
                'review_note' => 'Matches account details.',
            ])
            ->assertOk()
            ->assertJsonPath('data.submission.status', KycSubmission::STATUS_APPROVED);

        $this->assertSame('active', $user->fresh()->account_status->value);
    }
}
