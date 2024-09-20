<?php

namespace Application\Model;

use Laminas\Db\Sql\Sql;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;


class EidSampleTypeTable extends AbstractTableGateway
{

    protected $table = 'r_eid_sample_type';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function fetchAllSampleType($asArray = false)
    {
        // Initialize SQL object
        $sql = new Sql($this->adapter);

        // Build the select query
        $select = $sql->select();
        $select->from($this->table)->where(['status' => 'active']);

        if ($asArray) {
            // Use buildSqlString to generate raw SQL query string
            $sqlString = $sql->buildSqlString($select);

            // Execute the query using the adapter and fetch the result as an array
            $resultSet = $this->adapter->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
            return $resultSet->toArray();  // Convert result set to array
        }

        // Return the result set for further usage
        return $this->select(['status' => 'active']);
    }
}
