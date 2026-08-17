<?php

declare(strict_types=1);

/**
 * /api/v2 — the Slim seam.
 *
 * public/index.php hands every /api/v2 request here and exits, so nothing below
 * ever touches the Laminas MVC application. The existing service layer is still
 * reachable, lazily, through LaminasBridge.
 *
 * Returns a callable so the file can be require'd without executing: the base
 * path (non-empty only where the app is deployed in a sub-directory) comes from
 * the caller.
 */

use App\HttpHandlers\V2\AuthLoginHandler;
use App\HttpHandlers\V2\EnrollHandler;
use App\HttpHandlers\V2\FacilitiesHandler;
use App\HttpHandlers\V2\HealthHandler;
use App\HttpHandlers\V2\ImportVlHandler;
use App\HttpHandlers\V2\MetadataHandler;
use App\HttpHandlers\V2\ReceiveCovid19Handler;
use App\HttpHandlers\V2\ReceiveEidHandler;
use App\HttpHandlers\V2\ReceiveVlHandler;
use App\HttpHandlers\V2\SourceDataHandler;
use App\HttpHandlers\V2\WeblimsVlHandler;
use App\Middlewares\ApiErrorMiddleware;
use App\Middlewares\BearerAuthMiddleware;
use App\Middlewares\RequestTrackingMiddleware;
use App\Services\EnrollmentService;
use App\Services\LaminasBridge;
use DI\Container;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteCollectorProxy;

use function DI\autowire;
use function DI\get;

return static function (string $basePath = ''): void {
    $appRoot = dirname(__DIR__, 2);

    $container = new Container([
        LaminasBridge::class => static fn(): LaminasBridge => new LaminasBridge($appRoot),

        ResponseFactoryInterface::class => static fn(): ResponseFactoryInterface => new ResponseFactory(),

        EnrollmentService::class => static function (ContainerInterface $c): EnrollmentService {
            $bridge = $c->get(LaminasBridge::class);
            $key = $bridge->configValue('api.enrollment_key');

            return new EnrollmentService($bridge, is_string($key) && $key !== '' ? $key : null);
        },

        ApiErrorMiddleware::class => static fn(ContainerInterface $c): ApiErrorMiddleware => new ApiErrorMiddleware(
            $c->get(ResponseFactoryInterface::class),
            (bool) $c->get(LaminasBridge::class)->configValue('api.debug', false)
        ),

        // Two configurations of the same middleware, one per audience. Routes
        // reference them by these names.
        'auth.client' => static fn(ContainerInterface $c): BearerAuthMiddleware => new BearerAuthMiddleware(
            $c->get(ResponseFactoryInterface::class),
            $c->get(EnrollmentService::class),
            $c->get(LaminasBridge::class),
            ['client']
        ),
        'auth.user' => static fn(ContainerInterface $c): BearerAuthMiddleware => new BearerAuthMiddleware(
            $c->get(ResponseFactoryInterface::class),
            $c->get(EnrollmentService::class),
            $c->get(LaminasBridge::class),
            ['user']
        ),

        RequestTrackingMiddleware::class => autowire(),

        HealthHandler::class => autowire(),
        EnrollHandler::class => autowire(),
        AuthLoginHandler::class => autowire(),
        ReceiveVlHandler::class => autowire(),
        ReceiveEidHandler::class => autowire(),
        ReceiveCovid19Handler::class => autowire(),
        WeblimsVlHandler::class => autowire(),
        ImportVlHandler::class => autowire(),
        SourceDataHandler::class => autowire(),
        MetadataHandler::class => autowire(),
        FacilitiesHandler::class => autowire(),

        ResponseFactory::class => get(ResponseFactoryInterface::class),
    ]);

    AppFactory::setContainer($container);
    $app = AppFactory::create();

    if ($basePath !== '') {
        $app->setBasePath($basePath);
    }

    $tracking = RequestTrackingMiddleware::class;

    // Not a static closure: Slim binds route-group callables to the container.
    $app->group('/api/v2', function (RouteCollectorProxy $group) use ($tracking): void {
        // Open: the capability probe LIS clients use to decide between v2 and
        // the legacy paths, so it cannot require a token.
        $group->get('/health', HealthHandler::class);

        // Open by design, guarded by the enrollment key in its own body.
        $group->post('/enroll', EnrollHandler::class);

        // Credentials in the body; this is where an api user gets its token.
        $group->post('/auth/login', AuthLoginHandler::class);

        // LIS ingestion — an enrolled lab client pushing data up.
        $group->post('/vl', ReceiveVlHandler::class)->add($tracking)->add('auth.client');
        $group->post('/eid', ReceiveEidHandler::class)->add($tracking)->add('auth.client');
        $group->post('/covid19', ReceiveCovid19Handler::class)->add($tracking)->add('auth.client');
        $group->post('/vl/weblims', WeblimsVlHandler::class)->add($tracking)->add('auth.client');
        $group->post('/vl/import', ImportVlHandler::class)->add($tracking)->add('auth.client');
        $group->post('/metadata', MetadataHandler::class)->add($tracking)->add('auth.client');

        // Read-out endpoints — a human-issued api user (dash_users, role 6).
        $group->post('/vl/source-data', SourceDataHandler::class)->add($tracking)->add('auth.user');
        $group->post('/facilities', FacilitiesHandler::class)->add($tracking)->add('auth.user');
    });

    // Added last, so it runs first and nothing escapes as HTML or a stack trace.
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(ApiErrorMiddleware::class);

    $app->run();
};
