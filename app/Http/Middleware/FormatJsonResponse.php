<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FormatJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof JsonResponse) {
            $originalData = $response->getData(true);
            $formattedData = [
                'success' => $response->isSuccessful(),
                'status'  => $response->getStatusCode(),
                'data'    => $originalData,
            ];
            $response->setData($formattedData);
        }
        return $response;
    }
}
