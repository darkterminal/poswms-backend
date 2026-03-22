<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        // Handle validation errors
        if ($e instanceof ValidationException) {
            return $this->handleValidationException($e);
        }

        // Handle authentication errors
        if ($e instanceof AuthenticationException) {
            return $this->handleAuthenticationException($e);
        }

        // Handle authorization errors
        if ($e instanceof AccessDeniedHttpException) {
            return $this->handleAccessDeniedException($e);
        }

        // Handle not found errors
        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return $this->handleNotFoundException($e);
        }

        // Handle other HTTP exceptions
        if ($e instanceof HttpException) {
            return $this->handleHttpException($e);
        }

        // Handle all other exceptions as internal server errors
        return $this->handleGenericException($e);
    }

    /**
     * Handle validation exceptions.
     */
    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $errors[$field] = is_array($messages) ? $messages[0] : $messages;
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'validation_error',
                'message' => 'The given data was invalid.',
                'details' => $errors,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], $e->status);
    }

    /**
     * Handle authentication exceptions.
     */
    protected function handleAuthenticationException(AuthenticationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
                'message' => 'Unauthenticated. Please provide valid authentication credentials.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 401);
    }

    /**
     * Handle access denied exceptions.
     */
    protected function handleAccessDeniedException(AccessDeniedHttpException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'access_denied',
                'message' => $e->getMessage() ?: 'Access denied. You do not have permission to perform this action.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 403);
    }

    /**
     * Handle not found exceptions.
     */
    protected function handleNotFoundException(Throwable $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'not_found',
                'message' => 'The requested resource was not found.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 404);
    }

    /**
     * Handle generic HTTP exceptions.
     */
    protected function handleHttpException(HttpException $e): JsonResponse
    {
        $statusCode = $e->getStatusCode();

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'http_error_' . $statusCode,
                'message' => $e->getMessage() ?: 'An HTTP error occurred.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], $statusCode);
    }

    /**
     * Handle generic exceptions.
     */
    protected function handleGenericException(Throwable $e): JsonResponse
    {
        // Log the exception for debugging
        \Log::error('API Exception: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'internal_server_error',
                'message' => 'An internal server error occurred.',
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 500);
    }

    /**
     * Register the exception handling callbacks.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
