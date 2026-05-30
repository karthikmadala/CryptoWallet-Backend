<?php

namespace App\Services;

use App\Enums\LogoType;
use App\Models\AppSetting;
use App\Models\IcoToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BrandingService
{
    private const CACHE_KEY = 'app_branding_logo';
    private const CACHE_TTL = 3600;

    public function resolve(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->computeLogo();
        });
    }

    public function autoAssign(IcoToken $token): void
    {
        $setting = AppSetting::instance();
        $setting->application_logo_type = LogoType::Ico;
        $setting->selected_ico_token_id = $token->id;
        $setting->application_logo_path = null;
        $setting->save();

        $this->clearCache();
    }

    public function save(array $data): AppSetting
    {
        $setting = AppSetting::instance();

        if ($data['type'] === 'ico') {
            $setting->application_logo_type = LogoType::Ico;
            $setting->selected_ico_token_id = $data['ico_token_id'];
            $setting->application_logo_path = null;
        } else {
            // Delete old custom logo if path is being replaced
            if ($setting->application_logo_path && isset($data['application_logo_path'])) {
                Storage::disk('public')->delete($setting->application_logo_path);
            }
            $setting->application_logo_type  = LogoType::Custom;
            $setting->selected_ico_token_id  = null;
            $setting->application_logo_path  = $data['application_logo_path'];
        }

        $setting->save();
        $this->clearCache();

        return $setting->fresh();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function current(): ?AppSetting
    {
        return AppSetting::instance()->load('selectedToken');
    }

    private function computeLogo(): string
    {
        $setting = AppSetting::instance();

        if ($setting->application_logo_type === LogoType::Custom && $setting->application_logo_path) {
            return asset('storage/' . $setting->application_logo_path);
        }

        if ($setting->application_logo_type === LogoType::Ico && $setting->selected_ico_token_id) {
            $token = IcoToken::find($setting->selected_ico_token_id);
            if ($token?->logo_path) {
                return asset('storage/' . $token->logo_path);
            }
            if ($token?->logo_url) {
                return $token->logo_url;
            }
        }

        // Fallback: use stored fallback path or default
        if ($setting->fallback_logo_path) {
            return asset('storage/' . $setting->fallback_logo_path);
        }

        return $this->defaultLogo();
    }

    private function defaultLogo(): string
    {
        return asset('images/default-logo.png');
    }
}
