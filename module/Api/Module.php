<?php

namespace Api;

class Module
{
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
                'Api\Controller\VlsmReferenceTables' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $commonService = $diContainer->get('CommonService');
                        return new \Api\Controller\VlsmReferenceTablesController($commonService);
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
                'Api\Controller\ReceiveVlData' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('SampleService');
                        return new \Api\Controller\ReceiveVlDataController($sampleService);
                    }
                },
                'Api\Controller\ReceiveEidData' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('EidSampleService');
                        return new \Api\Controller\ReceiveEidDataController($sampleService);
                    }
                },
                'Api\Controller\ReceiveCovid19Data' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('Covid19FormService');
                        return new \Api\Controller\ReceiveCovid19DataController($sampleService);
                    }
                },
            ),
        );
    }
}
