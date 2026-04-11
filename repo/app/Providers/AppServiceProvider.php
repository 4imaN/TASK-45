<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\LoanRequest;
use App\Models\Checkout;
use App\Models\ReservationRequest;
use App\Models\FileAsset;
use App\Models\TransferRequest;
use App\Models\RecommendationBatch;
use App\Models\EntitlementGrant;
use App\Policies\LoanRequestPolicy;
use App\Policies\CheckoutPolicy;
use App\Policies\ReservationRequestPolicy;
use App\Policies\FileAssetPolicy;
use App\Policies\TransferRequestPolicy;
use App\Policies\RecommendationBatchPolicy;
use App\Policies\EntitlementGrantPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected array $policies = [
        LoanRequest::class => LoanRequestPolicy::class,
        Checkout::class => CheckoutPolicy::class,
        ReservationRequest::class => ReservationRequestPolicy::class,
        FileAsset::class => FileAssetPolicy::class,
        TransferRequest::class => TransferRequestPolicy::class,
        RecommendationBatch::class => RecommendationBatchPolicy::class,
        EntitlementGrant::class => EntitlementGrantPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Force HTTPS URL generation when behind a reverse proxy
        if (config('app.url') && str_starts_with(config('app.url'), 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Admin gate
        Gate::define('admin', fn(User $user) => $user->isAdmin());

        // Staff gate
        Gate::define('staff', fn(User $user) => $user->isAdmin() || $user->isTeacher() || $user->isTA());
    }
}

