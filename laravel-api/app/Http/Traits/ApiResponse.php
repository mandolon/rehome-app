<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponse
{
    /**
     * Return a success response with data.
     */
    protected function successResponse(
        $data = null, 
        string $message = 'Success', 
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'data' => $data,
            'meta' => array_merge([
                'success' => true,
                'message' => $message,
                'timestamp' => now()->toISOString()
            ], $meta),
            'errors' => null
        ];

        return response()->json($response, $status);
    }

    /**
     * Return an error response.
     */
    protected function errorResponse(
        string $message = 'An error occurred',
        int $status = 400,
        array $errors = [],
        array $meta = []
    ): JsonResponse {
        $response = [
            'data' => null,
            'meta' => array_merge([
                'success' => false,
                'message' => $message,
                'timestamp' => now()->toISOString()
            ], $meta),
            'errors' => empty($errors) ? [$message] : $errors
        ];

        return response()->json($response, $status);
    }

    /**
     * Return a resource response with proper formatting.
     */
    protected function resourceResponse(
        JsonResource|ResourceCollection $resource,
        string $message = 'Success',
        int $status = 200,
        array $meta = []
    ): JsonResponse {
        $data = $resource->toArray(request());
        
        return $this->successResponse($data, $message, $status, $meta);
    }

    /**
     * Return a no content response.
     */
    protected function noContentResponse(string $message = 'Success'): JsonResponse
    {
        return $this->successResponse(null, $message, 204);
    }

    /**
     * Return a created response.
     */
    protected function createdResponse(
        $data = null,
        string $message = 'Created successfully'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Return a not found response.
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Return a forbidden response.
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return a validation error response.
     */
    protected function validationErrorResponse(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }
}