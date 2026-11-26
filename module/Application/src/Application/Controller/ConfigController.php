<?php

namespace Application\Controller;


use Laminas\View\Model\ViewModel;
use Application\Service\CommonService;

class ConfigController extends AbstractAppController
{

    private $configService = null;

    public function __construct($configService, \Application\Model\Acl $acl)
    {
        $this->setAcl($acl);
        $this->configService = $configService;
    }

    public function indexAction()
    {
        if (!$this->hasPrivilege('index')) {
            return $this->forbidden();
        }
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
        if (!$this->hasPrivilege('edit')) {
            return $this->forbidden();
        }
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
