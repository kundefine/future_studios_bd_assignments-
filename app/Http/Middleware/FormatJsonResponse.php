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

            // Check if this looks like a Laravel pagination response
            if (isset($originalData['data']) && isset($originalData['current_page'])) {
                $items = $originalData['data'];
                unset($originalData['data']);

                // Flatten the data key so items and metadata sit side-by-side
                $originalData = array_merge($originalData, ['items' => $items]);
                // Or completely merge them at root: array_merge($originalData, $items)
            }

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
