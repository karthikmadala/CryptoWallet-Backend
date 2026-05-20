<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandingResource;
use App\Http\Resources\IcoTokenResource;
use App\Models\AppSetting;
use App\Models\IcoToken;
use App\Services\BrandingService;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppBrandingController extends Controller
{
    public function __construct(
        private readonly BrandingService  $branding,
        private readonly FileUploadService $files,
    ) {}

    // Public — no auth — used by Angular APP_INITIALIZER
    public function show(): JsonResponse
    {
        $setting = AppSetting::first();

        return api_response(true, 'Branding retrieved.', [
            'logo_url'  => $this->branding->resolve(),
            'logo_type' => $setting?->application_logo_type instanceof \App\Enums\LogoType
                ? $setting->application_logo_type->value
                : ($setting?->application_logo_type ?? 'custom'),
        ]);
    }

    // Admin — GET current branding config + all tokens that have logos
    public function adminShow(): JsonResponse
    {
        $setting = $this->branding->current();
        $tokens  = IcoToken::whereNotNull('logo_path')->orWhereNotNull('logo_url')
            ->orderBy('name')->get();

        return api_response(true, 'Branding config retrieved.', [
            'branding' => $setting ? new BrandingResource($setting) : null,
            'tokens'   => IcoTokenResource::collection($tokens),
        ]);
    }

    // Admin — POST save branding selection
    public function adminSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'         => 'required|in:ico,custom',
            'ico_token_id' => 'required_if:type,ico|uuid|exists:ico_tokens,id',
            'custom_logo'  => [
                'required_if:type,custom',
                'nullable',
                'image',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
        ]);

        $saveData = ['type' => $data['type']];

        if ($data['type'] === 'ico') {
            $saveData['ico_token_id'] = $data['ico_token_id'];
        } else {
            $existing = AppSetting::first()?->application_logo_path;
            $upload   = $this->files->storeCustomLogo($request->file('custom_logo'), $existing);
            $saveData['application_logo_path'] = $upload['path'];
        }

        $setting = $this->branding->save($saveData);

        return api_response(true, 'Branding updated.', [
            'branding' => new BrandingResource($setting),
        ]);
    }
}
