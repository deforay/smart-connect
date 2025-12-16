<?php

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;

class CronController extends AbstractActionController
{

    private $sampleService = null;

    public function __construct($sampleService)
    {
        $this->sampleService = $sampleService;
    }

    public function indexAction()
    {
    }

    public function importVlAction()
    {
        return false;
    }

    public function generateBackupAction()
    {

        $this->sampleService->generateBackup();
    }
}
