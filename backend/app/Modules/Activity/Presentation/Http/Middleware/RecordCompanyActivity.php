<?php

namespace App\Modules\Activity\Presentation\Http\Middleware;

use App\Modules\Activity\Application\Services\RecordCompanyActivity as RecordActivity;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordCompanyActivity
{
    public function __construct(private RecordActivity $recorder) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response = $next($request);

        if (
            ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || ! $response->isSuccessful()
        ) {
            return $response;
        }

        $route = $request->route();
        $companyId = $request->attributes->get('active_company_id')
            ?? $route?->parameter('company');
        $responseData = $response instanceof JsonResponse
            ? $response->getData(true)
            : [];
        if (! $companyId && $request->isMethod('post') && $request->is('api/v1/companies')) {
            $companyId = $responseData['data']['id'] ?? null;
        }

        if (! is_numeric($companyId) || (int) $companyId < 1) {
            return $response;
        }

        $routeUri = $route?->uri() ?? $request->path();
        $parameters = $route?->parameters() ?? [];
        $subjectId = null;
        foreach (['category', 'product', 'member', 'sale', 'session', 'plan'] as $key) {
            if (isset($parameters[$key])) {
                $subjectId = (string) $parameters[$key];
                break;
            }
        }
        $subjectId ??= isset($responseData['data']['id'])
            ? (string) $responseData['data']['id']
            : null;

        $details = [
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'fields' => implode(', ', array_keys($request->except([
                'password',
                'password_confirmation',
                'access_token',
                'refresh_token',
                'token',
            ]))),
        ];
        if ($request->has('is_active')) {
            $details['is_active'] = $request->boolean('is_active');
        }

        $this->recorder->execute(
            (int) $companyId,
            $request->user()?->getKey(),
            $this->action($request, $routeUri),
            $routeUri,
            $subjectId,
            $details
        );

        return $response;
    }

    private function action(Request $request, string $routeUri): string
    {
        if (str_ends_with($routeUri, '/void')) {
            return 'sale.voided';
        }

        if (str_ends_with($routeUri, '/returns')) {
            return 'sale.returned';
        }

        if (str_ends_with($routeUri, '/close')) {
            return 'cash_session.closed';
        }

        if (str_ends_with($routeUri, '/status')) {
            return $request->boolean('is_active')
                ? 'record.activated'
                : 'record.deactivated';
        }

        if (
            $request->has('is_active')
            && count($request->except([
                'password',
                'password_confirmation',
                'access_token',
                'refresh_token',
                'token',
            ])) === 1
        ) {
            return $request->boolean('is_active')
                ? 'record.activated'
                : 'record.deactivated';
        }

        if ($request->isMethod('delete') && str_contains($routeUri, '/members/')) {
            return 'record.deactivated';
        }

        return match ($request->method()) {
            'POST' => 'record.created',
            'PUT', 'PATCH' => 'record.updated',
            'DELETE' => 'record.deleted',
            default => 'record.changed',
        };
    }
}
