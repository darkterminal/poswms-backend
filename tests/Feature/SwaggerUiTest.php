<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerUiTest extends TestCase
{
    /**
     * Test that the Swagger UI documentation page loads successfully.
     */
    public function test_swagger_ui_page_loads(): void
    {
        $response = $this->withoutMiddleware()->get('/docs/api');

        $response->assertStatus(200)
            ->assertSee('Swagger UI', false)
            ->assertSee('POS WMS Backend API Documentation');
    }

    /**
     * Test that the OpenAPI JSON endpoint returns valid JSON.
     */
    public function test_openapi_json_endpoint(): void
    {
        $response = $this->withoutMiddleware()->get('/api/v1/docs/openapi.json');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'openapi',
                'info',
                'servers',
                'tags',
                'paths',
            ]);
    }

    /**
     * Test that the OpenAPI JSON contains expected API information.
     */
    public function test_openapi_json_contains_api_info(): void
    {
        $response = $this->withoutMiddleware()->get('/api/v1/docs/openapi.json');

        $response->assertJson([
            'info' => [
                'title' => 'POS WMS Backend API',
                'version' => '1.0.0',
            ],
        ]);
    }

    /**
     * Test that the OpenAPI JSON contains expected tags.
     */
    public function test_openapi_json_contains_tags(): void
    {
        $response = $this->withoutMiddleware()->get('/api/v1/docs/openapi.json');

        $response->assertJsonFragment(['name' => 'Authentication'])
            ->assertJsonFragment(['name' => 'Stores'])
            ->assertJsonFragment(['name' => 'Warehouses'])
            ->assertJsonFragment(['name' => 'Products']);
    }

    /**
     * Test that the OpenAPI JSON contains authentication paths.
     */
    public function test_openapi_json_contains_auth_paths(): void
    {
        $response = $this->withoutMiddleware()->get('/api/v1/docs/openapi.json');

        $response->assertJsonStructure([
            'paths' => [
                '/api/v1/auth/login',
                '/api/v1/tenants/{tenant_id}/auth/logout',
            ],
        ]);
    }
}
