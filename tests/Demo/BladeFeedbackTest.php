<?php

namespace Tests\Demo;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

class BladeFeedbackTest extends TestCase
{
    public function test_missing_route_renders_without_shared_session_errors(): void
    {
        $this->assertNull(View::shared('errors'));

        $this->get('/missing-campusspace-route')->assertNotFound()->assertSee('Halaman tidak ditemukan');

        $this->assertNull(View::shared('errors'));
    }

    public function test_component_displays_shared_validation_errors(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['role' => ['The selected role is invalid.']]));
        View::share('errors', $errors);

        $this->blade('<x-feedback />')->assertSee('The selected role is invalid.');
    }
}
