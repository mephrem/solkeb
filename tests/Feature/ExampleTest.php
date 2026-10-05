<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Visitors who are not logged in are sent to the login page. */
    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
