<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    public function test_redirect_relative_behavior(): void
    {
        Route::get('redirect-test-xyz', fn () => redirect('/dashboard'));

        $res = $this->get('http://fitra.localhost/redirect-test-xyz');
        fwrite(STDERR, "status: " . $res->getStatusCode() . PHP_EOL);
        fwrite(STDERR, "location: " . ($res->headers->get('Location') ?? 'null') . PHP_EOL);
        $this->assertTrue(true);
    }
}
