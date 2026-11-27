<?php

namespace Application\Controller;

use Laminas\View\Model\ViewModel;

/**
 * The result view and result PDF actions that the VL and EID clinic
 * controllers share. The controller provides $sampleService, whose
 * getSampleInfo() returns no row for a sample the user may not see, and
 * $configService.
 */
trait SampleResultActions
{
    public function generateResultPdfAction()
    {
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return null;
        }

        $sampleResult = $this->sampleService->getSampleInfo($request->getPost());
        if (empty($sampleResult)) {
            return $this->notFoundAction();
        }
        $config = $this->configService->getAllGlobalConfig();
        $viewModel = new ViewModel();
        $viewModel->setVariables(array('sampleResult' => $sampleResult, 'config' => $config))
            ->setTerminal(true);
        return $viewModel;
    }

    /**
     * The result page for the sample whose base64 id is in the route, or a
     * 404 when the user may not see it.
     */
    private function testResultView(string $activeTab)
    {
        $this->layout()->setVariable('activeTab', $activeTab);
        $params = ['id' => base64_decode($this->params()->fromRoute('id'))];

        $sampleResult = $this->sampleService->getSampleInfo($params);
        if (empty($sampleResult)) {
            return $this->notFoundAction();
        }
        return new ViewModel(array(
            'result' => $sampleResult
        ));
    }
}
