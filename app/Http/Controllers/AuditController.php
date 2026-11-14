<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Trilha de auditoria das operações sensíveis do sistema.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = AuditLog::with(['user', 'institution'])->latest();

        // Salvaguarda no backend: usuários institucionais consultam apenas a própria Câmara,
        // independentemente do escopo global ou de parâmetros manipulados na requisição.
        if (! $user->isSuperAdmin()) {
            $query->where('institution_id', $user->institution_id);
        }

        if ($action = $request->input('action')) {
            $query->where('action', 'like', $action.'%');
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['action', 'user_id', 'from', 'to']),
        ]);
    }
}
