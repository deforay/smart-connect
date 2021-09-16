<?php

namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class GetLocaleData extends AbstractHelper
{

    private $globalTable;
    public function __construct($globalTable)
    {
        $this->globalTable = $globalTable;
    }

    public function __invoke($column, $localeId)
    {
        return $this->globalTable->fetchLocaleDetailsById($column, $localeId);
    }
}
