<?php
declare(strict_types=1);
// Legacy roster editor replaced by the ownership-checked vendors/module_action.run.php workflow.
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
exit('This legacy HR endpoint has been retired. Use the current HR forms in the vendor dashboard.');
