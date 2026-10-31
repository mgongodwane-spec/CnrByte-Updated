<?php
declare(strict_types=1);
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'This legacy statement-edit endpoint is disabled. Statement records must be updated through the maintained financial workflow.';
