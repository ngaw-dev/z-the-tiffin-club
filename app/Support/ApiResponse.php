<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use JsonSerializable;

/**
 * Uniform JSON envelope for every API response and error.
 *
 * Success: { "message": ..., "data": ..., "status": 200 }
 * Error:   { "message": ..., "errors": {...}|null, "status": 4xx }
 *
 * The exception renderer in bootstrap/app.php funnels every failure through
 * ApiResponse::error() so all endpoints (present and future) return a
 * consistent shape without each controller doing its own formatting.
 */
class ApiResponse
{
    /**
     * @param  array<string, mixed>|Arrayable|JsonSerializable|null  $data
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = [
            'message' => $message,
            'data' => self::normalize($data),
            'status' => $status,
        ];

        return response()->json($payload, $status);
    }

    /**
     * @param  iterable<string, mixed>|Arrayable|JsonSerializable|null  $errors
     */
    public static function error(string $message, int $status = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => self::normalize($errors),
            'status' => $status,
        ], $status);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        return $value;
    }
}
