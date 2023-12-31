<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\Task_tags;
use Illuminate\Http\Request;
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
        $completedTasks = QueryBuilder::for(Task::class)
            ->allowedFilters([
                AllowedFilter::partial('title'),
            ])
            ->where('is_completed', 1)
            ->withCount('attachments')
            ->with('tags', 'attachments')
            ->paginate(5);

        $uncompletedTasks = QueryBuilder::for(Task::class)
            ->allowedFilters([
                AllowedFilter::partial('title'),
            ])
            ->where('is_completed', 0)
            ->withCount('attachments')
            ->with('tags', 'attachments')
            ->paginate(5);

        return [
            'completedTasks' => TaskResource::collection($completedTasks),
            'uncompletedTasks' => TaskResource::collection($uncompletedTasks),
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

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
                $tags = new Task_tags();
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
        $task->update($request->validated());

        return new TaskResource($task);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $task->delete();

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
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function uncomplete(Request $request)
    {
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
}
