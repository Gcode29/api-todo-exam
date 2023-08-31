<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTags extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'tag_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }
}
