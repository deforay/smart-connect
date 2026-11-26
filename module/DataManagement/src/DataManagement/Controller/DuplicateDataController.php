<?php

namespace DataManagement\Controller;

use Application\Controller\Traits\AclAwareTrait;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;


class DuplicateDataController extends AbstractActionController
{
  use AclAwareTrait;

  public \Application\Service\SampleService $sampleService;

  public function __construct($sampleService, \Application\Model\Acl $acl)
  {
    $this->sampleService = $sampleService;
    $this->setAcl($acl);
  }

  public function indexAction()
  {
    if (!$this->hasPrivilege('index')) {
      return $this->forbidden();
    }
    $this->layout()->setVariable('activeTab', 'duplicate-data');
    /** @var \Laminas\Http\Request $request */
    $request = $this->getRequest();
    if ($request->isPost()) {
      $parameters = $request->getPost();
      $result = $this->sampleService->getAllSamples($parameters);
      return $this->getResponse()->setContent(CommonService::jsonEncode($result));
    }
  }

  public function removeAction()
  {
    if (!$this->hasPrivilege('edit')) {
      return $this->forbidden();
    }
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
    if (!$this->hasPrivilege('edit')) {
      return $this->forbidden();
    }
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
