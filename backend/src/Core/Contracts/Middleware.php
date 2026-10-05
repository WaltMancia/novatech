<?php
declare(strict_types=1);

namespace NovaTech\Core\Contracts;

use NovaTech\Core\{Request, Response};

interface Middleware
{
    /** @param callable(Request): Response $next */
    public function handle(Request $request, callable $next): Response;
}
