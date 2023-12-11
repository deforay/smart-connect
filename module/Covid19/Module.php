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
                'Covid19\Controller\SummaryController' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $commonService = $diContainer->get('CommonService');
                        $summaryService = $diContainer->get('Covid19FormService');
                        return new \Covid19\Controller\SummaryController($summaryService, $commonService);
                    }
                },
                'Covid19\Controller\LabsController' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $facilityService = $diContainer->get('FacilityService');
                        $commonService = $diContainer->get('CommonService');
                        $summaryService = $diContainer->get('Covid19FormService');
                        return new \Covid19\Controller\LabsController($summaryService, $facilityService, $commonService);
                    }
                },
            )
        );
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(

                'Covid19FormTable' => new class
                {
                    public function __invoke($diContainer)
                    {
                        $session = new Container('credo');
                        $mappedFacilities = (property_exists($session, 'mappedFacilities') && $session->mappedFacilities !== null && !empty($session->mappedFacilities)) ? $session->mappedFacilities : array();
                        $dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
                        $covid19SampleTable = property_exists($session, 'covid19SampleTable') && $session->covid19SampleTable !== null ? $session->covid19SampleTable :  'dash_form_covid19';
                        $dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
                        $commonService = $diContainer->get('CommonService');
                        $tableObj = new \Covid19\Model\Covid19FormTable($dbAdapter, $diContainer, $mappedFacilities, $covid19SampleTable, $commonService);


                        $storage = $diContainer->get('Cache\Persistent');
                        return new ObjectCache(
                            $storage,
                            new PatternOptions([
                                'object' => $tableObj,
                                'object_key' => $covid19SampleTable // this makes sure we have different caches for both current and archive
                            ])
                        );
                    }
                },

                'Covid19FormTableWithoutCache'  => new class
                {
                    public function __invoke($diContainer)
                    {
                        $session = new Container('credo');
                        $mappedFacilities = (property_exists($session, 'mappedFacilities') && $session->mappedFacilities !== null && !empty($session->mappedFacilities)) ? $session->mappedFacilities : array();
                        $dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
                        $covid19SampleTable = property_exists($session, 'covid19SampleTable') && $session->covid19SampleTable !== null ? $session->covid19SampleTable :  'dash_form_covid19';
                        $dbAdapter = $diContainer->get('Laminas\Db\Adapter\Adapter');
                        $commonService = $diContainer->get('CommonService');
                        return new \Covid19\Model\Covid19FormTable($dbAdapter, $diContainer, $mappedFacilities, $covid19SampleTable, $commonService);
                    }
                },

                'Covid19FormService'  => new class
                {
                    public function __invoke($diContainer)
                    {
                        $commonService = $diContainer->get('CommonService');
                        return new \Covid19\Service\Covid19FormService($diContainer, $commonService);
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
