<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class ConfigController extends AbstractActionController
{
    public function indexAction(){
        $this->layout()->setVariable('activeTab', 'config');  
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $configService = $this->getServiceLocator()->get('ConfigService');
            $result = $configService->getAllConfig($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    
    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'config');
        $configService = $this->getServiceLocator()->get('ConfigService');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $configService->updateConfig($params);
            return $this->redirect()->toRoute('config');
        }else{
            $config=$configService->getAllGlobalConfig();
            return new ViewModel(array(
                'config' => $config,
            ));
        }
    }

}
