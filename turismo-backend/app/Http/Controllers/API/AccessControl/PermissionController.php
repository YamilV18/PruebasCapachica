<?php

namespace App\Http\Controllers\API\AccessControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    /**
     * Display a listing of all permissions.
     */
    public function index()
    {
        $permissions = Permission::all();
        
        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Assign permissions to a user.
     */
    public function assignPermissionsToUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($request->user_id);
        $user->syncPermissions($request->permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully',
            'data' => [
                'user' => $user->only(['id', 'name', 'email']),
                'permissions' => $user->getAllPermissions()->pluck('name')
            ]
        ]);
    }

    /**
     * Get all permissions for a specific user.
     */
    public function getUserPermissions($id)
    {
        $user = User::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'name', 'email']),
                'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
                'permissions_via_roles' => $user->getPermissionsViaRoles()->pluck('name'),
                'all_permissions' => $user->getAllPermissions()->pluck('name')
            ]
        ]);
    }

    /**
     * Assign permissions to a role.
     */
    public function assignPermissionsToRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::findOrFail($request->role_id);
        $role->syncPermissions($request->permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to role successfully',
            'data' => [
                'role' => $role->only(['id', 'name']),
                'permissions' => $role->permissions->pluck('name')
            ]
        ]);
    }
}