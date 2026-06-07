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
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KycAdminController extends Controller
{
    public function documents(): JsonResponse
    {
        $documents = KycDocument::query()
            ->withCount('submissions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return api_response(true, 'KYC documents retrieved.', [
            'documents' => KycDocumentResource::collection($documents),
        ]);
    }

    public function storeDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'is_required' => 'required|boolean',
            'is_active'   => 'required|boolean',
            'sort_order'  => 'nullable|integer|min:0|max:65535',
        ]);

        $document = KycDocument::create([
            ...$validated,
            'slug' => Str::slug($validated['name']),
        ]);

        return api_response(true, 'KYC document created.', [
            'document' => new KycDocumentResource($document),
        ], null, 201);
    }

    public function updateDocument(Request $request, KycDocument $document): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'is_required' => 'sometimes|required|boolean',
            'is_active'   => 'sometimes|required|boolean',
            'sort_order'  => 'nullable|integer|min:0|max:65535',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $document->update($validated);

        return api_response(true, 'KYC document updated.', [
            'document' => new KycDocumentResource($document),
        ]);
    }

    public function submissions(Request $request): JsonResponse
    {
        $query = KycSubmission::query()->with(['user', 'document', 'reviewer']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $submissions = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50));

        return api_response(true, 'KYC submissions retrieved.', [
            'submissions' => KycSubmissionResource::collection($submissions),
            'pagination'  => [
                'total'        => $submissions->total(),
                'per_page'     => $submissions->perPage(),
                'current_page' => $submissions->currentPage(),
                'last_page'    => $submissions->lastPage(),
            ],
        ]);
    }

    public function review(Request $request, KycSubmission $submission, KycStatusService $statusService): JsonResponse
    {
        $validated = $request->validate([
            'status'      => 'required|string|in:approved,rejected',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $submission->update([
            'status'      => $validated['status'],
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $statusService->refreshUserStatus($submission->user);

        return api_response(true, 'KYC submission reviewed.', [
            'submission' => new KycSubmissionResource($submission->load(['user', 'document', 'reviewer'])),
        ]);
    }

    public function download(KycSubmission $submission): BinaryFileResponse
    {
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return response()->download(
            Storage::disk('local')->path($submission->file_path),
            $submission->original_name ?: "kyc-submission-{$submission->id}"
        );
    }
}
