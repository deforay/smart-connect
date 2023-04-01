<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class LaboratoryController extends AbstractActionController
{


    private $sampleService = null;
    private \Application\Service\CommonService $commonService;

    public function __construct($sampleService, $commonService)
    {
        $this->commonService = $commonService;
        $this->sampleService = $sampleService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        return $this->redirect()->toRoute('summary');
    }

    public function dashboardAction()
    {
        
        $this->layout()->setVariable('activeTab', 'laboratory');
        
        $sampleType = $this->sampleService->getSampleType();
        $labName = $this->sampleService->getAllLabName();
        $provinceName = $this->sampleService->getAllProvinceList();
        $districtName = $this->sampleService->getAllDistrictList();
        $testReasonName = $this->sampleService->getAllTestReasonName();
        $clinicName = $this->sampleService->getAllClinicName();
        
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'labName' => $labName,
            'provinceName' => $provinceName,
            'districtName' => $districtName,
            'testReason' => $testReasonName,
            'clinicName' => $clinicName,
        ));
    }


    public function samplesAccessionAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        return new ViewModel();
    }

    public function samplesWaitingAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        return new ViewModel();
    }


    public function samplesRejectedAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        return new ViewModel();
    }

    public function drillDownAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();


        $labFilter = $this->params()->fromQuery('lab');
        $params['labs'] = explode(',', $labFilter);

        
        
        $hubName = $this->sampleService->getAllHubName();
        $sampleType = $this->sampleService->getSampleType();
        $currentRegimen = $this->sampleService->getAllCurrentRegimen();
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        //print_r($facilityInfo);die;
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'currentRegimen' => $currentRegimen,
            'facilityInfo' => $facilityInfo,
            'searchMonth' => $this->params()->fromQuery('month'),
            'searchGender' => $this->params()->fromQuery('gender'),
            'searchRange' => $this->params()->fromQuery('range'),
            'fromMonth' => $this->params()->fromQuery('fromMonth'),
            'toMonth' => $this->params()->fromQuery('toMonth'),
            'labFilter' => $this->params()->fromQuery('lab'),
            'age' => $this->params()->fromQuery('age'),
            'femaleFilter' => $this->params()->fromQuery('femaleFilter'),
            'lt' => $this->params()->fromQuery('lt'),
            'result' => $this->params()->fromQuery('result')
        ));
    }

    public function requisitionFormsIncompleteAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $month = "";
        $labFilter = "";
        if ($this->params()->fromQuery('month')) {
            $month = $this->params()->fromQuery('month');
        }
        if ($this->params()->fromQuery('lab')) {
            $labFilter = $this->params()->fromQuery('lab');
            $params['labs'] = $labFilter;
        }
        
        
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'searchMonth' => $month,
            'labFilter' => $labFilter,
            'facilityInfo' => $facilityInfo
        ));
    }

    public function getIncompleteSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getIncompleteSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getIncompleteBarSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getIncompleteBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleResultAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleResultDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSamplesTestedAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSamplesTested($params);
            $sampleType = $this->sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'sampleType' => $sampleType))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSamplesTestedPerLabAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getSamplesTestedPerLab($params);
            $sampleType = $this->sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'sampleType' => $sampleType))
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
            
            $params['gender'] = 'yes';
            $result = $this->sampleService->getSampleTestedResultGenderDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getLabTurnAroundTimeAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $sampleType = $this->sampleService->getSampleType();
            $result = $this->sampleService->getLabTurnAroundTime($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'sampleType' => $sampleType))
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
            
            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleTestResultPregnantAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleTestedResultPregnantPatientDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleTestResultBreastfeedingAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleTestedResultBreastfeedingPatientDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
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
            
            $result = $this->sampleService->getRequisitionFormsTested($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleVolumeAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleVolume($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getFemalePatientResultAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getFemalePatientResult($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getLineOfTreatmentAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getLineOfTreatment($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getLabFacilitiesAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $sampleType = $this->sampleService->getSampleType();
            $result = $this->sampleService->getFacilites($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'height' => $params['height']))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getVlOutComesAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getVlOutComes($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getBarSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getLabFilterSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            
            $result = $this->sampleService->getLabFilterSampleDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getFilterSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            
            $result = $this->sampleService->getFilterSampleDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function samplesTestedLabAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $gender = "";
        $month = "";
        $range = "";
        $age = "";
        $fromMonth = "";
        $toMonth = "";
        $labFilter = "";
        $params['fromSrc'] = 'tested-lab';
        if ($this->params()->fromQuery('gender')) {
            $gender = $this->params()->fromQuery('gender');
        }
        if ($this->params()->fromQuery('month')) {
            $month = $this->params()->fromQuery('month');
        }
        if ($this->params()->fromQuery('range')) {
            $range = $this->params()->fromQuery('range');
        }
        if ($this->params()->fromQuery('age')) {
            $age = $this->params()->fromQuery('age');
        }
        if ($this->params()->fromQuery('fromMonth')) {
            $fromMonth = $this->params()->fromQuery('fromMonth');
        }
        if ($this->params()->fromQuery('toMonth')) {
            $toMonth = $this->params()->fromQuery('toMonth');
        }
        if ($this->params()->fromQuery('lab')) {
            $labFilter = $this->params()->fromQuery('lab');
            $params['labNames'] = explode(',', $labFilter);
        }

        
        
        $hubName = $this->sampleService->getAllHubName();
        $sampleType = $this->sampleService->getSampleType();
        $currentRegimen = $this->sampleService->getAllCurrentRegimen();
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'currentRegimen' => $currentRegimen,
            'searchMonth' => $month,
            'searchGender' => $gender,
            'searchRange' => $range,
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'labFilter' => $labFilter,
            'age' => $age,
            'facilityInfo' => $facilityInfo
        ));
    }

    public function getLabSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getLabSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getLabBarSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getLabBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function sampleVolumeAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $fromMonth = "";
        $toMonth = "";
        $labFilter = "";
        $sampleStatus = "";
        $params['fromSrc'] = 'sample-volume';
        if ($this->params()->fromQuery('fromMonth')) {
            $fromMonth = $this->params()->fromQuery('fromMonth');
        }
        if ($this->params()->fromQuery('toMonth')) {
            $toMonth = $this->params()->fromQuery('toMonth');
        }
        if ($this->params()->fromQuery('lab')) {
            $labFilter = $this->params()->fromQuery('lab');
            $params['labCodes'] = explode(',', $labFilter);
        }
        if ($this->params()->fromQuery('result')) {
            $sampleStatus = $this->params()->fromQuery('result');
        }
        
        
        $hubName = $this->sampleService->getAllHubName();
        $sampleType = $this->sampleService->getSampleType();
        $currentRegimen = $this->sampleService->getAllCurrentRegimen();
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'currentRegimen' => $currentRegimen,
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'labFilter' => $labFilter,
            'sampleStatus' => $sampleStatus,
            'facilityInfo' => $facilityInfo
        ));
    }

    public function exportSampleResultExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $file = $this->sampleService->generateSampleResultExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function exportLabTestedSampleExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $file = $this->sampleService->generateLabTestedSampleExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function samplesTestedTurnAroundTimeAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $gender = "";
        $month = "";
        $range = "";
        $age = "";
        $fromMonth = "";
        $toMonth = "";
        $labFilter = "";
        if ($this->params()->fromQuery('gender')) {
            $gender = $this->params()->fromQuery('gender');
        }
        if ($this->params()->fromQuery('month')) {
            $month = $this->params()->fromQuery('month');
        }
        if ($this->params()->fromQuery('range')) {
            $range = $this->params()->fromQuery('range');
        }
        if ($this->params()->fromQuery('age')) {
            $age = $this->params()->fromQuery('age');
        }
        if ($this->params()->fromQuery('fromMonth')) {
            $fromMonth = $this->params()->fromQuery('fromMonth');
        }
        if ($this->params()->fromQuery('toMonth')) {
            $toMonth = $this->params()->fromQuery('toMonth');
        }
        if ($this->params()->fromQuery('lab')) {
            $labFilter = $this->params()->fromQuery('lab');
            $params['labs'] = explode(',', $labFilter);
        }

        
        
        $hubName = $this->sampleService->getAllHubName();
        $sampleType = $this->sampleService->getSampleType();
        $currentRegimen = $this->sampleService->getAllCurrentRegimen();
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'currentRegimen' => $currentRegimen,
            'searchMonth' => $month,
            'searchGender' => $gender,
            'searchRange' => $range,
            'fromMonth' => $fromMonth,
            'toMonth' => $toMonth,
            'labFilter' => $labFilter,
            'age' => $age,
            'facilityInfo' => $facilityInfo
        ));
    }

    public function getBarSampleTatAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getBarSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getPieSampleTatAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getFilterSampleTatAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            
            $result = $this->sampleService->getFilterSampleTatDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function exportSampleTestedResultTatExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $file = $this->sampleService->generateLabTestedSampleTatExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getFacilitiesGeolocationAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $fromDate = "";
        $toDate = "";
        $labFilter = "";
        if ($this->params()->fromQuery('fromDate')) {
            $fromDate = $this->params()->fromQuery('fromDate');
        }
        if ($this->params()->fromQuery('toDate')) {
            $toDate = $this->params()->fromQuery('toDate');
        }
        if ($this->params()->fromQuery('lab')) {
            $labFilter = $this->params()->fromQuery('lab');
        }
        
        $labName = $this->sampleService->getAllLabName();
        if (trim($fromDate) != '' && trim($toDate) != '') {
            return new ViewModel(array('fromMonth' => date('M-Y', strtotime($fromDate)), 'toMonth' => date('M-Y', strtotime($toDate)), 'labFilter' => $labFilter, 'labName' => $labName));
        } else {
            return $this->redirect()->toRoute("laboratory");
        }
    }

    public function getLocationInfoAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->commonService->getSampleTestedLocationInfo($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function drillDownResultAwaitedAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = array();
        $frmSource = "";
        $labFilter = "";
        if ($this->params()->fromQuery('src')) {
            $frmSource = $this->params()->fromQuery('src');
        }
        if ($this->params()->fromQuery('lab')) {
            $labFilter = $this->params()->fromQuery('lab');
            $params['labs'] = explode(',', $labFilter);
        }
        
        
        $hubName = $this->sampleService->getAllHubName();
        $sampleType = $this->sampleService->getSampleType();
        $currentRegimen = $this->sampleService->getAllCurrentRegimen();
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'frmSource' => $frmSource,
            'labFilter' => $labFilter,
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'currentRegimen' => $currentRegimen,
            'facilityInfo' => $facilityInfo
        ));
    }

    public function getProvinceWiseResultAwaitedDrillDownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getProvinceWiseResultAwaitedDrillDown($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getLabWiseResultAwaitedDrillDownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getLabWiseResultAwaitedDrillDown($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getDistrictWiseResultAwaitedDrillDownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getDistrictWiseResultAwaitedDrillDown($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getClinicWiseResultAwaitedDrillDownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getClinicWiseResultAwaitedDrillDown($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getFilterSampleResultAwaitedDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            
            $result = $this->sampleService->getFilterSampleResultAwaitedDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function exportResultsAwaitedSampleAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $file = $this->sampleService->generateResultsAwaitedSampleExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function expandBarChartAction()
    {
        echo "came";
        die;
        $this->layout('layout/modal.phtml');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        $params = $request->getQuery();
        return new ViewModel(array(
            'params' => $params
        ));
    }
    public function getSampleTestReasonBarChartAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleTestReasonBarChartDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                'result' => $result
            ))->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleTestResultAgeGroupTwoToFiveAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            
            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
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
            
            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
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
            
            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
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
            
            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
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
            
            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getSampleStatusDataTableAction()
    {

        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            
            $result = $this->sampleService->getSampleStatusDataTable($parameters);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }


}
