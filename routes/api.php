<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockchainController;
use App\Http\Controllers\Api\V1\ChainInfoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ICOController;
use App\Http\Controllers\Api\V1\IcoSaleController;
use App\Http\Controllers\Api\V1\KycController;
use App\Http\Controllers\Api\V1\PortfolioController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StakingController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletGenerationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', [HealthController::class, 'show']);
    Route::get('chains', [ChainInfoController::class, 'index']);

    // Public branding endpoint — no auth required
    Route::get('app/branding', [\App\Http\Controllers\Api\V1\AppBrandingController::class, 'show']);

    Route::prefix('auth')->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('register', [AuthController::class, 'register']);
            Route::post('login', [AuthController::class, 'login']);
            Route::post('metamask/nonce', [BlockchainController::class, 'nonce']);
            Route::post('metamask/verify', [AuthController::class, 'metamaskVerify']);
        });

        Route::middleware('throttle:otp-verify')->post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::middleware('throttle:otp-resend')->post('resend-otp', [AuthController::class, 'resendOtp']);
        Route::middleware('throttle:forgot-password')->post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        Route::get('google/redirect', [AuthController::class, 'googleRedirect']);
        Route::get('google/callback', [AuthController::class, 'googleCallback']);

        Route::middleware(['auth:sanctum', 'check.token.expiry', 'store.api.session'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });
    });

    Route::middleware(['auth:sanctum', 'check.token.expiry', 'store.api.session'])->group(function (): void {
        // Profile + KYC always accessible — user must reach KYC page to complete verification
        Route::prefix('profile')->group(function (): void {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::patch('change-password', [ProfileController::class, 'changePassword']);
            Route::get('internal-recipients', [ProfileController::class, 'internalRecipients']);
        });

        Route::prefix('kyc')->group(function (): void {
            Route::get('/', [KycController::class, 'index']);
            Route::post('documents/{document}', [KycController::class, 'upload']);
            Route::get('submissions/{submission}/download', [KycController::class, 'download']);
        });

        // All other user features require KYC when admin has enabled enforcement
        Route::middleware('kyc.completed')->group(function (): void {
            Route::prefix('wallets')->group(function (): void {
                Route::get('/', [WalletController::class, 'index']);
                Route::post('/', [WalletController::class, 'store']);
                Route::delete('{wallet}', [WalletController::class, 'destroy']);
                Route::post('metamask/nonce', [WalletController::class, 'metamaskNonce']);
                Route::post('metamask/verify', [WalletController::class, 'metamaskVerify']);
            });

            Route::middleware('throttle:portfolio')->group(function (): void {
                Route::get('portfolio', [PortfolioController::class, 'index']);
                Route::get('portfolio/chain/{chain}', [PortfolioController::class, 'chain']);
                Route::get('portfolio/{wallet}', [PortfolioController::class, 'show']);
            });

            Route::get('chain/{chain}/address/{address}/info', [ChainInfoController::class, 'info']);

            Route::prefix('transactions')->group(function (): void {
                Route::post('prepare', [TransactionController::class, 'prepare']);
                Route::get('/', [TransactionController::class, 'index']);
                Route::get('{transaction}', [TransactionController::class, 'show']);
                Route::post('{transaction}/status', [TransactionController::class, 'checkStatus']);

                Route::middleware('throttle:broadcast')->group(function (): void {
                    Route::post('record', [TransactionController::class, 'record']);
                    Route::post('/', [TransactionController::class, 'store']);
                    Route::post('{transaction}/cancel', [TransactionController::class, 'cancel']);
                    Route::post('sign', [TransactionController::class, 'sign']);
                    Route::post('broadcast', [TransactionController::class, 'broadcast']);
                });
            });

            Route::prefix('blockchain')->group(function (): void {
                Route::get('token-details', [BlockchainController::class, 'tokenDetails']);
                Route::post('balance', [BlockchainController::class, 'erc20Balance']);
                Route::post('native-balance', [BlockchainController::class, 'nativeBalance']);
                Route::post('receipt', [BlockchainController::class, 'transactionReceipt']);
                Route::get('gas-price/{chain}', [BlockchainController::class, 'gasPrice']);
                Route::post('nonce', [BlockchainController::class, 'nonce']);
            });

            Route::prefix('wallet-gen')->group(function (): void {
                Route::get('mnemonic', [WalletGenerationController::class, 'mnemonic']);
                Route::post('address', [WalletGenerationController::class, 'createAddress']);
                Route::post('internal', [WalletGenerationController::class, 'createInternalWallet']);
                Route::post('reveal-key', [WalletGenerationController::class, 'revealKey']);
            });

            Route::prefix('staking')->group(function (): void {
                Route::get('user', [StakingController::class, 'userDetails']);
                Route::get('plan', [StakingController::class, 'planDetails']);
                Route::middleware('throttle:broadcast')->group(function (): void {
                    Route::post('prepare/stake', [StakingController::class, 'prepareStake']);
                    Route::post('prepare/withdraw', [StakingController::class, 'prepareWithdraw']);
                });
            });

            // ── ICO legacy (Phase 1 — preserved) ─────────────────────────────
            Route::prefix('ico')->group(function (): void {
                Route::middleware('throttle:broadcast')->group(function (): void {
                    Route::post('buy/prepare', [ICOController::class, 'prepareBuyTokens']);
                    Route::post('buy', [ICOController::class, 'selfServiceBuyTokens']);
                    Route::post('purchase/confirm', [ICOController::class, 'confirmPurchase']);
                });
            });

            // ── ICO Phase 2 — multi-sale launchpad ───────────────────────────
            Route::prefix('ico')->middleware(\App\Http\Middleware\EnsureVerifiedUser::class)->group(function (): void {
                Route::get('tokens', [IcoSaleController::class, 'tokens']);
                Route::get('tokens/{tokenId}/sale', [IcoSaleController::class, 'activeSale']);
                Route::get('sales/{saleId}', [IcoSaleController::class, 'show']);
                Route::get('purchases', [IcoSaleController::class, 'purchaseHistory']);

                Route::middleware('throttle:broadcast')->group(function (): void {
                    Route::post('sales/{saleId}/prepare', [IcoSaleController::class, 'preparePurchase']);
                });
            });
        }); // end kyc.completed

        Route::middleware('role:admin')->group(base_path('routes/admin.php'));
    });
});
