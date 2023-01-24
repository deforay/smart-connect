<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class SyncStatusController extends AbstractActionController
{

    private $commonService = null;

    public function __construct($commonService)
    {
        $this->commonService = $commonService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'sync-status');
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->commonService->getLabSyncStatus($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function syncStatusAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->commonService->getLabSyncStatus($params);
            $viewModel = new ViewModel(array(
                'result' => $result
            ));
            $viewModel->setTerminal(true);
            return $viewModel;
        }
    }
}
