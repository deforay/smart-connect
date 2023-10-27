<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class ApiSyncHistoryController extends AbstractActionController
{

    private $apiSyncHistoryService = null;

    public function __construct($apiSyncHistoryService)
    {
        $this->apiSyncHistoryService = $apiSyncHistoryService;
    }
    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'sync-history');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->apiSyncHistoryService->getAllDashTrackApiRequestsByGrid($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
        $requestType = $this->apiSyncHistoryService->getSyncHistoryType();
        return new ViewModel(array('requestType' => $requestType));
    }

    public function showParamsAction()
    {
        $id = base64_decode($this->params()->fromRoute('id'));
        $result = $this->apiSyncHistoryService->getSyncHistoryById($id);
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('result' => $result));
        return $viewModel;
    }
}
