<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskTags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tasks = QueryBuilder::for(Task::class)
            ->where('user_id', auth()->user()->id)
            ->when(request()->has('q'), fn ($query) => $query->where('title', 'like', '%'.request()->input('q').'%')
                ->orWhere('description', 'like', '%'.request()->input('q').'%')
            )
            ->when(request()->has('tag'), fn ($query) => $query->whereHas('tags', fn ($query) => $query->where('tag_id', request()->input('tag'))
            )
            )
            ->allowedFilters([
                AllowedFilter::exact('is_completed'),
                AllowedFilter::exact('priority'),
                AllowedFilter::trashed(),
            ])
            ->allowedSorts(['title', 'description', 'is_completed', 'due_date', 'created_at', 'time_completed'])
            ->withCount('attachments')
            ->with('tags', 'attachments')
            ->paginate(5);

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TaskRequest $request)
    {
        $validatedData = $request->validated();

        $task = new Task();
        $task->title = $validatedData['title'];
        $task->description = $validatedData['description'];

        if ($request->has('priority')) {
            $task->priority = $request->input('priority');
        }

        if ($request->has('due_date')) {
            $task->due_date = $request->input('due_date');
        }

        $task->user_id = auth()->user()->id;
        $task->save();

        if ($request->hasFile('uploadedFiles')) {
            foreach ($request->file('uploadedFiles') as $image) {
                error_log('Processing public image: '.$image->getClientOriginalName());
                $filename = Str::random(40).'.'.$image->getClientOriginalExtension();
                $path = $image->storeAs('files', $filename, 'public');
                $attachment = new Attachment();
                $attachment->filename = $filename;
                $attachment->path = 'storage/'.$path;
                $attachment->task_id = $task->id;
                $attachment->user_id = auth()->user()->id;
                $attachment->save();
            }
        }

        if ($request->has('selectedTags')) {
            foreach ($request->input('selectedTags') as $tagId) {
                $tags = new TaskTags();
                $tags->user_id = auth()->user()->id;
                $tags->task_id = $task->id;
                $tags->tag_id = $tagId;
                $tags->save();
            }
        }

        return response()->json(['message' => 'Task created successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TaskRequest $request, Task $task)
    {
        $validatedData = $request->validated();

        $task->title = $validatedData['title'];
        $task->description = $validatedData['description'];

        if ($request->has('priority')) {
            $task->priority = $request->input('priority');
        } else {
            $task->priority = null;
        }

        if ($request->has('due_date')) {
            $task->due_date = $request->input('due_date');
        } else {
            $task->due_date = null;
        }

        $task->user_id = auth()->user()->id;
        $task->save();

        // Update attachments (if provided)
        if ($request->hasFile('uploadedFiles')) {
            foreach ($request->file('uploadedFiles') as $image) {
                $filename = Str::random(40).'.'.$image->getClientOriginalExtension();
                $path = $image->storeAs('files', $filename, 'public');
                $attachment = new Attachment();
                $attachment->filename = $filename;
                $attachment->path = 'storage/'.$path;
                $attachment->task_id = $task->id;
                $attachment->user_id = auth()->user()->id;
                $attachment->save();
            }
        }

        // Update task tags
        if ($request->has('selectedTags')) {
            // Delete existing task tags for this user and task
            TaskTags::where('task_id', $task->id)
                ->where('user_id', auth()->user()->id)
                ->delete();

            // Attach the selected tags with the user_id
            foreach ($request->input('selectedTags') as $tagId) {
                $taskTag = new TaskTags();
                $taskTag->task_id = $task->id;
                $taskTag->tag_id = $tagId;
                $taskTag->user_id = auth()->user()->id;
                $taskTag->save();
            }
        }

        return response()->json(['message' => 'Task updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        if ($task->user_id !== auth()->user()->id) {
            return response()->json([
                'message' => 'Unauthorized Access'
            ], 401);
        }

        $task->forceDelete();

        return response()->noContent();
    }

    public function complete(Request $request)
    {
        $request->validate([
            'id' => 'required',
        ]);

        try {
            $taskId = $request->input('id');
            $task = Task::where('user_id', auth()->user()->id)
                ->findOrFail($taskId);

            $task->is_completed = 1;
            $task->time_completed = now();
            $task->save();

            return response()->json(['success' => 'OK'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }

    public function uncomplete(Request $request)
    {
        $request->validate([
            'id' => 'required',
        ]);

        try {
            $taskId = $request->input('id');
            $task = Task::where('user_id', auth()->user->id)
                ->findOrFail($taskId);

            $task->is_completed = 0;
            $task->time_completed = null;
            $task->save();

            return response()->json(['success' => 'OK'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function archive(Task $task)
    {
        if ($task->user_id !== auth()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $task->delete();
            return response()->json(['success' => 'OK'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function restore(int $id)
    {
        $task = Task::withTrashed()
            ->findOrFail($id);

        if ($task->user_id !== auth()->user->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $task->restore();

        return response()->json(['success' => 'OK'], 200);
    }

    public function deleteAttachment(Request $request, $taskId, $attachmentId)
    {
        $task = Task::findOrFail($taskId);
        $attachment = $task->attachments()->findOrFail($attachmentId);

        if ($attachment->user_id !== auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Storage::delete('public/'.$attachment->filename);

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted']);
    }
}
