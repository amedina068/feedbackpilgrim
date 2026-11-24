<?php

namespace Tests\Unit\Api\V1\Public;

use App\Http\Controllers\Api\V1\Public\InitController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class InitControllerUnitTest extends TestCase
{
    public function test_controller_exists(): void
    {
        $controller = new InitController;
        $this->assertInstanceOf(InitController::class, $controller);
    }

    public function test_controller_has_index_method(): void
    {
        $controller = new InitController;
        $this->assertTrue(method_exists($controller, 'index'));
    }

    public function test_controller_uses_handles_init_requests_trait(): void
    {
        $controller = new InitController;
        $traits = class_uses($controller);
        $this->assertContains('App\Http\Traits\HandlesInitRequests', $traits);
    }

    public function test_index_method_returns_json_response_type(): void
    {
        $reflection = new \ReflectionMethod(InitController::class, 'index');
        $returnType = $reflection->getReturnType();

        $this->assertEquals(JsonResponse::class, $returnType->getName());
    }

    public function test_controller_extends_base_controller(): void
    {
        $controller = new InitController;
        $this->assertInstanceOf(Controller::class, $controller);
    }
}
