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
        return $db->fetchAllDashTrackApiRequestsByGrid($parameters);
    }
    
    public function getSyncHistoryType()
    {
        $db = $this->sm->get('DashTrackApiRequestsTable');
        return $db->fetchSyncHistoryType();
    }
}
