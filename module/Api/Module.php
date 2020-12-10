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
                'Api\Controller\VlsmReferenceTables' => function ($sm) {
                    $commonService = $sm->getServiceLocator()->get('CommonService');
                    return new \Api\Controller\VlsmReferenceTablesController($commonService);
                },
                'Api\Controller\Vlsm' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('SampleService');
                    return new \Api\Controller\VlsmController($sampleService);
                },
                'Api\Controller\VlsmEid' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('EidSampleService');
                    return new \Api\Controller\VlsmEidController($sampleService);
                },
                'Api\Controller\VlsmCovid' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('Covid19FormService');
                    return new \Api\Controller\VlsmCovidController($sampleService);
                },
                'Api\Controller\WeblimsVL' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('SampleService');
                    return new \Api\Controller\WeblimsVLController($sampleService);
                },
                'Api\Controller\Facility' => function ($sm) {
                    $facilityService = $sm->getServiceLocator()->get('FacilityService');
                    return new \Api\Controller\FacilityController($facilityService);
                },
                'Api\Controller\User' => function ($sm) {
                    $userService = $sm->getServiceLocator()->get('UserService');
                    return new \Api\Controller\UserController($userService);
                },
                'Api\Controller\SourceData' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('SampleService');
                    return new \Api\Controller\SourceDataController($sampleService);
                },
                'Api\Controller\ImportViral' => function () {
                    return new \Api\Controller\ImportViralLoadController();
                },
                'Api\Controller\ReceiveVlData' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('SampleService');
                    return new \Api\Controller\ReceiveVlDataController($sampleService);
                },
                'Api\Controller\ReceiveEidData' => function ($sm) {
                    $sampleService = $sm->getServiceLocator()->get('EidSampleService');
                    return new \Api\Controller\ReceiveEidDataController($sampleService);
                },
            ),
        );
    }
}
