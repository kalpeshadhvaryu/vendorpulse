<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public static function fromResource(
        JsonResource|ResourceCollection $resource,
        string $message = 'OK',
        int $status = Response::HTTP_OK
    ): JsonResponse {
        return $resource
            ->additional([
                'success' => true,
                'message' => $message,
            ])
            ->response()
            ->setStatusCode($status);
    }

    /**
     * @param  array<string, mixed>|object|null  $data
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($data instanceof LengthAwarePaginator) {
            $payload['data'] = $data->items();
            $payload['meta'] = array_merge($meta, [
                'pagination' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
            ]);
        } elseif ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        ?array $errors = null,
        mixed $data = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }
}
