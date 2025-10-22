<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Return a success JSON response
     *
     * @param array|string $data
     * @param string|null $message
     * @param int $code
     * @return JsonResponse
     */
    protected function successResponse($data, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Return an error JSON response
     *
     * @param string $message
     * @param int $code
     * @param array|null $errors
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $code, ?array $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
    
    /**
     * Return a validation error JSON response
     *
     * @param array $errors
     * @return JsonResponse
     */
    protected function validationErrorResponse(array $errors): JsonResponse
    {
        return $this->errorResponse('Error de validación', 422, $errors);
    }
}