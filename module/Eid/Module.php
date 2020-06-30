<?php

namespace Eid;

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
                'Eid\Controller\Summary' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('EidSampleService');
                    $summaryService = $sm->getServiceLocator()->get('EidSummaryService');
                    return new \Eid\Controller\SummaryController($summaryService, $sampleService);
                },
            ),
        );
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(

                'EidSampleTable' => function ($sm) {
                    $session = new Container('credo');
                    $mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    $eidSampleTable = isset($session->eidSampleTable) ? $session->eidSampleTable :  null;
                    
                    $tableObj = new \Eid\Model\EidSampleTable($dbAdapter, $sm, $mappedFacilities, $eidSampleTable);
                    $table = PatternFactory::factory('object', [
                        'storage' => $sm->get('Cache\Persistent'),
                        'object' => $tableObj,
                        'object_key' => $eidSampleTable // this makes sure we have different caches for both current and archive
                    ]);
                    return $table;
                },
                'EidSampleTableWithoutCache' => function ($sm) {
                    $session = new Container('credo');
                    $mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
                    $eidSampleTable = isset($session->sampleTable) ? $session->sampleTable :  null;
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    return new \Eid\Model\EidSampleTable($dbAdapter, $sm, $mappedFacilities, $eidSampleTable);
                },


                'EidSampleService' => function ($sm) {
                    return new \Eid\Service\EidSampleService($sm);
                },
                'EidSummaryService' => function ($sm) {
                    return new \Eid\Service\EidSummaryService($sm);
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
