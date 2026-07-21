<?php

namespace Application\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'send-mail',
    description: 'Send queued mails from the temp_mail table.'
)]
class SendTempMail extends Command
{

    public \Application\Service\CommonService  $commonService;

    public function __construct($commonService)
    {
        $this->commonService = $commonService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commonService->sendTempMail();
        return Command::SUCCESS;
    }
}
