@php($isPreview = ($mode ?? 'full') === 'preview')

@if (! $isPreview)
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <p class="panel-muted text-xs" x-text="tasksCountLabel" x-cloak></p>
    </div>
@endif

<ul class="space-y-3" x-show="{{ $isPreview ? 'previewTasks.length' : 'tasks.length' }}" x-cloak>
    <template x-for="task in {{ $isPreview ? 'previewTasks' : 'tasks' }}" :key="task.id">
        <li class="panel-card p-4">
            <div class="flex items-start gap-3">
                <button type="button" @click="toggleTask(task)"
                    class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border transition"
                    :class="task.done ? 'border-emerald-500 bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'border-slate-400 dark:border-slate-600'">
                    <svg x-show="task.done" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <p class="font-medium"
                        :class="task.done ? 'text-slate-500 line-through' : 'text-slate-800 dark:text-slate-200'"
                        x-text="task.title"></p>
                    @unless ($isPreview)
                        <p x-show="task.note && !task.showNote"
                            class="panel-muted mt-1 text-xs italic"
                            x-text="task.note"></p>
                        <button type="button" @click="toggleNote(task)"
                            class="mt-1 text-xs text-emerald-600 hover:underline dark:text-emerald-400"
                            x-text="task.note || task.showNote ? labels.tasks_note : labels.tasks_note_add"></button>
                    @else
                        <p x-show="task.note"
                            class="panel-muted mt-1 text-xs italic line-clamp-2"
                            x-text="task.note"></p>
                    @endunless
                </div>
                @unless ($isPreview)
                    <button type="button" x-show="task.source === 'custom'" @click="removeTask(task)"
                        class="shrink-0 text-xs text-slate-400 hover:text-red-500"
                        x-text="labels.tasks_delete"></button>
                @endunless
            </div>
            @unless ($isPreview)
                <div x-show="task.showNote" x-cloak class="mt-3 pl-8">
                    <label class="panel-muted mb-1 block text-xs" x-text="labels.tasks_note"></label>
                    <textarea rows="2" x-model="task.note" @blur="saveNote(task)"
                        :placeholder="labels.tasks_note_placeholder"
                        class="panel-input-block text-sm"></textarea>
                </div>
            @endunless
        </li>
    </template>
</ul>

<p x-show="{{ $isPreview ? '!previewTasks.length' : '!tasks.length' }}" x-cloak
    class="panel-card mb-4 border-dashed p-6 text-center text-sm text-slate-500"
    x-text="{{ $isPreview ? 'previewEmptyMessage' : 'labels.tasks_empty' }}"></p>

@unless ($isPreview)
    <form class="panel-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center" @submit.prevent="addTask()">
        <input type="text" x-model="newTaskTitle" :placeholder="labels.tasks_add_placeholder"
            class="panel-input-block flex-1 text-sm">
        <button type="submit"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500"
            x-text="labels.tasks_add"></button>
    </form>
@endunless
