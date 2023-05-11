<?php

namespace Application\View\Helper;

use Application\Service\CommonService;
use Laminas\View\Helper\AbstractHelper;

class HumanReadableDateFormat extends AbstractHelper
{

    // $inputFormat = yyyy-mm-dd
    public function __invoke($date, $includeTime = false, $dateFormat = "d-M-Y", $timeFormat = "H:i:s")
    {
        return CommonService::humanReadableDateFormat($date, $includeTime, $dateFormat, $timeFormat);
    }
}
