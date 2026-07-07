export const PANEL_WEEKLY_TASKS_KEY = 'panel-weekly-tasks';

const PREVIEW_TASK_LIMIT = 3;

function readRaw(storageKey = PANEL_WEEKLY_TASKS_KEY) {
    try {
        const raw = localStorage.getItem(storageKey);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function normalizeTask(task, fallbackSource = 'roadmap') {
    return {
        id: task.id,
        title: task.title ?? '',
        done: Boolean(task.done),
        note: task.note ?? '',
        source: task.source ?? fallbackSource,
        showNote: Boolean(task.showNote),
    };
}

export const WeeklyTasksStore = {
    load(seedTasks, storageKey = PANEL_WEEKLY_TASKS_KEY) {
        const stored = readRaw(storageKey);
        if (Array.isArray(stored) && stored.length) {
            return stored.map((task) => normalizeTask(task));
        }

        return seedTasks.map((task) => normalizeTask(task, 'roadmap'));
    },

    save(tasks, storageKey = PANEL_WEEKLY_TASKS_KEY) {
        const payload = tasks.map(({ id, title, done, note, source }) => ({
            id,
            title,
            done,
            note,
            source,
        }));
        localStorage.setItem(storageKey, JSON.stringify(payload));
    },
};

export function dashboardWeeklyPlan(seedTasks, career, labels, storageKey = PANEL_WEEKLY_TASKS_KEY) {
    return {
        tasks: [],
        career,
        labels,
        newTaskTitle: '',

        init() {
            this.tasks = WeeklyTasksStore.load(seedTasks, storageKey);
        },

        get doneCount() {
            return this.tasks.filter((task) => task.done).length;
        },

        get totalCount() {
            return this.tasks.length;
        },

        get readiness() {
            if (!this.totalCount) {
                return 0;
            }

            return Math.round((this.doneCount / this.totalCount) * 100);
        },

        get tasksCountLabel() {
            return (this.labels.tasks_count || '')
                .replace(':done', String(this.doneCount))
                .replace(':total', String(this.totalCount));
        },

        get previewTasks() {
            return this.tasks.filter((task) => !task.done).slice(0, PREVIEW_TASK_LIMIT);
        },

        get previewEmptyMessage() {
            if (this.tasks.length > 0 && this.previewTasks.length === 0) {
                return this.labels.tasks_all_done || this.labels.tasks_empty;
            }

            return this.labels.tasks_empty;
        },

        persist() {
            WeeklyTasksStore.save(this.tasks, storageKey);
        },

        reorderTasks() {
            const pending = this.tasks.filter((task) => !task.done);
            const done = this.tasks.filter((task) => task.done);
            this.tasks = [...pending, ...done];
        },

        toggleTask(task) {
            task.done = !task.done;
            this.reorderTasks();
            this.persist();
        },

        saveNote(task) {
            this.persist();
        },

        toggleNote(task) {
            task.showNote = !task.showNote;
        },

        addTask() {
            const title = this.newTaskTitle.trim();
            if (!title) {
                return;
            }

            this.tasks.push({
                id: `custom-${Date.now()}`,
                title,
                done: false,
                note: '',
                source: 'custom',
                showNote: false,
            });
            this.newTaskTitle = '';
            this.persist();
        },

        removeTask(task) {
            if (task.source !== 'custom') {
                return;
            }

            this.tasks = this.tasks.filter((item) => item.id !== task.id);
            this.persist();
        },
    };
}
