<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_public_home_route_is_the_application_entry_point(): void
    {
        $route = collect(Route::getRoutes())->first(
            static fn ($route): bool => $route->getName() === 'public.home'
        );

        $this->assertNotNull($route);
        $this->assertSame('App\Http\Controllers\PublicLandingController@home', $route->getActionName());
    }
}
