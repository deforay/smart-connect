<?php

namespace Application\Controller;


use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;
use Application\Service\SnapShotService;
use Laminas\Mvc\Controller\AbstractActionController;

class SnapshotController extends AbstractActionController
{

    private CommonService $commonService;
    private SnapShotService $snapshotService;

    public function __construct($commonService, $snapshotService)
    {
        $this->commonService = $commonService;
        $this->snapshotService = $snapshotService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'snap-data');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $result = $this->commonService->getAllDashApiReceiverStatsByGrid($parameters);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
        $clinicName = $this->commonService->getAllClinicName();
        $provinceName = $this->commonService->getAllProvinceList();
        $districtName = $this->commonService->getAllDistrictList();
        $lapName = $this->commonService->getAllLabName();
        return new ViewModel(array(
            'flag' => $this->params()->fromRoute('id') ?? '',
            'clinicName' => $clinicName,
            'provinceName' => $provinceName,
            'districtName' => $districtName,
            'lapName' => $lapName
        ));
    }
    public function getSnapshotDataAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->snapshotService->getSnapshotData($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'params' => $params))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getQuickStatsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->snapshotService->getSnapshotQuickStatsDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getFacilityPerformanceAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // Mode is passed so that the service can compute "others" aggregation accurately for the active view.
            $result = $this->snapshotService->getFacilityPerformanceData($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }
}
