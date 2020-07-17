<?php

namespace Covid19\Model;

use Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Expression;
use \Application\Service\CommonService;
use Zend\Debug\Debug;

/**
 * Description of Countries
 *
 * @author amit
 * Description of Countries
 */
class Covid19FormTable extends AbstractTableGateway
{

    protected $table = 'dash_covid19_form';
    public $sm = null;
    public $config = null;
    protected $translator = null;

    public function __construct(Adapter $adapter, $sm = null)
    {
        $this->adapter = $adapter;
        $this->sm = $sm;
        $this->config = $this->sm->get('Config');
        $this->translator = $this->sm->get('translator');
    }

}
