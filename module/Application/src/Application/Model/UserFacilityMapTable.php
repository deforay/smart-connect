<?php

namespace Application\Model;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Sql\Sql;
use Application\Service\CommonService;


class UserFacilityMapTable extends AbstractTableGateway {

    protected $table = 'dash_user_facility_map';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
}