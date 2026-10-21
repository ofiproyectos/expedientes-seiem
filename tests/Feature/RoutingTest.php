<?php

namespace Tests\Feature;

use Tests\TestCase;

class RoutingTest extends TestCase
{
    public function test_home_redirects_to_expedientes(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/expedientes');
    }
}
