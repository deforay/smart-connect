<?php

namespace DataManagement\Controller;

use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;


class DuplicateDataController extends AbstractActionController
{

  public \Application\Service\SampleService $sampleService;

  private \Application\Model\Acl $acl;

  public function __construct($sampleService, \Application\Model\Acl $acl)
  {
    $this->sampleService = $sampleService;
    $this->acl = $acl;
  }

  private function isAllowed(string $privilege): bool
  {
    $role = (new \Application\Session\Container('credo'))->roleCode;
    return $this->acl->isAllowed($role, 'DataManagement\Controller\DuplicateDataController', $privilege);
  }

  private function forbidden()
  {
    /** @var \Laminas\Http\Response $response */
    $response = $this->getResponse();
    $response->setStatusCode(403);
    $response->setContent('Forbidden');
    return $response;
  }

  public function indexAction()
  {
    if (!$this->isAllowed('index')) {
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
    if (!$this->isAllowed('edit')) {
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
    if (!$this->isAllowed('edit')) {
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
