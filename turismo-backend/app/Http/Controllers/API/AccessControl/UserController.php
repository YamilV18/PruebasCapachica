<?php

namespace App\Http\Controllers\API\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Concerns\HandlesImages;


class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    use HandlesImages;

    public function index(Request $request)
    {
        // Add optional filtering
        $query = User::query();

        // Filter by active status if provided
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Filter by role if provided
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Allow search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Paginate results with eager loading of relationships
        $perPage = $request->get('per_page', 15);
        $users = $query->with(['roles.permissions', 'permissions', 'emprendimientos'])->paginate($perPage);

        // Transform the collection using the enhanced UserResource
        $users->setCollection(
            $users->getCollection()->map(function ($user) {
                return new UserResource($user);
            })
        );

        return response()->json([
            'success' => true,
            'data' => $users,
            'available_roles' => Role::all(['id', 'name']),
        ]);
    }
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (empty($query)) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $users = User::where('email', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->limit(10)
                    ->get();

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users)
        ]);
}
    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'active' => 'boolean',
            'country' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'preferred_language' => 'nullable|string|max:50',
            'foto_perfil' => 'nullable|image|max:5120', // 5MB max
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'active' => $request->has('active') ? $request->boolean('active') : true,
            'country' => $request->country,
            'birth_date' => $request->birth_date,
            'address' => $request->address,
            'gender' => $request->gender,
            'preferred_language' => $request->preferred_language,
        ];

        // Process profile photo if provided
        if ($request->hasFile('foto_perfil')) {
            $userData['foto_perfil'] = $this->storeImage($request->file('foto_perfil'), "usuarios/{$request->email}");
        }

        $user = User::create($userData);

        // Assign roles if provided
        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        } else {
            // Assign default user role if no roles specified
            $user->assignRole('user');
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => new UserResource($user->load('roles'))
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::with(['roles.permissions', 'permissions', 'emprendimientos'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'available_roles' => Role::all(['id', 'name']),
        ]);
    }
    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'active' => 'boolean',
            'country' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'preferred_language' => 'nullable|string|max:50',
            'foto_perfil' => 'nullable|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user data
        $userData = $request->only([
            'name', 'email', 'phone', 'active', 'country',
            'birth_date', 'address', 'gender', 'preferred_language'
        ]);

        // Update password if provided
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        // Process profile photo if provided
        if ($request->hasFile('foto_perfil')) {
            // Delete previous photo if exists
            $this->deleteImage($user->foto_perfil);
            $userData['foto_perfil'] = $this->storeImage($request->file('foto_perfil'), "usuarios/{$user->id}");
        }

        $user->update($userData);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => new UserResource($user->load('roles'))
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting admin users
        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar usuarios administradores'
            ], 403);
        }

        // Delete profile photo if exists
        if ($user->foto_perfil && !filter_var($user->foto_perfil, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($user->foto_perfil);
        }

        // Delete user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Activate a user.
     */
    public function activate($id)
    {
        $user = User::findOrFail($id);
        $user->update(['active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario activado exitosamente',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Deactivate a user.
     */
    public function deactivate($id)
    {
        $user = User::findOrFail($id);

        // Prevent deactivating admin users
        if ($user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede desactivar usuarios administradores'
            ], 403);
        }

        $user->update(['active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario desactivado exitosamente',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Assign roles to a user.
     */
    public function assignRoles(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $user->syncRoles($request->roles);

        return response()->json([
            'success' => true,
            'message' => 'Roles asignados exitosamente',
            'data' => [
                'user' => new UserResource($user),
                'roles' => $user->getRoleNames()
            ]
        ]);
    }

    /**
     * Upload or update user profile photo.
     */
    public function updateProfilePhoto(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'foto_perfil' => 'required|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);

        // Delete previous photo if exists
        $this->deleteImage($user->foto_perfil);
        $photoPath = $this->storeImage($request->file('foto_perfil'), "usuarios/{$user->id}");
        $user->update(['foto_perfil' => $photoPath]);

        return response()->json([
            'success' => true,
            'message' => 'Foto de perfil actualizada exitosamente',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Delete user profile photo.
     */
    public function deleteProfilePhoto($id)
    {
        $user = User::findOrFail($id);

        if (!$user->foto_perfil) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene foto de perfil'
            ], 400);
        }

        // Delete photo if it's not a URL
        $this->deleteImage($user->foto_perfil);

        $user->update(['foto_perfil' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Foto de perfil eliminada exitosamente',
            'data' => new UserResource($user)
        ]);
    }
}
