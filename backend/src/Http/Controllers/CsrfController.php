<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers;

use NovaTech\Core\Security\CsrfTokenManager;
use NovaTech\Core\{Request, Response};

final class CsrfController
{
    public function __construct(private readonly CsrfTokenManager $csrf)
    {
    }

    public function show(Request $request): Response
    {
        return Response::json(['csrfToken' => $this->csrf->token()]);
    }
}
