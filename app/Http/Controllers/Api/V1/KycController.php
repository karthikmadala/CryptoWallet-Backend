<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\KycDocumentResource;
use App\Http\Resources\KycSubmissionResource;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use App\Services\KycStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KycController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $documents = KycDocument::query()
            ->where('is_active', true)
            ->with(['submissions' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return api_response(true, 'KYC requirements retrieved.', [
            'documents' => KycDocumentResource::collection($documents),
        ]);
    }

    public function upload(Request $request, KycDocument $document, KycStatusService $statusService): JsonResponse
    {
        if (! $document->is_active) {
            return api_response(false, 'This KYC document is not active.', null, null, 422);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $user = $request->user();
        $file = $validated['file'];
        $path = $file->store("kyc/{$user->id}", 'local');

        $existing = KycSubmission::query()
            ->where('user_id', $user->id)
            ->where('kyc_document_id', $document->id)
            ->first();

        if ($existing?->file_path) {
            Storage::disk('local')->delete($existing->file_path);
        }

        $submission = KycSubmission::updateOrCreate(
            ['user_id' => $user->id, 'kyc_document_id' => $document->id],
            [
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'status'        => KycSubmission::STATUS_PENDING,
                'review_note'   => null,
                'reviewed_by'   => null,
                'reviewed_at'   => null,
            ]
        );

        $statusService->refreshUserStatus($user);

        return api_response(true, 'KYC document uploaded for review.', [
            'submission' => new KycSubmissionResource($submission->load('document')),
        ]);
    }

    public function download(Request $request, KycSubmission $submission): BinaryFileResponse
    {
        abort_unless($submission->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return response()->file(
            Storage::disk('local')->path($submission->file_path),
            ['Content-Type' => $submission->mime_type ?? 'application/octet-stream']
        );
    }
}
