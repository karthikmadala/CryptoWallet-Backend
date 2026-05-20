<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ICOController;
use App\Http\Controllers\Api\V1\IcoAdminController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\StakingController;
use App\Http\Controllers\Api\V1\StorageLogController;
use App\Http\Controllers\Api\V1\WalletGenerationController;
use Illuminate\Support\Facades\Route;

Route::get('admin/health', [HealthController::class, 'admin']);
Route::get('admin/users', [AdminController::class, 'users'])
    ->middleware('permission:users.view');
Route::get('admin/users/{userId}', [AdminController::class, 'userDetails'])
    ->middleware('permission:users.view');
Route::patch('admin/users/{userId}/menu-restrictions', [AdminController::class, 'updateMenuRestrictions'])
    ->middleware('permission:users.edit');
Route::get('admin/logs', [AdminController::class, 'logs'])
    ->middleware('permission:admin.access');
Route::get('admin/storage-logs', [StorageLogController::class, 'index'])
    ->middleware('permission:admin.access');
Route::get('admin/storage-logs/tail', [StorageLogController::class, 'tail'])
    ->middleware('permission:admin.access');
Route::get('admin/wallets', [AdminController::class, 'wallets'])
    ->middleware('permission:wallets.view');
Route::get('admin/transactions', [AdminController::class, 'transactions'])
    ->middleware('permission:transactions.view');
Route::get('admin/tokens', [AdminController::class, 'tokens'])
    ->middleware('permission:tokens.view');
Route::post('admin/tokens', [AdminController::class, 'createToken'])
    ->middleware('permission:tokens.create');
Route::put('admin/tokens/{token}', [AdminController::class, 'updateToken'])
    ->middleware('permission:tokens.edit');
Route::delete('admin/tokens/{token}', [AdminController::class, 'deleteToken'])
    ->middleware('permission:tokens.delete');
Route::patch('admin/tokens/{token}/status', [AdminController::class, 'toggleTokenStatus'])
    ->middleware('permission:tokens.toggle');

// Admin wallet generation (full key pair)
Route::post('admin/wallet-gen/keypair', [WalletGenerationController::class, 'createKeypair']);

// Admin staking (backend-signed, uses service wallet)
Route::post('admin/staking/stake', [StakingController::class, 'executeStake']);
Route::post('admin/staking/withdraw', [StakingController::class, 'executeWithdraw']);

// Admin ICO legacy (Phase 1 — backend-signed)
Route::post('admin/ico/sign', [ICOController::class, 'createSign']);
Route::post('admin/ico/buy', [ICOController::class, 'executeBuyTokens']);

// Admin ICO Phase 2 — token + sale management
Route::prefix('admin/ico')->group(function (): void {
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

// Admin analytics dashboard
Route::get('admin/analytics', [AdminController::class, 'analytics'])
    ->middleware('permission:admin.access');

// Role & permission management
Route::get('admin/roles', [RoleController::class, 'index']);
Route::post('admin/roles', [RoleController::class, 'store']);
Route::put('admin/roles/{role}', [RoleController::class, 'update']);
Route::delete('admin/roles/{role}', [RoleController::class, 'destroy']);
Route::get('admin/permissions', [RoleController::class, 'permissions']);
Route::patch('admin/users/{userId}/role', [RoleController::class, 'assignRole']);
Route::patch('admin/users/{userId}/status', [RoleController::class, 'updateStatus']);
