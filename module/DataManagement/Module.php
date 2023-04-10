<?php

namespace DataManagement;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getControllerConfig()
    {
        return array(
            'factories' => array(
                'DataManagement\Controller\DuplicateDataController' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $sampleService = $diContainer->get('SampleService');
                        return new \DataManagement\Controller\DuplicateDataController($sampleService);
                    }
                },
                'DataManagement\Controller\ExportController' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $commonService = $diContainer->get('CommonService');
                        return new \DataManagement\Controller\ExportController($commonService);
                    }
                },
            ),
        );
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
}
