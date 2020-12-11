<?php

namespace Application\View\Helper;

use Laminas\ServiceManager\ServiceLocatorAwareInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Helper\AbstractHelper;

class GetActiveModules extends AbstractHelper implements ServiceLocatorAwareInterface
{

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    public function __invoke()
    {
        $config = $this->getServiceLocator()->getServiceLocator()->get('Config');
        $dashModules =  array(
            'vl' => $config['defaults']['vlModule'],
            'eid' => $config['defaults']['eidModule'],
            'covid19' => $config['defaults']['covid19Module'],
            'poc' => $config['defaults']['pocDashboard']
        );
        return $dashModules;
    }
}
