<?php

namespace Application\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;
use Laminas\Db\TableGateway\AbstractTableGateway;


class UserOrganizationsMapTable extends AbstractTableGateway
{

    protected $table = 'user_organization_map';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }


    public function fetchOrganizations($userId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('uom' => 'user_organization_map'))
            ->where(array('user_id' => $userId));

        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }



    public function fetchUsers($orgId)
    {
        $dbAdapter = $this->adapter;
        $sql = new Sql($dbAdapter);
        $sQuery = $sql->select()->from(array('uom' => 'user_organization_map'))
            ->where(array('organization_id' => $orgId));

        $sQueryStr = $sql->buildSqlString($sQuery);
        return $dbAdapter->query($sQueryStr, $dbAdapter::QUERY_MODE_EXECUTE)->toArray();
    }


    public function mapUserOrganizations($params)
    {

        $this->delete(array('user_id' => $params['userId']));

        $credoContainer = new Container('credo');

        foreach ($params['facilities'] as $facilityId) {
            $this->insert(array('user_id' => $params['userId'], 'organization_id' => $facilityId));
        }
    }




    public function mapOrganizationToUsers($params)
    {

        $this->delete(array('organization_id' => $params['orgId']));

        $credoContainer = new Container('credo');

        foreach ($params['users'] as $userId) {
            $this->insert(array('organization_id' => $params['orgId'], 'user_id' => $userId));
        }
    }
}
