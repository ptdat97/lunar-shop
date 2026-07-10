<?php

namespace Modules\Core\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The single error envelope for `/api/v1/*`: `{ message, errors? }`, whatever
 * the client's Accept header says.
 *
 * Lives here rather than inline in bootstrap/app.php so the two branches that
 * matter — the status/message mapping, and the "response already sent" guard —
 * can be tested directly. `headers_sent()` cannot be faked in-process, so the
 * caller passes it in.
 */
class ApiErrorResponse
{
    /**
     * Build the envelope for a failed API request.
     *
     * @param  bool  $responseAlreadySent  the value of `headers_sent()`
     */
    public static function for(Throwable $e, bool $responseAlreadySent = false): JsonResponse
    {
        // Laravel's shutdown handler (HandleExceptions::renderHttpResponse)
        // calls ->send() on whatever we return, with no headers_sent() guard.
        // A second failure while PHP tears the request down — Redis dying as
        // the cache connection closes, say — would therefore append a second
        // JSON body to a response already on the wire, leaving the client with
        // `{...}{"message":"Server error."}`. Emit nothing: the status line is
        // long gone and the first body is the true one.
        if ($responseAlreadySent) {
            return (new JsonResponse(null, 500))->setContent('');
        }

        $status = static::statusFor($e);

        return new JsonResponse(['message' => static::messageFor($e, $status)], $status);
    }

    public static function statusFor(Throwable $e): int
    {
        return match (true) {
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    /**
     * Exception messages carry connection strings, credentials and file paths,
     * so a 500 always gets a generic message. HTTP exceptions may carry their
     * own, falling back to a per-status default.
     */
    public static function messageFor(Throwable $e, int $status): string
    {
        $message = $e->getMessage();

        if ($message !== '' && $status !== 500) {
            return $message;
        }

        return match ($status) {
            401 => 'Unauthenticated.',
            403 => 'This action is unauthorized.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            429 => 'Too many requests.',
            500 => 'Server error.',
            default => 'Request failed.',
        };
    }
}
