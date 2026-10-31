<?php
declare(strict_types=1);
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'This legacy endpoint is disabled. Run reviewed SQL migrations from includes/sql_tables/ using a database administration tool.';
