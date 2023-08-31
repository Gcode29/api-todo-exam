<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskTags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            ->when(request()->has('q'), fn ($query) =>
                $query->where('title', 'like', '%'. request()->input('q') . '%')
                    ->orWhere('description', 'like', '%'. request()->input('q') . '%')
            )
            ->when(request()->has('tag'), fn ($query) =>
                $query->whereHas('tags', fn ($query) =>
                    $query->where('tag_id', request()->input('tag'))
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
            ->paginate(2);

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
        Log::info('update');
        $task->update($request->validated());

        return new TaskResource($task);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $task->forceDelete();

        return response()->noContent();
    }

    public function complete(Request $request)
    {   
        Log::info('complete');
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
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function uncomplete(Request $request)
    {   
        Log::info('uncomplete');
        $request->validate([
            'id' => 'required',
        ]);

        try {
            $taskId = $request->input('id');
            $task = Task::where('user_id', auth()->user()->id)
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
        Log::info('archive');
        $user = auth()->user();
        
        if ($task->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $task->delete();

        return response()->json(['success' => 'OK'], 200);
    }

    public function restore(Task $task)
    {   
        Log::info('restoring');
        $user = auth()->user();

        if ($task->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $task->restore();

        return response()->json(['success' => 'OK'], 200);
    }
}
