<?php

namespace DataManagement\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class DuplicateDataController extends AbstractActionController
{

  public \Application\Service\SampleService $sampleService;

  public function __construct($sampleService)
  {
    $this->sampleService = $sampleService;
  }

  public function indexAction()
  {
    $this->layout()->setVariable('activeTab', 'duplicate-data');
    /** @var \Laminas\Http\Request $request */
    $request = $this->getRequest();
    if ($request->isPost()) {
      $parameters = $request->getPost();
      $result = $this->sampleService->getAllSamples($parameters);
      return $this->getResponse()->setContent(Json::encode($result));
    }
  }

  public function removeAction()
  {
    /** @var \Laminas\Http\Request $request */
    $request = $this->getRequest();
    if ($request->isPost()) {
      $params = $request->getPost();
      $response = $this->sampleService->removeDuplicateSampleRows($params);
      $viewModel = new ViewModel();
      $viewModel->setVariables(array('response' => $response))
        ->setTerminal(true);
      return $viewModel;
    }
  }

  public function editAction()
  {
    $this->layout()->setVariable('activeTab', 'duplicate-data');
    $id = base64_decode($this->params()->fromRoute('id'));
    $sample = $this->sampleService->getSample($id);
    if (isset($sample->vl_sample_id)) {
      return new ViewModel(array('sample' => $sample));
    } else {
      return $this->redirect()->toRoute('duplicate-data');
    }
  }
}
