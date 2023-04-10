<?php

namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class HumanReadableDateFormat extends AbstractHelper
{

    // $inputFormat = yyyy-mm-dd
    public function __invoke($date)
    {
        return \Application\Service\CommonService::humanReadableDateFormat($date);
    }
}
