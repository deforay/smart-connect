<?php

namespace Covid19\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;


class SummaryController extends AbstractActionController
{

	private \Application\Service\CommonService $commonService;
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
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesReceivedByFacility($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}

	public function samplesReceivedProvinceAction()
	{
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesReceivedByProvince($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}

	public function samplesReceivedDistrictAction()
	{
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesReceivedByDistrict($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}

	public function getPositiveRateBarChartAction()
	{
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllPositiveRateByDistrict($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}
	public function positiveRateProvinceAction()
	{
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllPositiveRateByProvince($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}

	public function positiveRateFacilityAction()
	{
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllPositiveRateByFacility($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}

	public function getSamplesRejectedBarChartAction()
	{
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesRejectedByDistrict($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}

	public function samplesRejectedFacilityAction()
	{
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesRejectedByFacility($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}
	public function samplesRejectedProvinceAction()
	{
		/** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
		if ($request->isPost()) {
			$parameters = $request->getPost();
			$result = $this->summaryService->getAllSamplesRejectedByProvince($parameters);
			return $this->getResponse()->setContent(CommonService::jsonEncode($result));
		}
	}

	public function getIndicatorsAction()
	{
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
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
		/** @var \Laminas\Http\Request $request */
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
