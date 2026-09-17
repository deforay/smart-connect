<?php

declare(strict_types=1);

// Run with: php tests/mail-queue.php. No network or persistent database is used.
require dirname(__DIR__) . '/vendor/autoload.php';

use Application\Model\TempMailTable;
use Application\Service\CommonService;
use Laminas\Db\Adapter\Adapter;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
$db->query("CREATE TABLE temp_mail (
    id INTEGER PRIMARY KEY, status TEXT DEFAULT 'pending', report_email TEXT,
    subject TEXT, text_message TEXT, to_mail TEXT, cc TEXT, bcc TEXT
)", Adapter::QUERY_MODE_EXECUTE);
$table = new TempMailTable($db);
foreach ([1 => 'retry', 2 => 'deliver', 3 => 'invalid'] as $id => $subject) {
    $table->insert([
        'id' => $id,
        'report_email' => $id === 3 ? 'invalid-address' : 'sender@example.org',
        'subject' => $subject,
        'text_message' => '<p>Test</p>',
        'to_mail' => ' first@example.org, , second@example.org ',
        'cc' => ' cc1@example.org, cc2@example.org ',
        'bcc' => ' bcc1@example.org, bcc2@example.org ',
    ]);
}
$config = ['email' => ['host' => 'smtp.example.org', 'config' => [
    'port' => 587, 'ssl' => 'tls', 'username' => 'user+name@example.org', 'password' => 'a:@/?#%',
]]];
$services = new class($db, $config) {
    public function __construct(private Adapter $db, private array $config) {}

    public function get(string $name): Adapter|array
    {
        return match ($name) {
            'Config' => $this->config,
            'Laminas\Db\Adapter\Adapter' => $this->db,
            default => throw new RuntimeException('Unexpected service: ' . $name),
        };
    }
};
$mailer = new class implements MailerInterface {
    public bool $fail = true;
    /** @var list<Email> */
    public array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        check($message instanceof Email, 'Expected an email');
        if ($this->fail && $message->getSubject() === 'retry') {
            throw new RuntimeException('Simulated SMTP failure');
        }
        $this->sent[] = $message;
    }
};
$service = new CommonService($services, null, $table);
$service->sendTempMail($mailer);
check(count($mailer->sent) === 1, 'A failed message must not stop later deliveries');
check($table->select(['id' => 1])->current()['status'] === 'pending', 'Failure must remain retryable');
check($table->select(['id' => 2])->count() === 0, 'Successful mail must leave the queue');
check($table->select(['id' => 3])->current()['status'] === 'pending', 'Malformed mail must remain queued');
$email = $mailer->sent[0];
foreach (['To' => ['first', 'second'], 'Cc' => ['cc1', 'cc2'], 'Bcc' => ['bcc1', 'bcc2']] as $header => $names) {
    $actual = array_map(static fn ($address): string => $address->getAddress(), $email->{'get' . $header}());
    $expected = array_map(static fn (string $name): string => $name . '@example.org', $names);
    check($actual === $expected, 'All ' . $header . ' recipients must be preserved');
}
$mailer->fail = false;
$service->sendTempMail($mailer);
check(count($mailer->sent) === 2, 'Failed mail must succeed on a later run');
check($table->select(['id' => 1])->count() === 0, 'Retried mail must leave the queue after success');

// Constructing the transport does not connect to the SMTP server.
$factory = new ReflectionMethod(CommonService::class, 'createMailTransport');
foreach ([[587, 'tls', false], [465, 'tls', true], [2465, 'ssl', true]] as [$port, $ssl, $implicit]) {
    $settings = $config['email'];
    $settings['config']['port'] = $port;
    $settings['config']['ssl'] = $ssl;
    $transport = $factory->invoke($service, $settings);
    check($transport->isTlsRequired(), 'SMTP encryption must be required');
    check($transport->getStream()->isTLS() === $implicit, 'Incorrect TLS mode');
    check($transport->getUsername() === $settings['config']['username'], 'Username must remain intact');
    check($transport->getPassword() === $settings['config']['password'], 'Password must remain intact');
}
echo "Mail queue regression checks passed.\n";
