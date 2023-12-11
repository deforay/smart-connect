<?php

namespace Application\Controller;

use Laminas\Json\Json;
use Laminas\View\Model\ViewModel;
use Application\Service\SampleService;
use Application\Service\SummaryService;
use Application\Controller\AbstractAppController;

class SummaryController extends AbstractAppController
{

    public SampleService $sampleService;
    public SummaryService $summaryService;

    public function __construct(SummaryService $summaryService, SampleService $sampleService)
    {
        parent::__construct();
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
        $this->ajaxAction();
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->summaryService->getSamplesReceivedBarChartDetails($params);
            $this->view->setVariables(array(
                'result' => $result
            ));
            return $this->view;
        }
    }

    public function samplesReceivedDistrictAction()
    {
        $this->ajaxAction();
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
        $this->ajaxAction();
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
        $this->ajaxAction();
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
        $this->ajaxAction();
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->summaryService->getSuppressionRateBarChartDetails($params);
            $this->view->setVariables(array(
                'result' => $result
            ));
            return $this->view;
        }
    }

    public function suppressionRateDistrictAction()
    {
        $this->ajaxAction();
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
        $this->ajaxAction();
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
        $this->ajaxAction();
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
        $this->ajaxAction();
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->summaryService->getSamplesRejectedBarChartDetails($params);

            $this->view->setVariables(array(
                'result' => $result
            ));
            return $this->view;
        }
    }

    public function samplesRejectedDistrictAction()
    {
        $this->ajaxAction();
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
        $this->ajaxAction();
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
        $this->ajaxAction();
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
        $this->ajaxAction();
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $result = $this->summaryService->getRegimenGroupBarChartDetails($params);

            $this->view->setVariables(array(
                'result' => $result
            ));
            return $this->view;
        }
    }

    public function getRegimenGroupDetailsAction()
    {
        $this->ajaxAction();
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
        $this->ajaxAction();
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $keySummaryIndicatorsResult = $this->summaryService->getKeySummaryIndicatorsDetails($params);

            $this->view->setVariables(array(
                'keySummaryIndicators' => $keySummaryIndicatorsResult,
            ));
            return $this->view;
        }
    }

    public function exportIndicatorResultExcelAction()
    {
        $this->ajaxAction();
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $file = $this->summaryService->exportIndicatorResultExcel($params);

            $this->view->setVariables(array('file' => $file))
                ->setTerminal(true);
            return $this->view;
        }
    }

    public function exportSuppressionRateByFacilityAction()
    {
        $this->ajaxAction();
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();

            $file = $this->summaryService->exportSuppressionRateByFacility($params);

            $this->view->setVariables(array('file' => $file));
            return $this->view;
        }
    }
}
