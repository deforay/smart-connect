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
                'Api\Controller\Vlsm' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('SampleService');
                    return new \Api\Controller\VlsmController($sampleService);
                }
            ),
        );
    }
}
