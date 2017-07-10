<?php
namespace Application\View\Helper;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Helper\AbstractHelper;

class GetConfigData extends AbstractHelper implements ServiceLocatorAwareInterface{
    
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator){
        $this->serviceLocator = $serviceLocator;  
        return $this;  
    }
    
    public function getServiceLocator(){
        return $this->serviceLocator;  
    }
    
    public function __invoke(){
        $sm = $this->getServiceLocator()->getServiceLocator();
        $globalDb = $sm->get('GlobalTable');
        return $globalDb->fetchAllGlobalConfig();
    }
}