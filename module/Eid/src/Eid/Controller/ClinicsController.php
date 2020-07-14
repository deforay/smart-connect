<?php

namespace Eid\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;
use Zend\Debug\Debug;

class ClinicsController extends AbstractActionController
{

  private $sampleService = null;

  public function __construct($sampleService)
  {
    $this->sampleService = $sampleService;
  }

  public function dashboardAction()
  {
  }
}
