<?php

namespace App\Providers;

use App\Models\OrganizationSubmission;
use App\Policies\OrganizationSubmissionPolicy;
use App\View\Composers\LoginAnnouncementsComposer;
use App\View\Composers\OrganizationNavbarComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        View::composer(
            ['layouts.organization', 'layouts.admin'],
            LoginAnnouncementsComposer::class
        );

        View::composer('organizations.partials.navbar', OrganizationNavbarComposer::class);

        // Submission file viewing: officers/advisers/admins (see policy for the
        // exact rules). Registered explicitly so it works regardless of policy
        // auto-discovery configuration.
        Gate::policy(OrganizationSubmission::class, OrganizationSubmissionPolicy::class);
    }
}
