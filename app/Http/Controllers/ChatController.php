<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatUser;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
      
            $chats = Chat::with(['users' => function ($query) use ($user) {
                $query->where('users.id', '!=', $user->id)
                      ->select('users.id', 'users.name', 'users.email');
            }])
            ->whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->withCount(['messages as messages_count'])
            ->orderBy('updated_at', 'desc')
            ->get();
            
    
            foreach ($chats as $chat) {
                $lastMessage = $chat->messages()
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if ($lastMessage) {
                    $chat->last_message = [
                        'id' => $lastMessage->id,
                        'content' => $lastMessage->content,
                        'created_at' => $lastMessage->created_at,
                        'user_id' => $lastMessage->user_id
                    ];
                } else {
                    $chat->last_message = null;
                }
                
              
                if ($chat->type === 'private' && empty($chat->name)) {
                    $otherUser = $chat->users->first();
                    $chat->name = $otherUser ? $otherUser->name : 'Private Chat';
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $chats,
                'message' => 'Chats retrieved successfully',
                'count' => $chats->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching chats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching chats',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            Log::info('Creating chat request:', $request->all());
            
      
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:private,group',
                'name' => 'nullable|string|min:2|max:100',
                'users' => 'required|array|min:1',
                'users.*' => 'required|exists:users,id|distinct'
            ], [
                'users.required' => 'At least one user must be selected',
                'users.min' => 'At least one user must be selected',
                'type.in' => 'Chat type must be private or group',
                'users.*.exists' => 'The selected user does not exist',
                'name.min' => 'Group name must be at least 2 characters'
            ]);
            
            if ($validator->fails()) {
                Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid data',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = $request->user();
            $userIds = $request->users;
            
       
            if (in_array($user->id, $userIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot add yourself to the chat'
                ], 422);
            }
            
       
            if ($request->type === 'private') {
                if (count($userIds) !== 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Private chat requires only one user'
                    ], 422);
                }
                
           
                $existingChat = Chat::where('type', 'private')
                    ->whereHas('users', function ($query) use ($user, $userIds) {
                        $query->where('user_id', $user->id);
                    })
                    ->whereHas('users', function ($query) use ($userIds) {
                        $query->where('user_id', $userIds[0]);
                    })
                    ->first();
                
                if ($existingChat) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Chat already exists',
                        'data' => $existingChat->load(['users' => function ($query) use ($user) {
                            $query->where('users.id', '!=', $user->id);
                        }])
                    ]);
                }
            }
            
            DB::beginTransaction();
      
            $chat = Chat::create([
                'type' => $request->type,
                'name' => $request->name,
                'created_by' => $user->id
            ]);
            
           
            $allParticipants = array_merge([$user->id], $userIds);
            
            foreach ($allParticipants as $participantId) {
                ChatUser::create([
                    'chat_id' => $chat->id,
                    'user_id' => $participantId
                ]);
            }
            
            DB::commit();

            $chat->load(['users' => function ($query) use ($user) {
                $query->where('users.id', '!=', $user->id)
                      ->select('users.id', 'users.name', 'users.email');
            }]);
            

            if ($chat->type === 'private' && empty($chat->name)) {
                $otherUser = $chat->users->first();
                $chat->name = $otherUser ? $otherUser->name : 'Private Chat';
            }
            
            Log::info('Chat created successfully:', ['chat_id' => $chat->id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Chat created successfully',
                'data' => $chat
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating chat: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the chat',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }


    public function show($chatId)
    {
        try {
            $user = Auth::user();
            
            $chat = Chat::with(['users' => function ($query) use ($user) {
                $query->where('users.id', '!=', $user->id)
                      ->select('users.id', 'users.name', 'users.email');
            }])
            ->whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->find($chatId);
            
            if (!$chat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat not found or you do not have access'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $chat,
                'message' => 'Chat retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching chat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the chat'
            ], 500);
        }
    }


    public function createChat(Request $request)
    {
        return $this->store($request);
    }
}