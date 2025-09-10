<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Shared\Components\Controller;

class PermissionController extends Controller
{
    /**
     * Display a listing of the permissions.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::all();
        
        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => 'api',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $permission,
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission,
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
        ]);
    }

    /**
     * Get permissions for a specific role.
     */
    public function getByRole($roleId): JsonResponse
    {
        $role = \Spatie\Permission\Models\Role::findOrFail($roleId);
        $permissions = $role->permissions;

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Get permissions for a specific user.
     */
    public function getByUser($userId): JsonResponse
    {
        $user = \Shared\Models\User::findOrFail($userId);
        $permissions = $user->getAllPermissions();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}