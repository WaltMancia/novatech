<?php
declare(strict_types=1);

use NovaTech\Core\Env;

return [
    // JSON por línea a stderr: Docker lo recoge y luego lo consume el SIEM (Fase 6).
    'stream' => Env::get('LOG_STREAM', 'php://stderr'),
];
