<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class SnapshotController extends AbstractActionController
{

    private \Application\Service\CommonService $commonService;
    private \Application\Service\SampleService $sampleService;

    public function __construct($commonService, $sampleService)
    {
        $this->commonService = $commonService;
        $this->sampleService = $sampleService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'status');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $result = $this->commonService->getAllDashApiReceiverStatsByGrid($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
        $clinicName = $this->commonService->getAllClinicName();
        $provinceName = $this->commonService->getAllProvinceList();
        $districtName = $this->commonService->getAllDistrictList();
        return new ViewModel(array(
            'clinicName' => $clinicName,
            'provinceName' => $provinceName,
            'districtName' => $districtName
        ));
    }
    public function getSnapshotDataAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getSnapshotData($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
}
