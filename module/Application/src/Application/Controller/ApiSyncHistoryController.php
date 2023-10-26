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
        $this->layout()->setVariable('activeTab', 'facility');
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
    
}
