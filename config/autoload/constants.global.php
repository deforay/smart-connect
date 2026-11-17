<?php

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . "/../../"));

defined('WEB_ROOT')
    || define('WEB_ROOT', APPLICATION_PATH . DIRECTORY_SEPARATOR . 'public');

defined('UPLOAD_PATH')
    || define('UPLOAD_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'uploads');

defined('TEMP_UPLOAD_PATH')
    || define('TEMP_UPLOAD_PATH', WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary');

// Files the application writes for itself, outside the web root.
defined('VAR_PATH')
    || define('VAR_PATH', APPLICATION_PATH . DIRECTORY_SEPARATOR . 'var');

// Excel exports and result PDFs. They hold sample and patient data, so they
// are kept out of public/ and served only through /download/:file, which needs
// a login.
defined('EXPORT_PATH')
    || define('EXPORT_PATH', VAR_PATH . DIRECTORY_SEPARATOR . 'exports');

// Where AppLogger writes and the log viewer reads. Defined here so a deployment
// can point the logs at another volume without patching code.
defined('LOG_PATH')
    || define('LOG_PATH', APPLICATION_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'logs');

// returning this empty array to avoid error in config merging
return [];
