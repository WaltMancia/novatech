<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers;

use NovaTech\Core\{Request, Response};

/** [LAB-BASELINE] "Temporal", para depurar el eco de parámetros en desarrollo -- nunca se quitó. */
final class DebugController
{
    public function echo(Request $request): Response
    {
        $msg = $request->query('msg', '');
        // Refleja la entrada sin escapar y como text/html: XSS reflejado (CWE-79).
        return new Response(200, ['Content-Type' => 'text/html; charset=utf-8'], "<p>Echo: {$msg}</p>");
    }
}
