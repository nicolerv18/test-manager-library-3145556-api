<?php

declare(strict_types=1);

namespace App\Adapters\Inbound\Http;

use App\Domain\DomainError;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Renders EVERY failure as the single error body `{code, message, details}` (RNF-4): no traces, SQL nor framework formats. */
final class ErrorResponder
{
    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof DomainError) {
            return self::body(StatusMap::for($e->errorCode), $e->errorCode, $e->getMessage(), $e->details);
        }
        if ($e instanceof NotFoundHttpException || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return self::body(404, DomainError::NOT_FOUND, 'El recurso no existe.');
        }
        if ($e instanceof MethodNotAllowedHttpException) {
            return self::body(405, 'METHOD_NOT_ALLOWED', 'Método no permitido para esta ruta.');
        }
        if ($e instanceof \Illuminate\Http\Exceptions\PostTooLargeException) {
            return self::body(422, DomainError::VALIDATION_ERROR, 'El archivo es demasiado grande.', [['field' => 'file', 'issue' => 'supera el tamaño permitido']]);
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return self::body($e->getStatusCode(), 'REQUEST_REJECTED', 'La petición no pudo procesarse.');
        }

        return self::body(500, 'INTERNAL_ERROR', 'Ocurrió un error inesperado. Intente de nuevo más tarde.');
    }

    /** @param list<array{field: string, issue: string}> $details */
    private static function body(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message, 'details' => $details], $status);
    }
}
