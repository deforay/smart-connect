<?php

namespace Covid19;

use Laminas\Session\Container;
use Laminas\Cache\Pattern\ObjectCache;
use Laminas\Cache\Pattern\PatternOptions;


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
                'Covid19\Controller\Labs' => function ($sm) {
                    $facilityService = $sm->getServiceLocator()->get('FacilityService');
                    $commonService = $sm->getServiceLocator()->get('CommonService');
                    $summaryService = $sm->getServiceLocator()->get('Covid19FormService');
                    return new \Covid19\Controller\LabsController($summaryService, $facilityService, $commonService);
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
                    $covid19SampleTable = isset($session->covid19SampleTable) ? $session->covid19SampleTable :  'dash_form_covid19';

                    $mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    $commonService = $sm->getServiceLocator()->get('CommonService');
                    $tableObj = new \Covid19\Model\Covid19FormTable($dbAdapter, $sm, $mappedFacilities, $covid19SampleTable, $commonService);


                    $storage = $sm->get('Cache\Persistent');
                    return new ObjectCache(
                        $storage,
                        new PatternOptions([
                            'object' => $tableObj,
                            'object_key' => $covid19SampleTable // this makes sure we have different caches for both current and archive
                        ])
                    );
                },

                'Covid19FormTableWithoutCache' => function ($sm) {
                    $session = new Container('credo');
                    $mappedFacilities = (isset($session->mappedFacilities) && count($session->mappedFacilities) > 0) ? $session->mappedFacilities : array();
                    $covid19SampleTable = isset($session->covid19SampleTable) ? $session->covid19SampleTable :    'dash_form_covid19';
                    $dbAdapter = $sm->get('Laminas\Db\Adapter\Adapter');
                    return new \Covid19\Model\Covid19FormTable($dbAdapter, $sm, $mappedFacilities, $covid19SampleTable);
                },

                'Covid19FormService' => function ($sm) {
                    $commonService = $sm->getServiceLocator()->get('CommonService');
                    return new \Covid19\Service\Covid19FormService($sm, $commonService);
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
