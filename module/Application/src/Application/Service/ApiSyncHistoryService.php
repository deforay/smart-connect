<?php

namespace Application\Service;

use Laminas\Session\Container;

class ApiSyncHistoryService
{

    public $sm = null;

    public function __construct($sm)
    {
        $this->sm = $sm;
    }

    public function getAllDashTrackApiRequestsByGrid($parameters)
    {
        $db = $this->sm->get('DashTrackApiRequestsTable');
        $acl = $this->sm->get('AppAcl');
        return $db->fetchAllDashTrackApiRequestsByGrid($parameters, $acl);
    }
    
    public function getSyncHistoryType()
    {
        $db = $this->sm->get('DashTrackApiRequestsTable');
        return $db->fetchSyncHistoryType();
    }
    
    public function getSyncHistoryById($id)
    {
        $db = $this->sm->get('DashTrackApiRequestsTable');
        return $db->fetchSyncHistoryById($id);
    }
}
