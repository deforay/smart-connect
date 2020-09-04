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
		$this->layout()->setVariable('activeTab', 'covid19-summary');
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

	public function getPositiveRateBarChartAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$result = $this->summaryService->getPositiveRateBarChartDetails($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'result' => $result
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function positiveRateDistrictAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllPositiveRateByDistrict($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}
	public function positiveRateProvinceAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllPositiveRateByProvince($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}

	public function positiveRateFacilityAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllPositiveRateByFacility($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}

	public function getSamplesRejectedBarChartAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$result = $this->summaryService->getSamplesRejectedBarChartDetails($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'result' => $result
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function samplesRejectedDistrictAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesRejectedByDistrict($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}

	public function samplesRejectedFacilityAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesRejectedByFacility($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}
	public function samplesRejectedProvinceAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesRejectedByProvince($parameters);
			return $this->getResponse()->setContent(Json::encode($result));
		}
	}

	public function getIndicatorsAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$keySummaryIndicatorsResult = $this->summaryService->getKeySummaryIndicatorsDetails($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'keySummaryIndicators' => $keySummaryIndicatorsResult,
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function getCovid19OutcomesAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$result = $this->summaryService->getCovid19OutcomesDetails($params);
			// Debug::dump($result);die;
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'result' => $result,
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function getCovid19OutcomesByAgeAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$result = $this->summaryService->getCovid19OutcomesByAgeDetails($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'result' => $result,
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function getCovid19OutcomesByProvinceAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$result = $this->summaryService->getCovid19OutcomesByProvinceDetails($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'result' => $result,
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function getTatAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$result = $this->summaryService->getTATDetails($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array(
				'result' => $result,
			))->setTerminal(true);
			return $viewModel;
		}
	}

	public function exportIndicatorResultExcelAction()
	{
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$file = $this->summaryService->exportIndicatorResultExcel($params);
			$viewModel = new ViewModel();
			$viewModel->setVariables(array('file' => $file))
				->setTerminal(true);
			return $viewModel;
		}
	}
}
