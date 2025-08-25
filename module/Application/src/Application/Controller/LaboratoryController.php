<?php

namespace Application\Controller;



use Application\Service\CommonService;
use Application\Service\SampleService;
use Application\Controller\AbstractAppController;

class LaboratoryController extends AbstractAppController
{

    public SampleService $sampleService;
    public CommonService $commonService;

    public function __construct($sampleService, $commonService)
    {
        parent::__construct();
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

        return $this->view->setVariables(array(
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
        return $this->view;
    }

    public function samplesWaitingAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        return $this->view;
    }


    public function samplesRejectedAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        return $this->view;
    }

    public function drillDownAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = [];


        $labFilter = $this->params()->fromQuery('lab');
        $params['labs'] = explode(',', $labFilter);

        $hubName = $this->sampleService->getAllHubName();
        $sampleType = $this->sampleService->getSampleType();
        $currentRegimen = $this->sampleService->getAllCurrentRegimen();
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        //print_r($facilityInfo);die;
        return $this->view->setVariables(array(
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
        $params = [];
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
        return $this->view->setVariables(array(
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
            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getIncompleteBarSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getIncompleteBarSampleDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getSampleResultAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleResultDetails($params);
            $this->view->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $this->view;
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

            $this->view->setVariables(array('result' => $result, 'sampleType' => $sampleType))
                ->setTerminal(true);
            return $this->view;
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

            $this->view->setVariables(array(
                'result' => $result,
                'sampleType' => $sampleType
            ))->setTerminal(true);
            return $this->view;
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
            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
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
            $this->view->setVariables(array(
                'result' => $result,
                'sampleType' => $sampleType
            ))->setTerminal(true);
            return $this->view;
        }
    }

    public function getSampleTestResultAgeGroupAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);
            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getSampleTestResultPregnantAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultPregnantPatientDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getSampleTestResultBreastfeedingAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultBreastfeedingPatientDetails($params);
            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getRequisitionFormsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getRequisitionFormsTested($params);
            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getSampleVolumeAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleVolume($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getFemalePatientResultAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getFemalePatientResult($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getLineOfTreatmentAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getLineOfTreatment($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
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

            $this->view->setVariables(array('result' => $result, 'height' => $params['height']))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleDetails($params);

            $this->view->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getVlOutComesAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getVlOutComes($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getBarSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getBarSampleDetails($params);

            $this->view->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getLabFilterSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->sampleService->getLabFilterSampleDetails($parameters);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function getFilterSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->sampleService->getFilterSampleDetails($parameters);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function samplesTestedLabAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = [];
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
        return $this->view->setVariables(array(
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

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getLabBarSampleDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getLabBarSampleDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function sampleVolumeAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = [];
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
        return $this->view->setVariables(array(
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

            $this->view->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function exportLabTestedSampleExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $file = $this->sampleService->generateLabTestedSampleExcel($params);

            $this->view->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function samplesTestedTurnAroundTimeAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = [];
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
        return $this->view->setVariables(array(
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

            $this->view->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getPieSampleTatAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleDetails($params);

            $this->view->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getFilterSampleTatAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->sampleService->getFilterSampleTatDetails($parameters);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function exportSampleTestedResultTatExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $file = $this->sampleService->generateLabTestedSampleTatExcel($params);

            $this->view->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $this->view;
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
            return $this->view->setVariables(array('fromMonth' => date('M-Y', strtotime($fromDate)), 'toMonth' => date('M-Y', strtotime($toDate)), 'labFilter' => $labFilter, 'labName' => $labName));
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

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function drillDownResultAwaitedAction()
    {
        $this->layout()->setVariable('activeTab', 'labs-dashboard');
        $params = [];
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
        return $this->view->setVariables(array(
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

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getLabWiseResultAwaitedDrillDownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getLabWiseResultAwaitedDrillDown($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getDistrictWiseResultAwaitedDrillDownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getDistrictWiseResultAwaitedDrillDown($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getClinicWiseResultAwaitedDrillDownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getClinicWiseResultAwaitedDrillDown($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function getFilterSampleResultAwaitedDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->sampleService->getFilterSampleResultAwaitedDetails($parameters);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function exportResultsAwaitedSampleAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $file = $this->sampleService->generateResultsAwaitedSampleExcel($params);

            $this->view->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $this->view;
        }
    }
    public function expandBarChartAction()
    {
        $this->layout('layout/modal.phtml');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        $params = $request->getQuery();
        return $this->view->setVariables(array(
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

            $this->view->setVariables(array(
                'result' => $result
            ))->setTerminal(true);
            return $this->view;
        }
    }
    public function getSampleTestResultAgeGroupTwoToFiveAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }
    public function getSampleTestResultAgeGroupSixToFourteenAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }
    public function getSampleTestResultAgeGroupFifteenToFourtynineAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }
    public function getSampleTestResultAgeGroupGreaterFiftyAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }
    public function getSampleTestResultAgeGroupUnknownAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->sampleService->getSampleTestedResultAgeGroupDetails($params);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }
    public function getSampleStatusDataTableAction()
    {

        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->sampleService->getSampleStatusDataTable($parameters);

            $this->view->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $this->view;
        }
    }
}
