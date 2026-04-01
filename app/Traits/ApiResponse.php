<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API response format for web and mobile apps
 */
trait ApiResponse
{
    /**
     * Success response
     */
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Success response with pagination meta
     */
    protected function successWithPagination(
        mixed $data,
        array $meta,
        string $message = 'Success'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], 200);
    }

    /**
     * Created response (201)
     */
    protected function created(
        mixed $data = null,
        string $message = 'Created successfully'
    ): JsonResponse {
        return $this->success($data, $message, 201);
    }

    /**
     * Error response
     */
    protected function error(
        string $message = 'Error',
        int $statusCode = 400,
        ?string $errorCode = null,
        ?array $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode) {
            $response['code'] = $errorCode;
        }

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Unauthorized response (401)
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401, 'UNAUTHORIZED');
    }

    /**
     * Forbidden response (403)
     */
    protected function forbidden(
        string $message = 'Forbidden',
        ?string $errorCode = null
    ): JsonResponse {
        return $this->error($message, 403, $errorCode ?? 'FORBIDDEN');
    }

    /**
     * Not found response (404)
     */
    protected function notFound(string $message = 'Not found'): JsonResponse
    {
        return $this->error($message, 404, 'NOT_FOUND');
    }

    /**
     * Validation error response (422)
     */
    protected function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->error($message, 422, 'VALIDATION_ERROR', $errors);
    }

    /**
     * Server error response (500)
     */
    protected function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return $this->error($message, 500, 'SERVER_ERROR');
    }
}
