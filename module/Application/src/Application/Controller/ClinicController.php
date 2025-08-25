<?php

namespace Application\Controller;


use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;

class ClinicController extends AbstractActionController
{

    public \Application\Service\SampleService $sampleService;
    public \Application\Service\ConfigService $configService;

    public function __construct($sampleService, $configService)
    {
        $this->configService = $configService;
        $this->sampleService = $sampleService;
    }


    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');
        return $this->redirect()->toRoute('clinics');
    }

    public function dashboardAction()
    {
        $this->layout()->setVariable('activeTab', 'clinics');

        $sampleType = $this->sampleService->getSampleType();
        $clinicName = $this->sampleService->getAllClinicName();
        $testReasonName = $this->sampleService->getAllTestReasonName();
        $provinceName = $this->sampleService->getAllProvinceList();
        $districtName = $this->sampleService->getAllDistrictList();
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'clinicName' => $clinicName,
            'testReason' => $testReasonName,
            'provinceName' => $provinceName,
            'districtName' => $districtName
        ));
    }

    public function getOverallViralLoadStatusAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {

            $params = $request->getPost();


            $chartResult = $this->sampleService->getOverallViralLoadStatus($params);
            //var_dump($chartResult);die;
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('chartResult' => $chartResult))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getViralLoadStatusBasedOnGenderAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $chartResult = $this->sampleService->getViralLoadStatusBasedOnGender($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $chartResult))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleTestReasonAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->fetchSampleTestedReason($params);
            $testReasonName = $this->sampleService->getAllTestReasonName();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'testReason' => $testReasonName))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleTestResultAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicSampleTestedResults($params);
            $sampleType = $this->sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'sampleType' => $sampleType))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function testResultAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->sampleService->getAllTestResults($parameters);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function generateResultPdfAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $sampleResult = $this->sampleService->getSampleInfo($params);
            $config = $this->configService->getAllGlobalConfig();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('sampleResult' => $sampleResult, 'config' => $config))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function exportResultExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            if ($params['cFrom'] == 'high') {
                $file = $this->sampleService->generateHighVlSampleResultExcel($params);
            } else {
                $file = $this->sampleService->generateResultExcel($params);
            }
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function testResultViewAction()
    {
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');
        $params = [];
        $params['id'] = base64_decode($this->params()->fromRoute('id'));

        $sampleResult = $this->sampleService->getSampleInfo($params);
        return new ViewModel(array(
            'result' => $sampleResult
        ));
    }

    public function samplesTestReasonAction()
    {
        $this->layout()->setVariable('activeTab', 'clinics-dashboard');
        $params = [];
        $params['clinic'] = $this->params()->fromQuery('clinic');
        $params['testReasonCode'] = $this->params()->fromQuery('r');
        $params['dateRange'] = $this->params()->fromQuery('dRange');
        $params['testResult'] = $this->params()->fromQuery('rlt');
        $params['sampleType'] = base64_decode($this->params()->fromQuery('sTyp'));
        $params['adherence'] = $this->params()->fromQuery('adhr');
        $params['age'] = $this->params()->fromQuery('age');
        $params['gender'] = $this->params()->fromQuery('gd');
        $params['isPatientPregnant'] = $this->params()->fromQuery('p');
        $params['isPatientBreastfeeding'] = $this->params()->fromQuery('bf');

        $clinics = $this->sampleService->getAllClinicName();
        $testReasons = $this->sampleService->getAllTestReasonName();
        $sampleType = $this->sampleService->getSampleType();
        return new ViewModel(array(
            'clinics' => $clinics,
            'testReasons' => $testReasons,
            'sampleType' => $sampleType,
            'params' => $params
        ));
    }

    public function getSamplesTestReasonBasedOnAgeGroupAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getVLTestReasonBasedOnAgeGroup($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSamplesTestReasonBasedOnGenderAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getVLTestReasonBasedOnGender($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSamplesTestReasonBasedOnClinicsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getVLTestReasonBasedOnClinics($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultGenderAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultBasedGenderDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleTestResultAgeGroupAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'params' => $params))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupTwoToFiveAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'params' => $params))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupSixToFourteenAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'params' => $params))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupFifteenToFourtynineAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'params' => $params))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupGreaterFiftyAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'params' => $params))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupUnknownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'params' => $params))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getRequisitionFormsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicRequisitionFormsTested($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
}
