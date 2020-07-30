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
                'Covid19\Controller\Summary' => function ($sm) {
                    $commonService = $sm->getServiceLocator()->get('CommonService');
                    $summaryService = $sm->getServiceLocator()->get('Covid19FormService');
                    return new \Covid19\Controller\SummaryController($summaryService, $commonService);
                },
            )
        );
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(

                /* 'Covid19FormTable' => function ($sm) {
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    return new \Covid19\Model\Covid19FormTable($dbAdapter, $sm);
                }, */
                'Covid19FormTable' => function ($sm) {
                    $session = new Container('credo');
                    $mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    $covid19SampleTable = isset($session->covid19SampleTable) ? $session->covid19SampleTable :  null;

                    $tableObj = new \Covid19\Model\Covid19FormTable($dbAdapter, $sm, $mappedFacilities, $covid19SampleTable);
                    $table = PatternFactory::factory('object', [
                        'storage' => $sm->get('Cache\Persistent'),
                        'object' => $tableObj,
                        'object_key' => $covid19SampleTable // this makes sure we have different caches for both current and archive
                    ]);
                    return $table;
                },

                'Covid19FormTableWithoutCache' => function ($sm) {
                    $session = new Container('credo');
                    $mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
                    $eidSampleTable = isset($session->sampleTable) ? $session->sampleTable :  null;
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    return new \Covid19\Model\Covid19FormTable($dbAdapter, $sm, $mappedFacilities, $eidSampleTable);
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
