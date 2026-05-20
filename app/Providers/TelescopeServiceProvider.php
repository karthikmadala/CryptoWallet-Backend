<?php

namespace App\Providers;

use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::night();
        $this->hideSensitiveRequestDetails();
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }
        Telescope::hideRequestParameters(['_token']);
        Telescope::hideRequestHeaders(['cookie', 'x-csrf-token', 'x-xsrf-token']);
    }

    protected function gate(): void {}
}
