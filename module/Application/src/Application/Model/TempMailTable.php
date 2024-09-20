<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;


class TempMailTable extends AbstractTableGateway
{

    protected $table = 'temp_mail';
    protected $adapter;

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function insertTempMailDetails($toEmailAddress, $subject, $message, $fromEmailAddress, $fromName, $cc, $bcc)
    {
        $data = array(
            'report_email' => $fromEmailAddress,
            'to_mail' => $toEmailAddress,
            'subject' => $subject,
            'text_message' => $message
        );
        $this->insert($data);
        return $this->lastInsertValue;
    }

    public function updateTempMailStatus($id)
    {
        return $this->update(array('status' => 'not-sent'), array('id' => $id));
    }

    public function deleteTempMail($id)
    {
        $this->delete(array('id = ' . $id));
    }
}
