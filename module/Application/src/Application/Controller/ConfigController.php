<?php

namespace Application\Controller;


use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;

class ConfigController extends AbstractActionController
{

    private $configService = null;

    public function __construct($configService)
    {
        $this->configService = $configService;
    }

    public function indexAction()
    {
        $this->layout()->setVariable('activeTab', 'config');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->configService->getAllConfig($params);
            return $this->getResponse()->setContent(CommonService::jsonEncode($result));
        }
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'config');
        /** @var \Laminas\Http\Request $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->configService->updateConfig($params);
            return $this->redirect()->toRoute('config');
        } else {
            $config = $this->configService->getAllGlobalConfig();
            $locales = $this->configService->getActiveLocales();
            return new ViewModel(array(
                'config' => $config,
                'locales' => $locales
            ));
        }
    }
}
