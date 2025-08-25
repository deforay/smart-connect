<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;


class SyncStatusController extends AbstractActionController
{

    private \Application\Service\CommonService $commonService;

    public function __construct($commonService)
    {
        $this->commonService = $commonService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'sync-status');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->commonService->getLabSyncStatus($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function syncStatusAction()
    {
        /** @var \Laminas\Http\Request $request */
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

    public function exportSyncStatusExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $file = $this->commonService->generateSyncStatusExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }
}
