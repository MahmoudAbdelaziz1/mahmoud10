<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 

class UserController extends Controller
{
    /**
     * Get all users except the current user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Check if user is logged in
            if (!auth()->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must log in first'
                ], 401);
            }

           
            $query = User::where('id', '!=', auth()->id());

       
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }


            $users = $query->select([
                'id',
                'name',
                'email',
                'created_at',
                'updated_at'
            ])
            ->orderBy('name', 'asc') 
            ->get();

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users,
                'count' => $users->count(),
                'current_user_id' => auth()->id() 
            ]);

        } catch (\Exception $e) {
        
            Log::error('Error fetching users: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific user (optional)
     */
    public function show($id)
    {
        try {
            $user = User::where('id', $id)
                       ->where('id', '!=', auth()->id()) 
                       ->select(['id', 'name', 'email', 'created_at'])
                       ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'User data retrieved successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching user data'
            ], 500);
        }
    }
}