<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Json\Json;

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
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $this->configService->getAllConfig($params);
            return $this->getResponse()->setContent(Json::encode($result));
        }
    }

    public function editAction()
    {
        $this->layout()->setVariable('activeTab', 'config');
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
