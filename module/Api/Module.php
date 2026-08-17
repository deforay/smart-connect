<?php

namespace Api;

use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\MvcEvent;

class Module
{
    /**
     * Deprecation window for the legacy /api/* endpoints.
     *
     * Hundreds of LIS installations call these, and they update on their own
     * schedule, so the endpoints keep working unchanged until the deployment
     * says otherwise. Until the configured api.legacy_sunset date they merely
     * advertise their own retirement through RFC 8594 headers; after it they
     * answer 410 and point at /api/v2. A null sunset means headers only — the
     * cutoff never fires on its own.
     *
     * `bin/console api-usage` is how you decide the date is safe.
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getApplication();

        $application->getEventManager()->attach(
            MvcEvent::EVENT_ROUTE,
            function (MvcEvent $e) {
                $routeMatch = $e->getRouteMatch();
                if ($routeMatch === null) {
                    return null;
                }

                $controller = (string) $routeMatch->getParam('controller', '');
                if (!str_starts_with($controller, 'Api\Controller\\')) {
                    return null;
                }

                $config = $e->getApplication()->getServiceManager()->get('config');
                $sunset = $config['api']['legacy_sunset'] ?? null;

                $response = $e->getResponse();
                if (!$response instanceof HttpResponse) {
                    return null;
                }

                $headers = $response->getHeaders();
                $headers->addHeaderLine('Deprecation', 'true');
                $headers->addHeaderLine('Link', '</api/v2/health>; rel="successor-version"');

                if (empty($sunset)) {
                    return null;
                }

                // Interpreted as UTC, not server-local: a bare '2027-06-30'
                // parsed locally lands on the 29th once gmdate() converts it
                // back, and the cutoff would fire a day early west of Greenwich.
                try {
                    $sunsetDate = new \DateTimeImmutable((string) $sunset, new \DateTimeZone('UTC'));
                } catch (\Exception) {
                    return null;
                }

                $sunsetTimestamp = $sunsetDate->getTimestamp();

                $headers->addHeaderLine('Sunset', gmdate('D, d M Y H:i:s', $sunsetTimestamp) . ' GMT');

                if (time() < $sunsetTimestamp) {
                    return null;
                }

                // Past the cutoff: answer 410 and stop before the controller.
                $path = '';
                $request = $e->getRequest();
                if ($request instanceof HttpRequest) {
                    $path = $request->getUri()->getPath() ?? '';
                }

                $response->setStatusCode(410);
                $headers->addHeaderLine('Content-Type', 'application/json');
                $response->setContent(json_encode([
                    'status' => 'error',
                    'message' => sprintf(
                        'The %s API was retired on %s. Use the /api/v2/* endpoints instead.',
                        $path === '' ? 'legacy' : $path,
                        gmdate('Y-m-d', $sunsetTimestamp)
                    ),
                    'sunset' => gmdate('Y-m-d', $sunsetTimestamp),
                    'data' => null,
                ], JSON_UNESCAPED_SLASHES));

                $e->stopPropagation(true);

                return $response;
            },
            // After routing has resolved the controller, before dispatch.
            -100
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Laminas\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
    public function getControllerConfig()
    {
        return array(
            'factories' => array(
                'Api\Controller\Health' => new class
                {
                    public function __invoke($diContainer)
                    {
                        return new \Api\Controller\HealthController();
                    }
                },
                'Api\Controller\VlsmMetadata' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $commonService = $diContainer->get('CommonService');
                        return new \Api\Controller\VlsmMetadataController($commonService);
                    }
                },
                'Api\Controller\Vlsm' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('SampleService');
                        return new \Api\Controller\VlsmController($sampleService);
                    }
                },
                'Api\Controller\VlsmEid' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('EidSampleService');
                        return new \Api\Controller\VlsmEidController($sampleService);
                    }
                },
                'Api\Controller\VlsmCovid19' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('Covid19FormService');
                        return new \Api\Controller\VlsmCovid19Controller($sampleService);
                    }
                },
                'Api\Controller\WeblimsVL' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('SampleService');
                        return new \Api\Controller\WeblimsVLController($sampleService);
                    }
                },
                'Api\Controller\Facility' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $facilityService = $diContainer->get('FacilityService');
                        return new \Api\Controller\FacilityController($facilityService);
                    }
                },
                'Api\Controller\User' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $userService = $diContainer->get('UserService');
                        return new \Api\Controller\UserController($userService);
                    }
                },
                'Api\Controller\SourceData' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('SampleService');
                        return new \Api\Controller\SourceDataController($sampleService);
                    }
                },
                'Api\Controller\ImportViral' => new class
                {
                    public function __invoke($diContainer)
                    {
                        return new \Api\Controller\ImportViralLoadController();
                    }
                },
            ),
        );
    }
}
