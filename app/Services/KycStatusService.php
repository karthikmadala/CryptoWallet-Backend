<?php

namespace App\Services;

use App\Enums\Auth\AccountStatus;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Models\User;

class KycStatusService
{
    public function refreshUserStatus(User $user): void
    {
        if ($user->account_status === AccountStatus::Suspended || $user->account_status === AccountStatus::Locked) {
            return;
        }

        $requiredIds = KycDocument::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->pluck('id');

        if ($requiredIds->isEmpty()) {
            return;
        }

        $approvedCount = KycSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('kyc_document_id', $requiredIds)
            ->where('status', KycSubmission::STATUS_APPROVED)
            ->distinct('kyc_document_id')
            ->count('kyc_document_id');

        $nextStatus = $approvedCount === $requiredIds->count()
            ? AccountStatus::Active
            : AccountStatus::PendingVerification;

        if ($user->account_status !== $nextStatus) {
            $user->forceFill(['account_status' => $nextStatus->value])->save();
        }
    }
}
