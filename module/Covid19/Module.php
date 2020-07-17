<?php

namespace Covid19;

use Laminas\Session\Container;
use Laminas\Cache\PatternFactory;


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
                'Covid19\Controller\Index' => function ($sm) {
                    $service = $sm->getServiceLocator()->get('Covid19FormService');
                    return new \Covid19\Controller\IndexController($service);
                },
            )
        );
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(

                'Covid19FormTable' => function ($sm) {
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    return new \Covid19\Model\Covid19FormTable($dbAdapter, $sm);
                },

                'Covid19FormService' => function ($sm) {
                    return new \Covid19\Service\Covid19FormService($sm);
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
