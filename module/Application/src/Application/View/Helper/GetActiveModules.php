<?php

namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class GetActiveModules extends AbstractHelper
{

    private $config;
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function __invoke()
    {
        return [
            'vl' => $this->config['defaults']['vlModule'],
            'eid' => $this->config['defaults']['eidModule'],
            'poc' => $this->config['defaults']['pocDashboard']
        ];
    }
}
