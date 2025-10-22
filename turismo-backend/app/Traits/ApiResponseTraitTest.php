<?php

namespace Tests\Unit\Traits;

use Tests\TestCase;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponseTrait;

class ApiResponseTraitTest extends TestCase
{
    use ApiResponseTrait;

    /** @test */
    public function devuelve_respuesta_exitosa()
    {
        $data = ['clave' => 'valor'];
        $message = 'Todo bien';

        $response = $this->successResponse($data, $message);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $response->getData(true));
    }

    /** @test */
    public function devuelve_respuesta_de_error()
    {
        $message = 'Hubo un error';
        $code = 500;

        $response = $this->errorResponse($message, $code);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($code, $response->getStatusCode());
        $this->assertEquals([
            'success' => false,
            'message' => $message
        ], $response->getData(true));
    }

    /** @test */
    public function devuelve_respuesta_de_error_de_validacion()
    {
        $errors = ['nombre' => ['El campo es obligatorio.']];

        $response = $this->validationErrorResponse($errors);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals([
            'success' => false,
            'message' => 'Error de validación',
            'errors' => $errors
        ], $response->getData(true));
    }
}
