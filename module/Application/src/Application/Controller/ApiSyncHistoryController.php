<?php

namespace Application\Controller;


use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;

class ApiSyncHistoryController extends AbstractAppController
{

    private $apiSyncHistoryService = null;

    public function __construct($apiSyncHistoryService, \Application\Model\Acl $acl)
    {
        $this->setAcl($acl);
        $this->apiSyncHistoryService = $apiSyncHistoryService;
    }
    public function indexAction()
    {
        if (!$this->hasPrivilege('index')) {
            return $this->forbidden();
        }
        $this->layout()->setVariable('activeTab', 'sync-history');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->apiSyncHistoryService->getAllDashTrackApiRequestsByGrid($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
        $requestType = $this->apiSyncHistoryService->getSyncHistoryType();
        return new ViewModel(array('requestType' => $requestType));
    }

    public function showParamsAction()
    {
        if (!$this->hasPrivilege('show-params')) {
            return $this->forbidden();
        }
        $id = base64_decode($this->params()->fromRoute('id'));
        $result = $this->apiSyncHistoryService->getSyncHistoryById($id);
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('result' => $result));
        return $viewModel;
    }
}
