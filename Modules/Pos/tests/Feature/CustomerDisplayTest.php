<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerDisplayTest extends TestCase
{
    public function test_customer_display_has_no_remote_snapshot_or_pairing_api(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());
        $this->assertTrue($routes->contains('customer-display'));
        $this->getJson('/api/v1/pos/customer/viewer/'.\Illuminate\Support\Str::uuid())->assertNotFound();
        $this->assertFalse($routes->contains(fn ($uri) => str_contains($uri, 'pos/viewer/{cartId}')));
    }
}
