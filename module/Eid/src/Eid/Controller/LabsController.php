<?php

namespace Eid\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;
use Zend\Debug\Debug;

class LabsController extends AbstractActionController
{

	private $sampleService = null;
	private $facilityService = null;
	private $commonService = null;
	const PROVINCE = 0;
	const DISTRICT = 1;
	const CLINIC   = 2;

	public function __construct($sampleService, $facilityService,$commonService)
	{
		$this->sampleService = $sampleService;
		$this->facilityService = $facilityService;
		$this->commonService = $commonService;
	}

	public function dashboardAction()
	{
		$this->layout()->setVariable('activeTab', 'eid-labs');
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
			$result = $this->sampleService->fetchLabPerformance($params);
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

			$facilities = $this->facilityService->fetchLocationDetails();
			$result = $this->sampleService->getTATbyProvince($facilities, $labs, $dates[0], $dates[1]);
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
				$facilities = $this->facilityService->getDistrictList($params['province']);
				$result = $this->sampleService->getTATbyDistrict($facilities, $labs, $dates[0], $dates[1]);
			} else if ($params['category'] == self::DISTRICT) { // If it is a District: It brings the respective Clinics TATs
				$facilities   = $this->facilityService->getFacilityByDistrict($params['district']);
				$result       = $this->sampleService->getTATbyClinic($facilities, $labs, $dates[0], $dates[1]);
			} else { // Brings the TAT ordered by Province
				$facilities = $this->facilityService->fetchLocationDetails();
				$result = $this->sampleService->getTATbyProvince($facilities, $labs, $dates[0], $dates[1]);
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
					'resultProvinces' => $this->sampleService->getTATbyProvince($provinceArray, $labs, $dates[0], $dates[1]),
					'resultDistricts' => $this->sampleService->getTATbyDistrict($districtArray, $labs, $dates[0], $dates[1]),
					'resultClinics'   => $this->sampleService->getTATbyClinic($clinicArray, $labs, $dates[0], $dates[1]),
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
			//echo "<script type='text/javascript'>alert('".json_encode($this->sampleService -> getTATbyProvince($provinceArray,$labs,$dates[0],$dates[1]))."');</script>";
			return $viewModel;
		}
	}

	public function drillDownResultAwaitedAction()
    {
        $this->layout()->setVariable('activeTab', 'eid-labs');
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
		// $sampleService = $this->getServiceLocator()->get('SampleService');
		// $commonService = $this->getServiceLocator()->get('CommonService');
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
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            // $sampleService = $this->getServiceLocator()->get('SampleService');
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
            // $sampleService = $this->getServiceLocator()->get('SampleService');
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
            // $sampleService = $this->getServiceLocator()->get('SampleService');
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
            // $sampleService = $this->getServiceLocator()->get('SampleService');
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
            // $sampleService = $this->getServiceLocator()->get('SampleService');
            $result = $this->sampleService->getFilterSampleResultAwaitedDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
}
