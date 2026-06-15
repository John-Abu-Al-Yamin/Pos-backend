<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        array $extra = []
    ): JsonResponse {
        if ($data instanceof LengthAwarePaginator) {
            return response()->json(array_merge([
                'success' => true,
                'status' => $statusCode,
                'message' => $message,
                'data' => $data->items(),
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
            ], $extra), $statusCode);
        }

        if (
            $data instanceof AnonymousResourceCollection &&
            $data->resource instanceof AbstractPaginator
        ) {
            $paginator = $data->resource;

            return response()->json(array_merge([
                'success' => true,
                'status' => $statusCode,
                'message' => $message,
                'data' => $data->collection,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ], $extra), $statusCode);
        }

        return response()->json(array_merge([
            'success' => true,
            'status' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $extra), $statusCode);
    }

    public static function emptyData(
        string $message = 'Success',
        int $statusCode = 201
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'status' => $statusCode,
            'message' => $message,
        ], $statusCode);
    }

    public static function error(
        string $message = 'Error',
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'status' => $statusCode,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }
}
