<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\DataQualityController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\FileController;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::middleware([\App\Http\Middleware\ForcePasswordChange::class])->group(function () {
        // Student: enrolled classes from permission scopes
        Route::get('/my-classes', [\App\Http\Controllers\Api\UserContextController::class, 'myClasses']);

        // Catalog
        Route::get('/catalog', [CatalogController::class, 'index']);
        Route::get('/catalog/{resource}', [CatalogController::class, 'show']);

        // Loans
        Route::get('/loans', [LoanController::class, 'index']);
        Route::post('/loans', [LoanController::class, 'store'])->middleware(\App\Http\Middleware\CheckHold::class);
        Route::get('/loans/{loan}', [LoanController::class, 'show']);
        Route::post('/loans/{loan}/approve', [LoanController::class, 'approve']);
        Route::post('/loans/{loan}/checkout', [LoanController::class, 'checkout']);
        Route::post('/checkouts/{checkout}/checkin', [LoanController::class, 'checkin']);
        Route::post('/checkouts/{checkout}/renew', [LoanController::class, 'renew']);

        // Reminders
        Route::get('/reminders', [\App\Http\Controllers\Api\UserContextController::class, 'reminders']);
        Route::post('/reminders/{reminder}/acknowledge', [\App\Http\Controllers\Api\UserContextController::class, 'acknowledgeReminder']);

        // Checkouts list for staff
        Route::get('/checkouts', [LoanController::class, 'checkoutsList']);

        // Reservations
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store'])->middleware(\App\Http\Middleware\CheckHold::class);
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
        Route::post('/reservations/{reservation}/approve', [ReservationController::class, 'approve']);
        Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);

        // Transfers
        Route::get('/transfers', [TransferController::class, 'index']);
        Route::post('/transfers', [TransferController::class, 'store'])->middleware(\App\Http\Middleware\CheckHold::class);
        Route::post('/transfers/{transfer}/approve', [TransferController::class, 'approve']);
        Route::post('/transfers/{transfer}/in-transit', [TransferController::class, 'markInTransit']);
        Route::post('/transfers/{transfer}/complete', [TransferController::class, 'complete']);
        Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel']);

        // Memberships
        Route::get('/memberships/tiers', [MembershipController::class, 'tiers']);
        Route::get('/memberships/me', [MembershipController::class, 'myMembership']);
        Route::get('/memberships/packages', [MembershipController::class, 'packages']);
        Route::post('/memberships/redeem-points', [MembershipController::class, 'redeemPoints'])->middleware(\App\Http\Middleware\CheckHold::class);
        Route::post('/memberships/redeem-stored-value', [MembershipController::class, 'redeemStoredValue'])->middleware(\App\Http\Middleware\CheckHold::class);
        Route::post('/memberships/entitlements/{grant}/consume', [MembershipController::class, 'consumeEntitlement'])->middleware(\App\Http\Middleware\CheckHold::class);

        // Recommendations
        Route::post('/recommendations/for-class', [RecommendationController::class, 'forClass']);
        Route::get('/recommendations/batches/{batch}', [RecommendationController::class, 'batchTrace']);
        Route::post('/recommendations/override', [RecommendationController::class, 'override']);

        // Data Quality (staff only)
        Route::prefix('data-quality')->middleware('can:staff')->group(function () {
            Route::get('/stats', [DataQualityController::class, 'stats']);
            Route::post('/import', [DataQualityController::class, 'import']);
            Route::get('/batches', [DataQualityController::class, 'batches']);
            Route::get('/batches/{batch}', [DataQualityController::class, 'batchReport']);
            Route::get('/batches/{batch}/download', [DataQualityController::class, 'downloadReport']);
            Route::get('/remediation', [DataQualityController::class, 'remediationQueue']);
            Route::post('/remediation/{item}', [DataQualityController::class, 'remediateItem']);
            Route::get('/duplicates', [DataQualityController::class, 'duplicates']);
            Route::post('/duplicates/{candidate}', [DataQualityController::class, 'resolveDuplicate']);
            Route::get('/vendor-aliases', [DataQualityController::class, 'vendorAliases']);
            Route::post('/vendor-aliases', [DataQualityController::class, 'createVendorAlias']);
            Route::put('/vendor-aliases/{alias}', [DataQualityController::class, 'updateVendorAlias']);
            Route::get('/manufacturer-aliases', [DataQualityController::class, 'manufacturerAliases']);
            Route::post('/manufacturer-aliases', [DataQualityController::class, 'createManufacturerAlias']);
            Route::put('/manufacturer-aliases/{alias}', [DataQualityController::class, 'updateManufacturerAlias']);
        });

        // Admin
        Route::prefix('admin')->middleware('can:admin')->group(function () {
            Route::get('/stats', [AdminController::class, 'stats']);
            Route::post('/memberships/assign-tier', [\App\Http\Controllers\Api\MembershipController::class, 'assignTier']);
            Route::post('/memberships/deposit', [\App\Http\Controllers\Api\MembershipController::class, 'depositStoredValue']);
            Route::post('/memberships/grant-entitlement', [\App\Http\Controllers\Api\MembershipController::class, 'grantEntitlement']);
            Route::post('/scopes', [AdminController::class, 'assignScope']);
            Route::get('/scopes', [AdminController::class, 'scopes']);
            Route::get('/scopes/user', [AdminController::class, 'userScopes']);
            Route::delete('/scopes/{scope}', [AdminController::class, 'deleteScope']);
            Route::get('/allowlists', [AdminController::class, 'listAllowlists']);
            Route::post('/allowlists', [AdminController::class, 'addAllowlist']);
            Route::delete('/allowlists/{allowlist}', [AdminController::class, 'deleteAllowlist']);
            Route::get('/blacklists', [AdminController::class, 'listBlacklists']);
            Route::post('/blacklists', [AdminController::class, 'addBlacklist']);
            Route::delete('/blacklists/{blacklist}', [AdminController::class, 'deleteBlacklist']);
            Route::get('/holds', [AdminController::class, 'holds']);
            Route::post('/holds', [AdminController::class, 'createHold']);
            Route::post('/holds/{hold}/release', [AdminController::class, 'releaseHold']);
            Route::get('/interventions', [AdminController::class, 'interventionLogs']);
            Route::get('/audit-logs', [AdminController::class, 'auditLogs']);
            Route::get('/audit-logs/export', [AdminController::class, 'exportAuditLogs']);
            Route::post('/reveal-field', [AdminController::class, 'revealField']);
        });

        // Files
        Route::post('/files/upload', [FileController::class, 'upload'])->middleware(\App\Http\Middleware\CheckHold::class);
        Route::get('/files', [FileController::class, 'index']);
        Route::get('/files/{file}/download', [FileController::class, 'download']);
    });
});
