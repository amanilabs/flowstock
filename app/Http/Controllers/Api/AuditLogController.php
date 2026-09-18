<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    /** Requires the view-audit-logs permission. */
    #[QueryParameter('subject_type', description: 'Filter by fully-qualified subject model class.', type: 'string')]
    #[QueryParameter('log_name', description: 'Filter by log name (e.g. "auth", "default").', type: 'string')]
    #[QueryParameter('event', description: 'Filter by event (e.g. "created", "updated", "deleted").', type: 'string')]
    #[QueryParameter('causer_search', description: 'Match against the causer\'s name or email.', type: 'string')]
    #[QueryParameter('from', description: 'Only logs on/after this date (Y-m-d).', type: 'string')]
    #[QueryParameter('to', description: 'Only logs on/before this date (Y-m-d).', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page.', type: 'int', default: 15, example: 25)]
    public function index(Request $request)
    {
        $logs = Activity::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with('causer')
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('log_name'), fn ($q) => $q->where('log_name', $request->string('log_name')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($request->filled('causer_search'), function ($q) use ($request) {
                $search = $request->string('causer_search');
                $q->whereHasMorph('causer', [User::class], fn ($q2) => $q2
                    ->where(fn ($q3) => $q3
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return AuditLogResource::collection($logs);
    }
}
