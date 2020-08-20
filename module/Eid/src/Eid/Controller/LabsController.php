<?php

namespace Eid\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;
use Zend\Debug\Debug;

class LabsController extends AbstractActionController
{

  private $sampleService = null;
  private $commonService = null;

  public function __construct($sampleService, $commonService)
  {
    $this->sampleService = $sampleService;
    $this->commonService = $commonService;
  }

  public function dashboardAction()
  {
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
}
