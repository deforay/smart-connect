<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

class SummaryController extends AbstractActionController
{


    private $sampleService = null;
    private $summaryService = null;

    public function __construct($summaryService, $sampleService)
    {
        $this->summaryService = $summaryService;
        $this->sampleService = $sampleService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'summary-dashboard');
        return $this->redirect()->toRoute('summary');
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
        $this->layout()->setVariable('activeTab', 'summary');

        $summaryTabResult = $this->summaryService->fetchSummaryTabDetails($params);
        $allLineofTreatmentResult = $this->summaryService->getAllLineOfTreatmentDetails($params);
        $allCollapsibleLineofTreatmentResult = $this->summaryService->getAllCollapsibleLineOfTreatmentDetails($params);

        /* District, Province and Facility */

        $clinicName = $this->sampleService->getAllClinicName();
        $provinceName = $this->sampleService->getAllProvinceList();
        $districtName = $this->sampleService->getAllDistrictList();
        /* Ends Here*/

        return new ViewModel(array(
            'summaryTabInfo' => $summaryTabResult,
            'allLineofTreatmentInfo' => $allLineofTreatmentResult,
            'allCollapsibleLineofTreatmentResult' => $allCollapsibleLineofTreatmentResult,
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

    public function samplesReceivedDistrictAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSamplesReceivedByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    public function samplesReceivedProvinceAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSamplesReceivedByProvince($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function samplesReceivedFacilityAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSamplesReceivedByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getSuppressionRateBarChartAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->summaryService->getSuppressionRateBarChartDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                'result' => $result
            ))->setTerminal(true);
            return $viewModel;
        }
    }

    public function suppressionRateDistrictAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSuppressionRateByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    public function suppressionRateProvinceAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSuppressionRateByProvince($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function suppressionRateFacilityAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSuppressionRateByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getSamplesRejectedBarChartAction()
    {
        /** @var \Laminas\Http\Request $request */
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
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSamplesRejectedByDistrict($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function samplesRejectedFacilityAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSamplesRejectedByFacility($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }
    public function samplesRejectedProvinceAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getAllSamplesRejectedByProvince($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getRegimenGroupBarChartAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->summaryService->getRegimenGroupBarChartDetails($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array(
                'result' => $result
            ))->setTerminal(true);
            return $viewModel;
        }
    }

    public function getRegimenGroupDetailsAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $request->getPost();

            $result = $this->summaryService->getRegimenGroupSamplesDetails($parameters);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function getIndicatorsAction()
    {
        /** @var \Laminas\Http\Request $request */
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

    public function exportIndicatorResultExcelAction()
    {
        /** @var \Laminas\Http\Request $request */
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

    public function exportSuppressionRateByFacilityAction()
    {
        /** @var \Laminas\Http\Request $request */
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $file = $this->summaryService->exportSuppressionRateByFacility($params);
            $viewModel = new ViewModel();
            $viewModel->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $viewModel;
        }
    }
}
