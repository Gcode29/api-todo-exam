<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTags extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'tag_id',
    ];

    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
