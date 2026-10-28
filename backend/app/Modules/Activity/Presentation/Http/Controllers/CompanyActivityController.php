<?php

namespace App\Modules\Activity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activity\Infrastructure\Persistence\Eloquent\CompanyActivityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $events = CompanyActivityEvent::query()
            ->with('actor:id,name')
            ->where('company_id', (int) $request->attributes->get('active_company_id'))
            ->latest('id')
            ->paginate($perPage);

        return response()->json($events);
    }
}
