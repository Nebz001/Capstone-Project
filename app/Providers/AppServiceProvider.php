<?php

namespace App\Providers;

use App\View\Composers\LoginAnnouncementsComposer;
use App\View\Composers\OrganizationNavbarComposer;
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
    }
}
