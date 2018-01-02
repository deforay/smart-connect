<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Json\Json;

class TimeController extends AbstractActionController{
    const PROVINCE = 0;
    const DISTRICT = 1;
    const CLINIC   = 2;
    public function indexAction(){
        set_time_limit(10000);
        $this->layout()->setVariable('activeTab', 'times-dashboard');
        return $this->_redirect()->toUrl('/times/dashboard');
    }

    public function dashboardAction(){
        $this->layout()->setVariable('activeTab', 'times-dashboard');
        $sampleService   = $this->getServiceLocator()->get('SampleService');
        $facilityService = $this->getServiceLocator()->get('FacilityService');
        $provinces       = $facilityService -> fetchLocationDetails();
        $districts       = $facilityService -> getAllDistrictsList();
        $sampleType      = $sampleService -> getSampleType();
        $clinicName      = $sampleService -> getAllClinicName();
        $labName         = $sampleService -> getAllLabName();

        $params = array();
        $gender="";
        $month="";
        $range="";
        $fromMonth="";
        $toMonth="";
        $labFilter="";
        $result="";

        if($this->params()->fromQuery('month')){
            $month=$this->params()->fromQuery('month');
        }
        if($this->params()->fromQuery('range')){
            $range=$this->params()->fromQuery('range');
        }
        if($this->params()->fromQuery('fromMonth')){
            $fromMonth=$this->params()->fromQuery('fromMonth');
        }
        if($this->params()->fromQuery('toMonth')){
            $toMonth=$this->params()->fromQuery('toMonth');
        }
        if($this->params()->fromQuery('lab')){
            $labFilter=$this->params()->fromQuery('lab');
            $params['labs'] = explode(',',$labFilter);
        }
        if($this->params()->fromQuery('result')){
            $result=$this->params()->fromQuery('result');
        }
        $sampleService = $this->getServiceLocator()->get('SampleService');
        $commonService = $this->getServiceLocator()->get('CommonService');
        $hubName = $sampleService->getAllHubName();
        $currentRegimen = $sampleService->getAllCurrentRegimen();
        $facilityInfo = $commonService->getSampleTestedFacilityInfo($params);

        return new ViewModel(array(
                'clinicName' => $clinicName,
                'labName'    => $labName,
                'provinces' => $provinces,
                'districts' => $districts,
                'hubName' => $hubName,
                'currentRegimen' => $currentRegimen,
                'searchMonth' => $month,
                'searchGender' => $gender,
                'searchRange' => $range,
                'fromMonth' => $fromMonth,
                'toMonth' => $toMonth,
                'labFilter' => $labFilter,
                'facilityInfo' => $facilityInfo,
                'result' => $result
            )
        );
    }


    public function getTATDefaultAction(){
      $request = $this->getRequest();
      if ($request->isPost()) {
          $params = $request->getPost();
          $sampleService = $this->getServiceLocator()->get('SampleService');
          $facilityService = $this->getServiceLocator()->get('FacilityService');
          $dates = explode(" to ",$params['sampleCollectionDate']);
          $category = $params['category'];

          $facilities = $facilityService->fetchLocationDetails();
          $result = $sampleService -> getTATbyProvince($facilities,$dates[0],$dates[1]);

          if($category=='provinces'){
            $facilities = $facilityService->fetchLocationDetails();
            $result = $sampleService -> getTATbyProvince($facilities,$dates[0],$dates[1]);
          }
          else if($category=='labs'){
            $facilities = $sampleService -> getAllLabName();
            $labs = array();
            $i = 0;
            foreach ($facilities as $key) {
              $labs[$i] = array(
                'location_id'   => $key['facility_code'],
                'location_name' => $key['facility_name']
              );
              $i++;
            }
            $result = $sampleService -> getTATbyLab($labs,$dates[0],$dates[1]);
          }
          $viewModel = new ViewModel();
          $viewModel -> setVariables(array('results' => $result,'categoryChecked'=>$category))
                     -> setTerminal(true);
          return $viewModel;
        }
    }

    public function getTATfromURLAction(){
      $request = $this->getRequest();
      if ($request->isPost()) {
          $params = $request->getPost();
          $sampleService = $this->getServiceLocator()->get('SampleService');
          $facilityService = $this->getServiceLocator()->get('FacilityService');
          $dates = explode(" to ",$params['sampleCollectionDate']);
          $category = $params['category'];
          $place = $params['place'];

          if($params['category'] == self::PROVINCE){ // If it is a Province: It brings the respective Districts TATs
            $facilities = $facilityService -> getDistrictList($params['facility']);
            $result = $sampleService -> getTATbyDistrict($facilities,$dates[0],$dates[1]);
          }
          else if($params['category'] == self::DISTRICT){ // If it is a District: It brings the respective Clinics TATs
            $facilities   = $facilityService -> getFacilityByDistrict($params['facility']);
            $result       = $sampleService -> getTATbyClinic($facilities,$dates[0],$dates[1]);
          }
          else{ // Brings the TAT ordered by Province
            $facilities = $facilityService -> fetchLocationDetails();
            $result = $sampleService -> getTATbyProvince($facilities,$dates[0],$dates[1]);
          }
          $viewModel = new ViewModel();
          $viewModel->setVariables(
              array(
                'results'    => $result,
                'facilities' => $facilities,
                'category'   => $params['category'],
                'place'      => $place
              )
          )
          ->setTerminal(true);
          return $viewModel;
      }
    }

    public function getTATfromSearchFieldAction(){
      $request = $this -> getRequest();
      if ($request -> isPost()) {
          $params           = $request->getPost();
          $sampleService    = $this->getServiceLocator()->get('SampleService');
          $facilityService  = $this->getServiceLocator()->get('FacilityService');
          $category         = $params['category'];
          $provinces        = $params['provinces'];
          $districts        = $params['districts'];
          $clinics          = $params['clinics'];
          $provinceNames    = $params['provinceNames'];
          $districtNames    = $params['districtNames'];
          $clinicNames      = $params['clinicNames'];
          $dates            = explode(" to ", $params['sampleCollectionDate']);
          $provinceArray    = array();
          $districtArray    = array();
          $clinicArray      = array();
          $times            = array();

          if(isset($provinces) && !empty($provinces)){
            for($i=0; $i < sizeOf($provinces); $i++){
              $provinceArray[]  = array(
                'location_id'   => $provinces[$i],
                'location_name' => $provinceNames[$i]
              );
            }
          }
          else{
            $provinceArray = $facilityService -> fetchLocationDetails();
          }

          if(isset($districts) && !empty($districts)){
            for($i=0; $i < sizeOf($districts); $i++){
              $districtArray[] = array(
                'location_id'   => $districts[$i],
                'location_name' => $districtNames[$i]
              );
            }
          }
          else{
            if(isset($provinces) && !empty($provinces)){
              for($i=0; $i < sizeOf($provinces); $i++){
                $districtArray = array_merge($districtArray, $facilityService -> getDistrictList($provinces[$i]));
              }
            }
          }
          if(isset($clinics) && !empty($clinics)){
            for($i=0; $i < sizeOf($clinics); $i++){
              $clinicArray[] = array(
                'facility_id'   => $clinics[$i],
                'facility_name' => $clinicNames[$i]
              );
            }
          }
          else{
            if(isset($districts) && !empty($districts)){
              for($i=0; $i < sizeOf($districts); $i++){
                $clinicArray = array_merge($clinicArray, $facilityService -> getFacilityByDistrict($districts[$i]));
              }
            }
          }

          $viewModel = new ViewModel();
          $viewModel -> setVariables(
                          array(
                            'resultProvinces' => $sampleService -> getTATbyProvince($provinceArray,$dates[0],$dates[1]),
                            'resultDistricts' => $sampleService -> getTATbyDistrict($districtArray,$dates[0],$dates[1]),
                            'resultClinics'   => $sampleService -> getTATbyClinic($clinicArray,$dates[0],$dates[1]),
                            'provinceNames'   => $provinceNames,
                            'districtNames'   => $districtNames,
                            'clinicNames'     => $clinicNames,
                            'provincesID'     => $provinces,
                            'districtsID'     => $districts,
                            'clinicsID'       => $clinics,
                            'category'        => $params['category']                          )
                        )
                     -> setTerminal(true);
          //echo "<script type='text/javascript'>alert('".json_encode($sampleService -> getTATbyProvince($provinceArray,$dates[0],$dates[1]))."');</script>";
          return $viewModel;
      }
    }
}
