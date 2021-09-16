<?php

namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class HumanDateFormat extends AbstractHelper
{

    // $inputFormat = yyyy-mm-dd
    public function __invoke($date)
    {
        if ($date == null || $date == "" || $date == "0000-00-00" || $date == "0000-00-00 00:00:00") {
            return "";
        } else {
            $dateArray = explode('-', $date);
            $newDate =  "";

            $monthsArray = array(null, 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = $monthsArray[$dateArray[1] * 1];

            return $newDate .= $dateArray[2] . "-" . $mon . "-" . $dateArray[0];
        }
    }
}
