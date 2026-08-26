<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if (! app()->runningInConsole()) {
            $root = request()->getSchemeAndHttpHost();

            URL::forceRootUrl($root);
            config(['filesystems.disks.public.url' => $root.'/storage']);
        }
    }
}
