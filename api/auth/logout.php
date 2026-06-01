<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/response.php';
require_once __DIR__ . '/../../lib/session.php';

require_method('POST');

logout_user();

ok_response();

