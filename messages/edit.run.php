<?php
declare(strict_types=1);
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'This legacy message-edit endpoint is disabled. Use the Messages dashboard to send or reply to messages.';
