<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;


    protected $fillable = [
        'chat_id',
        'user_id',
        'message', 
    ];
    
    

    public function getMessageAttribute()
    {
        return $this->attributes['message'] ?? null;
    }
    
    public function setMessageAttribute($value)
    {
        $this->attributes['message'] = $value;
    }


    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}