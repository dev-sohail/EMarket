<?php

namespace Tests\Feature;

use App\Http\Controllers\Ajax\ProductController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AjaxProductEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_reports_a_bad_request_as_a_validation_error_not_a_server_fault(): void
    {
        $this->expectException(ValidationException::class);

        app(ProductController::class)->variants(Request::create('/', 'GET', []));
    }

    public function test_filter_clamps_an_unbounded_per_page(): void
    {
        $payload = app(ProductController::class)
            ->filter(Request::create('/', 'GET', ['per_page' => '1000000']))
            ->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame(100, $payload['data']['per_page']);
    }

    public function test_filter_survives_malformed_array_parameters(): void
    {
        $response = app(ProductController::class)->filter(Request::create('/', 'GET', [
            'sort_dir' => ['x'],
            'attributes' => 'not-an-array',
        ]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_search_ignores_an_array_query(): void
    {
        $response = app(ProductController::class)->search(Request::create('/', 'GET', ['q' => ['ab']]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->getData(true)['data']);
    }
}
