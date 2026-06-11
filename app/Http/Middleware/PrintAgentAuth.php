<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintAgentAuth
{
    /**
     * Validar clave API del agente de impresión.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Agent-Key');

        if (empty($key) || $key !== config('app.print_agent_key')) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Revisá la configuración del agente.',
            ], 401);
        }

        return $next($request);
    }
}
