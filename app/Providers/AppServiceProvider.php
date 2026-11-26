<?php

namespace App\Providers;

use App\Services\Identity\IdentityProfile;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        app(IdentityProfile::class)->validate();
    }
}
