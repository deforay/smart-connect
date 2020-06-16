<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class TimeController extends AbstractActionController
{
  const PROVINCE = 0;
  const DISTRICT = 1;
  const CLINIC   = 2;
  public function indexAction()
  {
    set_time_limit(10000);
    $this->layout()->setVariable('activeTab', 'times-dashboard');
    return $this->_redirect()->toUrl('/times/dashboard');
  }

  public function dashboardAction()
  {
    $this->layout()->setVariable('activeTab', 'times-dashboard');
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
    $facilityService = $this->getServiceLocator()->get('FacilityService');
    $sampleService   = $this->getServiceLocator()->get('SampleService');
    $provinces       = $facilityService->fetchLocationDetails();
    $districts       = $facilityService->getAllDistrictsList();
    $clinics         = $sampleService->getAllClinicName();
    $labs            = $sampleService->getAllLabName();

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
      $sampleService = $this->getServiceLocator()->get('SampleService');
      $facilityService = $this->getServiceLocator()->get('FacilityService');
      $dates = explode(" to ", $params['sampleCollectionDate']);
      $category = $params['category'];
      $labs = (isset($params['lab']) && !empty($params['lab'])) ? $params['lab'] : array();

      $facilities = $facilityService->fetchLocationDetails();
      $result = $sampleService->getTATbyProvince($facilities, $labs, $dates[0], $dates[1]);
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
      $sampleService = $this->getServiceLocator()->get('SampleService');
      $facilityService = $this->getServiceLocator()->get('FacilityService');
      $labs = (isset($params['lab']) && !empty($params['lab'])) ? $params['lab'] : array();
      $dates = explode(" to ", $params['sampleCollectionDate']);
      $category = $params['category'];
      $place = $params['place'];

      if ($params['category'] == self::PROVINCE) { // If it is a Province: It brings the respective Districts TATs
        $facilities = $facilityService->getDistrictList($params['province']);
        $result = $sampleService->getTATbyDistrict($facilities, $labs, $dates[0], $dates[1]);
      } else if ($params['category'] == self::DISTRICT) { // If it is a District: It brings the respective Clinics TATs
        $facilities   = $facilityService->getFacilityByDistrict($params['district']);
        $result       = $sampleService->getTATbyClinic($facilities, $labs, $dates[0], $dates[1]);
      } else { // Brings the TAT ordered by Province
        $facilities = $facilityService->fetchLocationDetails();
        $result = $sampleService->getTATbyProvince($facilities, $labs, $dates[0], $dates[1]);
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
      $sampleService    = $this->getServiceLocator()->get('SampleService');
      $facilityService  = $this->getServiceLocator()->get('FacilityService');
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
        $provinceArray = $facilityService->fetchLocationDetails();
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
            $districtArray = array_merge($districtArray, $facilityService->getDistrictList($provinces[$i]));
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
            $clinicArray = array_merge($clinicArray, $facilityService->getFacilityByDistrict($districts[$i]));
          }
        }
      }

      $viewModel = new ViewModel();
      $viewModel->setVariables(
        array(
          'daterange'       => $params['sampleCollectionDate'],
          'labs'            => (isset($labs) && !empty($labs)) ? implode(',', $labs) : '',
          'resultProvinces' => $sampleService->getTATbyProvince($provinceArray, $labs, $dates[0], $dates[1]),
          'resultDistricts' => $sampleService->getTATbyDistrict($districtArray, $labs, $dates[0], $dates[1]),
          'resultClinics'   => $sampleService->getTATbyClinic($clinicArray, $labs, $dates[0], $dates[1]),
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
      //echo "<script type='text/javascript'>alert('".json_encode($sampleService -> getTATbyProvince($provinceArray,$labs,$dates[0],$dates[1]))."');</script>";
      return $viewModel;
    }
  }
}
