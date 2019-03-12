<?php
namespace Api\Controller;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Zend\Json\Json;

class SourceDataController extends AbstractRestfulController
{
    public function create(){
        $params=$this->getRequest()->getPost();
        if(isset($params['token']) && $params['token']!=''){
            $sampleService = $this->getServiceLocator()->get('SampleService');
            $response = $sampleService->getSourceData($params);
        }else{
            $response['status'] = '422';
            $response['result'] = 'Invalid or Missing Query Params';
        }
        return new JsonModel($response);
    }
}
?>
