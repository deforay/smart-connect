<?php

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . "/../../"));

defined('WEB_ROOT')
    || define('WEB_ROOT', APPLICATION_PATH . DIRECTORY_SEPARATOR . 'public');

defined('UPLOAD_PATH')
    || define('UPLOAD_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads');

defined('TEMP_UPLOAD_PATH')
    || define('TEMP_UPLOAD_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary');

// returning this empty array to avoid error in config merging
return [];
