<?php

namespace Application\View\Helper;

use Application\Service\CommonService;
use Laminas\View\Helper\AbstractHelper;

class HumanReadableDateFormat extends AbstractHelper
{

    // $inputFormat = yyyy-mm-dd
    public function __invoke($date, $includeTime = false, $format = "d-M-Y")
    {
        return CommonService::humanReadableDateFormat($date, $includeTime, $format);
    }
}
