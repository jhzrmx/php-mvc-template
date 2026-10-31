<?php

// Front controller. This is the ONLY directory that should be web-accessible.
define('BASE_PATH', dirname(__DIR__));
chdir(BASE_PATH);

require BASE_PATH . '/bootstrap.php';
