<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Modules\Core\Support\ApiErrorResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * The `/api/v1/*` error envelope, and the guard that keeps a shutdown-time
 * failure from corrupting a response already on the wire.
 *
 * Laravel's shutdown handler (`HandleExceptions::renderHttpResponse`) calls
 * `->send()` on whatever the exception handler returns, with no `headers_sent()`
 * check. When a request had already failed and flushed its body, a second
 * exception raised while PHP tore the request down — Redis dying as the cache
 * connection closes, say — rendered a *second* JSON body into the same stream,
 * so the client received `{...}{"message":"Server error."}`, which no parser
 * accepts. Reproduced live at `/api/v1/products` with Redis unreachable: 54
 * bytes (the envelope twice) before the fix, 27 (once) after.
 *
 * `headers_sent()` is always false under phpunit, so the flag is passed to
 * ApiErrorResponse explicitly rather than faked.
 */
class ApiErrorAfterSendTest extends TestCase
{
    public function test_nothing_is_emitted_once_the_response_has_been_sent(): void
    {
        $response = ApiErrorResponse::for(new RuntimeException('redis died on shutdown'), responseAlreadySent: true);

        // A second body would corrupt the JSON already delivered to the client.
        $this->assertSame('', $response->getContent());
    }

    public function test_a_normal_failure_returns_the_error_envelope(): void
    {
        $response = ApiErrorResponse::for(new RuntimeException('boom'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['message' => 'Server error.'], json_decode($response->getContent(), true));
    }

    public function test_a_500_never_leaks_the_internal_message(): void
    {
        $response = ApiErrorResponse::for(new RuntimeException('SQLSTATE user=root password=hunter2'));

        $this->assertStringNotContainsString('hunter2', $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    /**
     * @return array<string, array{0: \Throwable, 1: int}>
     */
    public static function exceptionStatuses(): array
    {
        return [
            'unauthenticated' => [new AuthenticationException, 401],
            'forbidden' => [new AuthorizationException, 403],
            'model missing' => [new ModelNotFoundException, 404],
            'http not found' => [new NotFoundHttpException, 404],
            'unexpected' => [new RuntimeException('x'), 500],
        ];
    }

    #[DataProvider('exceptionStatuses')]
    public function test_exceptions_map_to_their_status(\Throwable $e, int $expected): void
    {
        $this->assertSame($expected, ApiErrorResponse::for($e)->getStatusCode());
    }

    public function test_the_envelope_is_applied_to_api_requests_end_to_end(): void
    {
        // Through the real handler + the bootstrap render callback.
        $response = app(ExceptionHandler::class)
            ->render(Request::create('/api/v1/products', 'GET'), new RuntimeException('boom'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['message' => 'Server error.'], json_decode($response->getContent(), true));
    }

    public function test_web_routes_keep_their_own_renderer(): void
    {
        // The callback only owns api/v1; SSR pages keep their HTML error pages.
        $response = app(ExceptionHandler::class)
            ->render(Request::create('/', 'GET'), new NotFoundHttpException);

        $this->assertNull(json_decode((string) $response->getContent(), true));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function acceptHeaders(): array
    {
        return [
            'wildcard (curl default)' => ['*/*'],
            'json' => ['application/json'],
            'html' => ['text/html'],
        ];
    }

    #[DataProvider('acceptHeaders')]
    public function test_an_unauthenticated_api_call_always_gets_a_401_envelope(string $accept): void
    {
        // Laravel redirects unauthenticated guests to `route('login')` unless the
        // request `expectsJson()`. A client sending `Accept: */*` therefore got an
        // HTML redirect — or a 500 where that route was unavailable — rather than
        // the error contract. Every api/v1 route answers in JSON.
        $this->withHeaders(['Accept' => $accept])
            ->get('/api/v1/orders')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_guest_redirect_targets_the_storefront_login_only_off_the_api(): void
    {
        $property = new \ReflectionProperty(Authenticate::class, 'redirectToCallback');
        $property->setAccessible(true);
        $redirect = $property->getValue();

        $this->assertNotNull($redirect, 'bootstrap/app.php must configure redirectGuestsTo');

        $this->assertNull(
            $redirect(Request::create('/api/v1/orders', 'GET')),
            'an API guest must get a 401 envelope, never a redirect',
        );

        $this->assertSame(
            route('storefront.login'),
            $redirect(Request::create('/account', 'GET')),
            'web guests still go to the login page',
        );
    }
}
