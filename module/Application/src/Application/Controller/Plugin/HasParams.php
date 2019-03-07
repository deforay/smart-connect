<?php
namespace Application\Controller\Plugin;
 
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
 
class HasParams extends AbstractPlugin{
    public function checkParams($allparams,$paramsToCheck){
        foreach($paramsToCheck as $param){
            if(!isset($allparams[$param]) || $allparams[$param] == ""){
                return false;
            }
        }
		return true;
    }
}