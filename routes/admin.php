<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ICOController;
use App\Http\Controllers\Api\V1\IcoAdminController;
use App\Http\Controllers\Api\V1\KycAdminController;
use App\Http\Controllers\Api\V1\PaymentSettingsController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\StakingController;
use App\Http\Controllers\Api\V1\StorageLogController;
use App\Http\Controllers\Api\V1\WalletGenerationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::get('health', [HealthController::class, 'admin']);
    Route::get('users', [AdminController::class, 'users'])
        ->middleware('permission:users.view');
    Route::get('users/{userId}', [AdminController::class, 'userDetails'])
        ->middleware('permission:users.view');
    Route::patch('users/{userId}/menu-restrictions', [AdminController::class, 'updateMenuRestrictions'])
        ->middleware('permission:users.edit');
    Route::get('logs', [AdminController::class, 'logs'])
        ->middleware('permission:admin.access');
    Route::get('storage-logs', [StorageLogController::class, 'index'])
        ->middleware('permission:admin.access');
    Route::get('storage-logs/tail', [StorageLogController::class, 'tail'])
        ->middleware('permission:admin.access');
    Route::get('wallets', [AdminController::class, 'wallets'])
        ->middleware('permission:wallets.view');
    Route::get('transactions', [AdminController::class, 'transactions'])
        ->middleware('permission:transactions.view');
    Route::get('tokens', [AdminController::class, 'tokens'])
        ->middleware('permission:tokens.view');
    Route::post('tokens', [AdminController::class, 'createToken'])
        ->middleware('permission:tokens.create');
    Route::put('tokens/{token}', [AdminController::class, 'updateToken'])
        ->middleware('permission:tokens.edit');
    Route::delete('tokens/{token}', [AdminController::class, 'deleteToken'])
        ->middleware('permission:tokens.delete');
    Route::patch('tokens/{token}/status', [AdminController::class, 'toggleTokenStatus'])
        ->middleware('permission:tokens.toggle');

    Route::prefix('kyc')->middleware('permission:kyc.manage')->group(function (): void {
        Route::get('documents', [KycAdminController::class, 'documents']);
        Route::post('documents', [KycAdminController::class, 'storeDocument']);
        Route::put('documents/{document}', [KycAdminController::class, 'updateDocument']);
        Route::get('submissions', [KycAdminController::class, 'submissions']);
        Route::get('submissions/{submission}/download', [KycAdminController::class, 'download']);
        Route::patch('submissions/{submission}/review', [KycAdminController::class, 'review']);
    });

    // Admin wallet generation (full key pair)
    Route::post('wallet-gen/keypair', [WalletGenerationController::class, 'createKeypair']);

    // Admin staking (backend-signed, uses service wallet)
    Route::post('staking/stake', [StakingController::class, 'executeStake']);
    Route::post('staking/withdraw', [StakingController::class, 'executeWithdraw']);

    // Admin ICO legacy (Phase 1 — backend-signed)
    Route::post('ico/sign', [ICOController::class, 'createSign']);
    Route::post('ico/buy', [ICOController::class, 'executeBuyTokens']);

    // Admin ICO Phase 2 — token + sale management
    Route::prefix('ico')->group(function (): void {
        // Token CRUD
        Route::get('tokens', [IcoAdminController::class, 'listTokens']);
        Route::post('tokens', [IcoAdminController::class, 'createToken']);
        Route::put('tokens/{icoToken}', [IcoAdminController::class, 'updateToken']);
        Route::delete('tokens/{icoToken}', [IcoAdminController::class, 'deleteToken']);

        // Sale CRUD + lifecycle
        Route::get('sales', [IcoAdminController::class, 'listSales']);
        Route::post('tokens/{icoToken}/sales', [IcoAdminController::class, 'createSale']);
        Route::put('sales/{icoSale}', [IcoAdminController::class, 'updateSale']);
        Route::patch('sales/{icoSale}/activate', [IcoAdminController::class, 'activateSale']);
        Route::patch('sales/{icoSale}/pause', [IcoAdminController::class, 'pauseSale']);
        Route::patch('sales/{icoSale}/end', [IcoAdminController::class, 'endSale']);

        // Payment methods
        Route::get('sales/{icoSale}/payment-methods', [IcoAdminController::class, 'listPaymentMethods']);
        Route::post('sales/{icoSale}/payment-methods', [IcoAdminController::class, 'createPaymentMethod']);
        Route::put('sales/{icoSale}/payment-methods/{methodId}', [IcoAdminController::class, 'updatePaymentMethod']);
        Route::delete('sales/{icoSale}/payment-methods/{methodId}', [IcoAdminController::class, 'deletePaymentMethod']);

        // Purchase monitoring
        Route::get('purchases', [IcoAdminController::class, 'purchases']);
    });

    Route::prefix('payment-settings')->middleware('permission:ico.manage')->group(function (): void {
        Route::get('/', [PaymentSettingsController::class, 'show']);
        Route::post('wallet', [PaymentSettingsController::class, 'saveWallet']);
        Route::delete('wallet', [PaymentSettingsController::class, 'disconnectWallet']);
    });

    // Admin analytics dashboard
    Route::get('analytics', [AdminController::class, 'analytics'])
        ->middleware('permission:admin.access');

    // Role & permission management
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    Route::get('permissions', [RoleController::class, 'permissions']);
    Route::patch('users/{userId}/role', [RoleController::class, 'assignRole']);
    Route::patch('users/{userId}/status', [RoleController::class, 'updateStatus']);

    // Branding settings (admin)
    Route::get('settings/branding',  [\App\Http\Controllers\Api\V1\AppBrandingController::class, 'adminShow']);
    Route::post('settings/branding', [\App\Http\Controllers\Api\V1\AppBrandingController::class, 'adminSave']);

    // KYC enforcement toggle
    Route::get('settings/kyc', [\App\Http\Controllers\Api\V1\KycSettingsController::class, 'show']);
    Route::patch('settings/kyc', [\App\Http\Controllers\Api\V1\KycSettingsController::class, 'update']);
});
