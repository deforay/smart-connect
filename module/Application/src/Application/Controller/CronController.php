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
        // The base cron route does no work. Jobs use the named actions or CLI commands.
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
