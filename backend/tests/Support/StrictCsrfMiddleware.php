<?php

namespace Tests\Support;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class StrictCsrfMiddleware extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
