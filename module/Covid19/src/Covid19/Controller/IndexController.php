<?php

namespace Covid19\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;
use Zend\Debug\Debug;

class IndexController extends AbstractActionController
{

	private $service = null;

	public function __construct($service)
	{
		$this->service = $service;
	}

	public function dashboardAction()
	{
		/* $service = $this->getServiceLocator()->get('Covid19FormService');
		$response =$service->saveFileFromVlsmAPI(); */
	}
}
