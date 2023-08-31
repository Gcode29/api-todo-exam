<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'due_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'task_id')->whereNotNull('path');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'task_tags', 'task_id', 'tag_id');
    }
}
