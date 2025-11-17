<?php
namespace Application\Model;

use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\ResultSet;

abstract class BaseTableGateway extends AbstractTableGateway
{
    protected function selectOne(array $where)
    {
        /** @var ResultSet $resultSet */
        $resultSet = $this->select($where);
        return $resultSet->current();
    }

    protected function selectAll(array $where = [])
    {
        /** @var ResultSet $resultSet */
        $resultSet = $this->select($where);
        return $resultSet->toArray();
    }

    protected function exists(array $where): bool
    {
        return $this->select($where)->count() > 0;
    }

    protected function countWhere(array $where): int
    {
        return $this->select($where)->count();
    }

    protected function selectOneOrNull(array $where)
    {
        $result = $this->selectOne($where);
        return $result ?: null; // Returns null if no record found
    }
}
