<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /** RF-USR-005: búsqueda por nombre o usuario y paginación fija. */
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $request->validate(['q' => 'nullable|string|max:100']);
        $users = User::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhere('username', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->orderBy('id')->paginate(10)->appends($request->only('q'));

        return view('users', compact('users'));
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('user-form', ['user' => null]);
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        return view('user-form', compact('user'));
    }

    public function save(Request $request, ?User $user = null)
    {
        $this->authorizeAdmin($request);
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => 'required|boolean',
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->numbers()],
        ], ['username.regex' => 'El nombre de usuario solo admite letras minúsculas sin acentos, números, punto, guion y guion bajo.', 'username.unique' => 'Ese nombre de usuario ya está registrado.']);
        $data['is_active'] = (bool) $data['is_active'];
        if ($user?->id === $request->user()->id && ($data['role'] !== 'ADMIN' || ! $data['is_active'])) {
            return back()->withInput()->withErrors(['role' => 'No puedes desactivar ni quitar el rol ADMIN de tu propia cuenta.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        if (! $user) {
            $user = User::create([...$data, 'created_by' => $request->user()->id]);
            Audit::record('user.created', "Alta de {$user->username} con rol {$user->role}", $user, ['role' => $user->role, 'is_active' => $user->is_active]);

            return redirect()->route('users.index')->with('status', 'Usuario creado.');
        }

        $before = $user->only('name', 'username', 'role', 'is_active');
        $user->update($data);
        $changes = array_diff_key($user->getChanges(), array_flip(['updated_at', 'password', 'remember_token']));
        if (isset($changes['role'])) {
            Audit::record('user.role_changed', "Rol de {$user->username}: {$before['role']} → {$user->role}", $user, ['from' => $before['role'], 'to' => $user->role]);
        }
        if (isset($changes['is_active'])) {
            Audit::record($user->is_active ? 'user.activated' : 'user.deactivated', ($user->is_active ? 'Activación de ' : 'Desactivación de ').$user->username, $user);
        }
        $other = array_diff_key($changes, array_flip(['role', 'is_active']));
        if ($other) {
            Audit::record('user.updated', "Modificación de {$user->username}", $user, ['fields' => array_keys($other), 'before' => array_intersect_key($before, $other)]);
        }
        if (isset($data['password'])) {
            Audit::record('user.password_reset', "Restablecimiento administrativo de contraseña de {$user->username}", $user);
        }
        // RF-USR-003: desactivar o restablecer la contraseña deja sin efecto las sesiones abiertas.
        if (! $user->is_active || isset($data['password'])) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()->route('users.index')->with('status', 'Usuario guardado.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}
