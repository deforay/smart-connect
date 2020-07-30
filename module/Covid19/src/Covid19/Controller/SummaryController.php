<?php

namespace Covid19\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;
use Zend\Debug\Debug;

class SummaryController extends AbstractActionController
{

	private $commonService = null;
	private $summaryService = null;

	public function __construct($summaryService, $commonService)
	{
		$this->summaryService = $summaryService;
		$this->commonService = $commonService;
	}

	public function dashboardAction()
	{
		if ($this->params()->fromQuery('f') && $this->params()->fromQuery('t')) {
			$params['fromDate'] = $this->params()->fromQuery('f');
			$params['toDate'] = $this->params()->fromQuery('t');
		} else {
			$params['fromDate']  = date('Y-m', strtotime('+1 month', strtotime('-12 month')));
			$params['toDate']  = date('Y-m');
		}
		$this->layout()->setVariable('activeTab', 'summary-dashboard');
		$summaryTabResult = $this->summaryService->fetchSummaryTabDetails($params);

		/* District, Province and Facility */
		$clinicName = $this->commonService->getAllClinicName();
		$provinceName = $this->commonService->getAllProvinceList();
		$districtName = $this->commonService->getAllDistrictList();
		/* Ends Here*/

		return new ViewModel(array(
			'summaryTabInfo' => $summaryTabResult,
			'startDate' => $params['fromDate'],
			'endDate' => $params['toDate'],
			'clinicName' => $clinicName,
			'provinceName' => $provinceName,
			'districtName' => $districtName
		));
	}

	public function getSamplesReceivedBarChartAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$result = $this->summaryService->getSamplesReceivedBarChartDetails($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'result' => $result
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function samplesReceivedFacilityAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesReceivedByFacility($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}

	public function samplesReceivedProvinceAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesReceivedByProvince($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}

	public function samplesReceivedDistrictAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesReceivedByDistrict($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}
}
