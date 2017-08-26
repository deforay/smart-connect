<?php
namespace Application\View\Helper;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Helper\AbstractHelper;

class GetLocaleData extends AbstractHelper implements ServiceLocatorAwareInterface{
    
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator){
        $this->serviceLocator = $serviceLocator;  
        return $this;  
    }
    
    public function getServiceLocator(){
        return $this->serviceLocator;  
    }
    
    public function __invoke($column,$localeId){
        $sm = $this->getServiceLocator()->getServiceLocator();
        $globalDb = $sm->get('GlobalTable');
        return $globalDb->fetchLocaleDetailsById($column,$localeId);
    }
}