<?php
declare(strict_types=1);
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'This legacy product-upload endpoint is disabled. Please use /cnrbyte/marketplace/products/create.pge.php.';
