<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Listing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** RF-AUD-002: consulta del historial global; solo ADMIN, con filtros y paginación. Sin operaciones de modificación. */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate([
            'user_id' => 'nullable|integer',
            'action' => ['nullable', Rule::in(array_keys(AuditEvent::ACTIONS))],
            'result' => ['nullable', Rule::in(array_keys(AuditEvent::RESULTS))],
            'resource_type' => ['nullable', Rule::in(array_keys(AuditEvent::RESOURCES))],
            'resource_id' => 'nullable|integer',
            'expediente_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => Listing::perPageRule(),
        ]);
        $events = AuditEvent::with('user')
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->result))
            ->when($request->filled('resource_type'), fn ($q) => $q->where('resource_type', $request->resource_type))
            ->when($request->filled('resource_id'), fn ($q) => $q->where('resource_id', $request->resource_id))
            ->when($request->filled('expediente_id'), fn ($q) => $q->where('expediente_id', $request->expediente_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('id')->paginate(Listing::perPage($request))->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name', 'username']);

        return view('audit', compact('events', 'users'));
    }
}
