<?php

namespace Baracod\Larastarterkit\Helper;

use Throwable;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Baracod\Larastarterkit\Traits\CaseConvert;
use Illuminate\Validation\ValidationException;

/**
 * Class ApiResponse
 *
 * Helper to format and send standardized API responses.
 */
class ApiResponse
{
    use CaseConvert;
    /**
     * Builds the base response structure.
     *
     * @param bool $success Indicates if the request was successful.
     * @param string|null $message Message describing the result.
     * @param mixed|null $data Data to return.
     * @param mixed|null $errors Errors to return.
     * @return array
     */
    private static function buildResponse(bool $success, ?string $message, $data = null, $errors = null): array
    {
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        // Converts data keys to camelCase and adds them if they exist
        if ($data !== null) {
            $response['data'] = self::toCamel($data);
        }

        // Adds errors if they exist
        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    /**
     * Sends the final JSON response.
     *
     * @param array $data The response data.
     * @param int $statusCode The HTTP status code.
     * @return JsonResponse
     */
    private static function send(array $data, int $statusCode): JsonResponse
    {
        return response()->json($data, $statusCode);
    }

    /**
     * Success response (HTTP 200 OK).
     *
     * @param mixed|null $data Data to return.
     * @param string $message Success message.
     * @param int $statusCode HTTP status code.
     * @return JsonResponse
     */
    public static function success($data = null, string $message = 'Operation successful.', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        return self::send(self::buildResponse(true, $message, $data), $statusCode);
    }

    /**
     * Response for a successful creation (HTTP 201 Created).
     *
     * @param mixed|null $data Data of the created resource.
     * @param string $message Success message.
     * @return JsonResponse
     */
    public static function created($data = null, string $message = 'Resource created successfully.'): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Response for a successful update (HTTP 200 OK).
     *
     * @param mixed|null $data Data of the modified resource.
     * @param string $message Success message.
     * @return JsonResponse
     */
    public static function edited($data = null, string $message = 'Resource modified successfully.'): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_OK);
    }

    /**
     * No content response (HTTP 204 No Content).
     *
     * @param string $message Information message.
     * @return JsonResponse
     */
    public static function noContent(string $message = 'No content.'): JsonResponse
    {
        // A 204 status should not have a response body, but we might want to return a message in some cases.
        // If a true 204 is required, `response()->noContent()` can be returned.
        // Here, we return a 200 with a message for more clarity on the client side.
        return self::success(null, $message, Response::HTTP_OK);
    }

    /**
     * General error response.
     *
     * @param string $message Error message.
     * @param int $statusCode HTTP status code.
     * @param mixed|null $errors Error details.
     * @return JsonResponse
     */
    public static function error(string $message, int $statusCode = Response::HTTP_BAD_REQUEST, $errors = null): JsonResponse
    {
        return self::send(self::buildResponse(false, $message, null, $errors), $statusCode);
    }



    /**
     * Response for a resource not found (HTTP 404 Not Found).
     *
     * @param string $message Error message.
     * @return JsonResponse
     */
    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Response for a validation error (HTTP 422 Unprocessable Entity).
     *
     * @param ValidationException $exception The validation exception.
     * @return JsonResponse
     */
    public static function validation(ValidationException $exception): JsonResponse
    {
        return self::error('The given data was invalid.', Response::HTTP_UNPROCESSABLE_ENTITY, $exception->errors());
    }

    /**
     * Response for an unauthorized access (HTTP 401 Unauthorized).
     *
     * @param string $message Error message.
     * @return JsonResponse
     */
    public static function unauthorized(string $message = 'Unauthorized.'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }
    /**
     * Response for an account locked access (HTTP 423 Locked).
     *
     * @param string $message Error message.
     * @return JsonResponse
     */
    public static function locked(string $message = 'Account locked.'): JsonResponse
    {
        return self::error($message, Response::HTTP_LOCKED);
    }
    /**
     * Response for a forbidden access (HTTP 403 Forbidden).
     *
     * @param string $message Error message.
     * @return JsonResponse
     */
    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Response for an internal server error (HTTP 500 Internal Server Error).
     *
     * @param Throwable $exception The exception that caused the error.
     * @param string $message Error message for the client.
     * @return JsonResponse
     */
    public static function serverError(Throwable $exception, string $message = 'Internal server error.'): JsonResponse
    {
        // Log the error for debugging
        Log::error($exception);

        $errorDetails = null;
        // In debug mode, provide more details about the error
        if (config('app.debug')) {
            $errorDetails = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return self::error($message, Response::HTTP_INTERNAL_SERVER_ERROR, $errorDetails);
    }
}
