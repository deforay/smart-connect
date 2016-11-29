<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class SourceController extends AbstractActionController {

    public function indexAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sourceService = $this->getServiceLocator()->get('SourceService');
            $result = $sourceService->getAllSource($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'source');
    }

    public function addAction() {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $sourceService = $this->getServiceLocator()->get('SourceService');
            $sourceService->addSource($params);
            return $this->redirect()->toRoute("source");
        }
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'source');
        return new ViewModel();
    }

    public function editAction() {
        $request = $this->getRequest();
        $sourceService = $this->getServiceLocator()->get('SourceService');
        if ($request->isPost()) {
            $params = $request->getPost();
            $sourceService->updateSource($params);
            return $this->redirect()->toRoute("source");
        } else {
            $id = base64_decode($this->params()->fromRoute('id'));
            $sourceResult = $sourceService->getSource($id);
            if ($sourceResult) {
                return new ViewModel(array(
                    'result' => $sourceResult,
                ));
            } else {
                return $this->redirect()->toRoute("source");
            }
        }
        $this->layout()->setVariable('activeTab', 'admin');
        $this->layout()->setVariable('activeMenu', 'source');
    }

}

