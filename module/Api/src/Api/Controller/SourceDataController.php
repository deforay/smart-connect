<?php
namespace Api\Controller;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Laminas\Json\Json;

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
