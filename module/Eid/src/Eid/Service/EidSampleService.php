<?php

namespace Eid\Service;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use Application\Service\CommonService;
use \PhpOffice\PhpSpreadsheet\Spreadsheet;

class EidSampleService
{

    public $sm = null;

    public function __construct($sm)
    {
        $this->sm = $sm;
    }

    public function getServiceManager()
    {
        return $this->sm;
    }

    //get all sample types
    public function getSampleType()
    {
        $sampleDb = $this->sm->get('SampleTypeTable');
        return $sampleDb->fetchAllSampleType();
    }

    public function getStats($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->getStats($params);
    }

    public function getMonthlySampleCount($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->getMonthlySampleCount($params);
    }
    public function getMonthlySampleCountByLabs($params)
    {
        $sampleDb = $this->sm->get('EidSampleTableWithoutCache');
        return $sampleDb->getMonthlySampleCountByLabs($params);
    }
}
