<?php

namespace Covid19\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class LabsController extends AbstractActionController
{

    private $sampleService = null;
    private $facilityService = null;
    private $commonService = null;
    const PROVINCE = 0;
    const DISTRICT = 1;
    const CLINIC   = 2;

    public function __construct($sampleService, $facilityService, $commonService)
    {
        $this->sampleService = $sampleService;
        $this->facilityService = $facilityService;
        $this->commonService = $commonService;
    }

    public function dashboardAction()
    {
        $this->layout()->setVariable('activeTab', 'covid19-labs');
        $labName = $this->sampleService->getAllLabName();
        $provinceName = $this->sampleService->getAllProvinceList();
        $districtName = $this->sampleService->getAllDistrictList();
        $clinicName = $this->sampleService->getAllClinicName();
        return new ViewModel(array(
            'labName' => $labName,
            'provinceName' => $provinceName,
            'districtName' => $districtName,
            'clinicName' => $clinicName,
        ));
    }

    public function statsAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getStats($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('params' => $params, 'result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getMonthlySampleCountAction()
    {
        $request = $this->getRequest();

        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getMonthlySampleCount($params);
            $sampleType = $this->sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'sampleType' => $sampleType))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getMonthlySampleCountByLabsAction()
    {
        $request = $this->getRequest();

        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getMonthlySampleCountByLabs($params);
            $sampleType = $this->sampleService->getSampleType();
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result, 'sampleType' => $sampleType))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getLabTurnAroundTimeAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getLabTurnAroundTime($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }
    public function getLabPerformanceAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getLabPerformance($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('result' => $result))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function timeAction()
    {
        $params = array();
        $month = "";
        $range = "";
        $provinceFilter = "";
        $districtFilter = "";
        $labFilter = "";

        if ($this->params()->fromQuery('month')) {
            $month = $this->params()->fromQuery('month');
        }
        if ($this->params()->fromQuery('range')) {
            $range = $this->params()->fromQuery('range');
        }
        if ($this->params()->fromQuery('province')) {
            $provinceFilter = $this->params()->fromQuery('province');
        }
        if ($this->params()->fromQuery('district')) {
            $districtFilter = $this->params()->fromQuery('district');
        }
        if ($this->params()->fromQuery('lab')) {
            $labFilter = $this->params()->fromQuery('lab');
            $params['labs'] = explode(',', $labFilter);
        }

        $provinces       = $this->facilityService->fetchLocationDetails();
        $districts       = $this->facilityService->getAllDistrictsList();
        $clinics         = $this->sampleService->getAllClinicName();
        $labs            = $this->sampleService->getAllLabName();

        return new ViewModel(
            array(
                'provinces' => $provinces,
                'districts' => $districts,
                'clinics' => $clinics,
                'labs'    => $labs,
                'searchMonth' => $month,
                'searchRange' => $range,
                'labFilter' => $labFilter,
                'provinceFilter' => $provinceFilter,
                'districtFilter' => $districtFilter
            )
        );
    }

    public function getTATDefaultAction()
    {

        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $dates = explode(" to ", $params['sampleCollectionDate']);
            $category = $params['category'];
            $labs = (isset($params['lab']) && !empty($params['lab'])) ? $params['lab'] : array();

            $result = $this->sampleService->getTATbyProvince($labs, $dates[0], $dates[1]);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('results' => $result, 'daterange' => $params['sampleCollectionDate'], 'labs' => implode(',', $labs), 'categoryChecked' => $category))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getTATfromURLAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $labs = (isset($params['lab']) && !empty($params['lab'])) ? $params['lab'] : array();
            $dates = explode(" to ", $params['sampleCollectionDate']);
            $place = $params['place'];

            if ($params['category'] == self::PROVINCE) { // If it is a Province: It brings the respective Districts TATs
                $result = $this->sampleService->getTATbyDistrict($labs, $dates[0], $dates[1]);
            } else if ($params['category'] == self::DISTRICT) { // If it is a District: It brings the respective Clinics TATs
                $result       = $this->sampleService->getTATbyClinic($labs, $dates[0], $dates[1]);
            } else { // Brings the TAT ordered by Province
                $result = $this->sampleService->getTATbyProvince($labs, $dates[0], $dates[1]);
            }
            $viewModel = new ViewModel();
            $viewModel->setVariables(
                array(
                    'results'    => $result,
                    'daterange'  => $params['sampleCollectionDate'],
                    'labs'       => (count($labs) > 0) ? implode(',', $labs) : '',
                    'facilities' => $facilities,
                    'category'   => $params['category'],
                    'place'      => $place
                )
            )
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function getTATfromSearchFieldAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params           = $request->getPost();

            $category         = $params['category'];
            $provinces        = $params['provinces'];
            $districts        = $params['districts'];
            $clinics          = $params['clinics'];
            $labs             = $params['labs'];
            $provinceNames    = $params['provinceNames'];
            $districtNames    = $params['districtNames'];
            $clinicNames      = $params['clinicNames'];
            $dates            = explode(" to ", $params['sampleCollectionDate']);
            $provinceArray    = array();
            $districtArray    = array();
            $clinicArray      = array();
            $times            = array();

            if (isset($provinces) && !empty($provinces)) {
                for ($i = 0; $i < sizeOf($provinces); $i++) {
                    $provinceArray[]  = array(
                        'location_id'   => $provinces[$i],
                        'location_name' => $provinceNames[$i]
                    );
                }
            } else {
                $provinceArray = $this->facilityService->fetchLocationDetails();
            }

            if (isset($districts) && !empty($districts)) {
                for ($i = 0; $i < sizeOf($districts); $i++) {
                    $districtArray[] = array(
                        'location_id'   => $districts[$i],
                        'location_name' => $districtNames[$i]
                    );
                }
            } else {
                if (isset($provinces) && !empty($provinces)) {
                    for ($i = 0; $i < sizeOf($provinces); $i++) {
                        $districtArray = array_merge($districtArray, $this->facilityService->getDistrictList($provinces[$i]));
                    }
                }
            }
            if (isset($clinics) && !empty($clinics)) {
                for ($i = 0; $i < sizeOf($clinics); $i++) {
                    $clinicArray[] = array(
                        'facility_id'   => $clinics[$i],
                        'facility_name' => $clinicNames[$i]
                    );
                }
            } else {
                if (isset($districts) && !empty($districts)) {
                    for ($i = 0; $i < sizeOf($districts); $i++) {
                        $clinicArray = array_merge($clinicArray, $this->facilityService->getFacilityByDistrict($districts[$i]));
                    }
                }
            }

            $viewModel = new ViewModel();
            $viewModel->setVariables(
                array(
                    'daterange'       => $params['sampleCollectionDate'],
                    'labs'            => (isset($labs) && !empty($labs)) ? implode(',', $labs) : '',
                    'resultProvinces' => $this->sampleService->getTATbyProvince($labs, $dates[0], $dates[1]),
                    'resultDistricts' => $this->sampleService->getTATbyDistrict($labs, $dates[0], $dates[1]),
                    'resultClinics'   => $this->sampleService->getTATbyClinic($labs, $dates[0], $dates[1]),
                    'provinceNames'   => $provinceNames,
                    'districtNames'   => $districtNames,
                    'clinicNames'     => $clinicNames,
                    'provincesID'     => $provinces,
                    'districtsID'     => $districts,
                    'clinicsID'       => $clinics,
                    'category'        => $params['category']
                )
            )
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function drillDownResultAwaitedAction()
    {
        $this->layout()->setVariable('activeTab', 'covid19-labs');
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
        $facilityInfo = $this->commonService->getSampleTestedFacilityInfo($params);
        return new ViewModel(array(
            'frmSource' => $frmSource,
            'labFilter' => $labFilter,
            'sampleType' => $sampleType,
            'hubName' => $hubName,
            'facilityInfo' => $facilityInfo
        ));
    }

    public function getProvinceWiseResultAwaitedDrillDownAction()
    {
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
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->sampleService->getFilterSampleResultAwaitedDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function drillDownAction()
    {
        $this->layout()->setVariable('activeTab', 'covid19-labs');
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

    public function getSampleDetailsAction()
    {
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

    public function getFilterSampleDetailsAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $result = $this->sampleService->getFilterSampleDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getLabFilterSampleDetailsAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();
            $result = $this->sampleService->getLabFilterSampleDetails($parameters);
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

        // $sampleService = $this->getServiceLocator()->get('SampleService');
        // $commonService = $this->getServiceLocator()->get('CommonService');
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
        // $sampleService = $this->getServiceLocator()->get('SampleService');
        // $commonService = $this->getServiceLocator()->get('CommonService');
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
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $file = $this->sampleService->generateSampleResultExcel($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }

    public function exportLabTestedSampleExcelAction()
    {
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

    public function getCovid19OutcomesByAgeAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getCovid19OutcomesByAgeInLabsDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                'result' => $result,
            ))->setTerminal(true);
            return $viewModel;
        }
    }

    public function getCovid19PositivityRateAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->sampleService->getCovid19PositivityRateDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                'result' => $result
            ))->setTerminal(true);
            return $viewModel;
        }
    }
}
