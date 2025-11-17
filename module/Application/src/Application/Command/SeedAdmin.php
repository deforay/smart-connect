<?php

namespace Application\Command;

use Application\Model\UsersTable;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Seeds the first admin user (role_id = 1).
 */
class SeedAdmin extends Command
{
    protected static $defaultName = 'seed-admin';
    private UsersTable $usersTable;

    public function __construct(UsersTable $usersTable)
    {
        parent::__construct();
        $this->usersTable = $usersTable;
    }

    protected function configure()
    {
        $this
            ->setDescription('Create the first admin user (role_id = 1).')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email (required)')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Admin username (required)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password (required)')
            ->addOption('mobile', null, InputOption::VALUE_OPTIONAL, 'Admin mobile (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getOption('email');
        $username = (string) $input->getOption('username');
        $password = (string) $input->getOption('password');
        $mobile = (string) $input->getOption('mobile');

        // Prompt for missing values
        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            $helper = new QuestionHelper();
        }
        if ($email === '') {
            $question = new Question('Admin email: ');
            $email = (string) $helper->ask($input, $output, $question);
        }
        if ($username === '') {
            $question = new Question('Admin username: ');
            $username = (string) $helper->ask($input, $output, $question);
        }
        if ($password === '') {
            $question = new Question('Admin password: ');
            $question->setHidden(true)->setHiddenFallback(false);
            $password = (string) $helper->ask($input, $output, $question);
        }

        if ($email === '' || $username === '' || $password === '') {
            $output->writeln('<error>email, username, and password are required.</error>');
            return 1;
        }

        // Prevent duplicate admin
        $existing = $this->usersTable->select(['email' => $email])->current();
        if ($existing) {
            $output->writeln('<comment>User with this email already exists. Skipping.</comment>');
            return 0;
        }

        $params = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'mobile' => $mobile,
            'role' => 1,
        ];

        $this->usersTable->addUser($params);
        $output->writeln('<info>Admin user created successfully.</info>');

        return 0;
    }
}
