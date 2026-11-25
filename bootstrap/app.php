<?php

use App\Http\Controllers\CaddyAskController;
use App\Http\Middleware\AdminContactPublicSiteLoginMiddleware;
use App\Http\Middleware\AllowIframeMiddleware;
use App\Http\Middleware\ApiClientMiddleware;
use App\Http\Middleware\EnsureUserIsOwner;
use App\Http\Middleware\GrabClientIdFromSessionMiddleware;
use App\Http\Middleware\HasAccessToAppMiddleware;
use App\Http\Middleware\IdentifyClientMiddleware;
use App\Http\Middleware\IdentifyWidgetClientMiddleware;
use App\Http\Middleware\LogoutDeactivatedUser;
use App\Http\Middleware\RedirectLoopbackHost;
use App\Http\Middleware\RedirectToSetup;
use App\Http\Middleware\RegistrationEnabledMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Support\NotFoundMessage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            // withRouting() ignores "health:" when "using:" is set.
            Route::get('/up', HealthCheck::class);

            // Caddy asks this route before it gets an on-demand TLS
            // certificate. It answers on any host, like /up.
            Route::get('/caddy/ask', CaddyAskController::class)->name('caddy.ask');

            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api.php'));

            Route::middleware('api')
                ->prefix('api/public')
                ->group(base_path('routes/public-api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('iframe')
                ->prefix('iframe')
                ->group(base_path('routes/iframe.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust the forwarded headers only from the proxies in
        // `trustedproxy.proxies`. The default is the Cloudflare ranges.
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB);

        // Authenticated guests (the `guest` middleware) land on the dashboard.
        $middleware->redirectUsersTo('/dashboard');

        // Send 127.0.0.1 and [::1] to the APP_URL host, where the admin
        // routes are.
        $middleware->prepend(RedirectLoopbackHost::class);

        // Never trim sensitive credential fields.
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        // End the session of a deactivated user. Laravel sorts route middleware
        // by priority and moves `auth` before each middleware that is not in
        // the priority list. Without the priority entry, `auth` sends a
        // deactivated user to the login form with no message.
        // SecurityHeaders stops a page of another site from framing the
        // dashboard or the public site. Its priority entry puts it outside
        // `auth`, so that the redirect to the login form also has the headers.
        // The iframe group does not use `web`.
        $middleware->web(append: [LogoutDeactivatedUser::class, SecurityHeaders::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, LogoutDeactivatedUser::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SecurityHeaders::class);

        // Preserve the Laravel 10 API stack: Sanctum stateful + rate limiting.
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
        $middleware->throttleApi();

        // Embeddable widget routes resolve the tenant and allow framing.
        $middleware->group('iframe', [
            IdentifyClientMiddleware::class,
            AllowIframeMiddleware::class,
        ]);

        $middleware->alias([
            'identifyClient' => IdentifyClientMiddleware::class,
            'clientId' => GrabClientIdFromSessionMiddleware::class,
            'adminContactLogin' => AdminContactPublicSiteLoginMiddleware::class,
            'hasAccess' => HasAccessToAppMiddleware::class,
            'allowIframe' => AllowIframeMiddleware::class,
            'api_client' => ApiClientMiddleware::class,
            'widget_client' => IdentifyWidgetClientMiddleware::class,
            'registrationEnabled' => RegistrationEnabledMiddleware::class,
            'setupRedirect' => RedirectToSetup::class,
            'owner' => EnsureUserIsOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API clients often send no Accept header. Answer them in JSON anyway,
        // never with a redirect to the login form or an HTML error page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*', 'mcp') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*', 'mcp')) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated. Send a valid API token in the Authorization header: "Bearer <token>".',
            ], 401);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            $previous = $exception->getPrevious();

            if (! $request->is('api/*') || ! $previous instanceof ModelNotFoundException) {
                return null;
            }

            return response()->json(['message' => NotFoundMessage::for($previous)], 404);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! in_array('contact', $exception->guards(), true) || $request->expectsJson()) {
                return null;
            }

            return redirect('/posts')->with('info', 'To vote, comment or post, open this board from your account in the product.');
        });
    })->create();
