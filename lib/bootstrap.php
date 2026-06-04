<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/input.php';
require_once __DIR__ . '/auth.php';

set_exception_handler('jsonUnexpectedError');

