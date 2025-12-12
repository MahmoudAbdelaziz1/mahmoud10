<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index($chatId)
    {
        // Use 'users' in lowercase
        $chat = Chat::with('users')->find($chatId);
        
        if (!$chat) {
            return response()->json([
                'message' => 'Chat not found'
            ], 404);
        }
        
        // Use 'users' in lowercase
        if (!$chat->users->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not authorized to access this chat'
            ], 403);
        }
        
        $messages = Message::where('chat_id', $chatId)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();
            
        return response()->json([
            'chat' => $chat,
            'messages' => $messages
        ]);
    }

    public function store(Request $request, $chatId)
    {

        $chat = Chat::with('users')->find($chatId);
        
        if (!$chat) {
            return response()->json([
                'message' => 'Chat not found'
            ], 404);
        }
        

        if (!$chat->users->contains(Auth::id())) {
            return response()->json([
                'message' => 'You are not authorized to send messages in this chat'
            ], 403);
        }
        

        $field = $request->has('content') ? 'content' : 'message';
        
        $data = $request->validate([
            $field => 'required|string|max:1000'
        ]);
        
        try {
   
            $msg = Message::create([
                'chat_id' => $chatId,
                'user_id' => Auth::id(),
                'message' => $data[$field], 
            ]);
            

            $msg->load('user');
            
            return response()->json([
                'message' => 'Message sent successfully',
                'data' => $msg
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while sending the message',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
