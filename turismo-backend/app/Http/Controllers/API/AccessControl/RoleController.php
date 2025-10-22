<?php

namespace App\Http\Controllers\API\AccessControl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        
        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name',
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

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web'
        ]);
        $role->syncPermissions($request->permissions);
        
        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role->load('permissions')
        ], 201);
    }

    
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }
    
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name,'.$role->id,
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
        
        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);
        
        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions')
        ]);
    }
    
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        
        // Prevenir eliminación de roles default
        if (in_array($role->name, ['admin', 'user'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default roles'
            ], 403);
        }
        
        $role->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}