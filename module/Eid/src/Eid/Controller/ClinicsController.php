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

	public function indexAction(){
		$this->layout()->setVariable('activeTab', 'eid-clinics');
        return $this->_redirect()->toUrl('/eid/clinics/dashboard');
    }

	public function dashboardAction()
	{
		$this->layout()->setVariable('activeTab', 'eid-clinics');
	}
}
