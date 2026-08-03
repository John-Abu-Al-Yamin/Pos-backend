<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min(
            max((int) $request->input('per_page', 25), 1),
            (int) config('audit.max_per_page', 100)
        );

        $logs = $this->filteredQuery($request)
            ->with('user:id,name,email,role')
            ->latest('occurred_at')
            ->paginate($perPage);

        return ApiResponse::success(
            data: $logs,
            message: 'Audit logs fetched successfully'
        );
    }

    public function show(AuditLog $auditLog)
    {
        return ApiResponse::success(
            data: $auditLog->load('user:id,name,email,role'),
            message: 'Audit log fetched successfully'
        );
    }

    public function related(AuditLog $auditLog)
    {
        $logs = AuditLog::query()
            ->when($auditLog->batch_uuid, fn (Builder $query) => $query->where('batch_uuid', $auditLog->batch_uuid))
            ->when(! $auditLog->batch_uuid && $auditLog->auditable_type && $auditLog->auditable_id, function (Builder $query) use ($auditLog) {
                $query->where('auditable_type', $auditLog->auditable_type)
                    ->where('auditable_id', $auditLog->auditable_id);
            })
            ->latest('occurred_at')
            ->limit(100)
            ->get();

        return ApiResponse::success(
            data: $logs,
            message: 'Related audit logs fetched successfully'
        );
    }

    public function stats(Request $request)
    {
        $query = $this->filteredQuery($request, defaultDateWindow: true);

        return ApiResponse::success(
            data: [
                'total' => (clone $query)->count(),
                'critical' => (clone $query)->where('severity', 'critical')->count(),
                'failed' => (clone $query)->where('status', 'failed')->count(),
                'by_module' => (clone $query)
                    ->selectRaw('module, count(*) as total')
                    ->groupBy('module')
                    ->orderByDesc('total')
                    ->get(),
                'by_action' => (clone $query)
                    ->selectRaw('action, count(*) as total')
                    ->groupBy('action')
                    ->orderByDesc('total')
                    ->limit(20)
                    ->get(),
            ],
            message: 'Audit log stats fetched successfully'
        );
    }

    public function filters()
    {
        return ApiResponse::success(
            data: [
                'users' => User::query()
                    ->select('id', 'name', 'email')
                    ->orderBy('name')
                    ->get(),
            ],
            message: 'Audit log filters fetched successfully'
        );
    }

    private function filteredQuery(Request $request, bool $defaultDateWindow = false): Builder
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : ($defaultDateWindow ? now()->subDays(30)->startOfDay() : null);

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : null;

        return AuditLog::query()
            ->when($request->filled('user_id'), fn (Builder $query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('actions'), fn (Builder $query) => $query->whereIn('action', (array) $request->input('actions')))
            ->when($request->filled('ip_address'), fn (Builder $query) => $query->where('ip_address', $request->input('ip_address')))
            ->when($request->filled('auditable_type'), fn (Builder $query) => $query->where('auditable_type', $request->input('auditable_type')))
            ->when($request->filled('auditable_id'), fn (Builder $query) => $query->where('auditable_id', $request->input('auditable_id')))
            ->when($request->filled('request_id'), fn (Builder $query) => $query->where('request_id', $request->input('request_id')))
            ->when($request->filled('batch_uuid'), fn (Builder $query) => $query->where('batch_uuid', $request->input('batch_uuid')))
            ->when($from, fn (Builder $query) => $query->where('occurred_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('occurred_at', '<=', $to))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = trim((string) $request->input('search'));

                if (mb_strlen($term) < 3) {
                    return;
                }

                $search = $term.'%';

                $query->where(function (Builder $query) use ($search) {
                    $query->where('user_name', 'like', $search)
                        ->orWhere('user_email', 'like', $search)
                        ->orWhere('auditable_label', 'like', $search)
                        ->orWhere('ip_address', 'like', $search)
                        ->orWhere('route', 'like', $search)
                        ->orWhere('request_id', 'like', $search);
                });
            });
    }
}
