<?php

namespace Application\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Sql;
use Application\Service\CommonService;


class UserFacilityMapTable extends AbstractTableGateway {

    protected $table = 'dash_user_facility_map';

    public function __construct(Adapter $adapter) {
        $this->adapter = $adapter;
    }
}