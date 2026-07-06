@extends('app.layouts.app')

@section('title', __('panel.chat.title'))

@section('content')
<div class="mx-auto max-w-4xl" x-data="{ prompts: {{ Js::from($assistant['prompts']) }}, messages: [], text: '', ask(prompt) { this.messages.push({ from: 'user', body: prompt.q }); this.messages.push({ from: 'bot', body: prompt.a }); }, send() { if (!this.text.trim()) return; const q = this.text; this.text = ''; const hit = this.prompts.find(p => q.toLowerCase().includes(p.q.toLowerCase().slice(0, 10))); this.messages.push({ from: 'user', body: q }); this.messages.push({ from: 'bot', body: hit ? hit.a : @js(__('panel.chat.fallback_answer')) }); } }">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.chat.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.chat.subtitle') }}</p>
    </header>

    <section class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="panel-card flex min-h-[32rem] flex-col p-5">
            <div class="flex-1 space-y-3 overflow-y-auto rounded-2xl bg-slate-50 p-4 dark:bg-slate-900/60">
                <div class="rounded-2xl rounded-tl-sm bg-emerald-500/10 p-3 text-sm text-slate-700 dark:text-slate-300">
                    {{ __('panel.chat.ready_message') }}
                </div>
                <template x-for="(message, i) in messages" :key="i">
                    <div class="flex" :class="message.from === 'user' ? 'justify-end' : 'justify-start'">
                        <p class="max-w-[85%] rounded-2xl p-3 text-sm" :class="message.from === 'user' ? 'rounded-tr-sm bg-emerald-600 text-white' : 'rounded-tl-sm bg-white text-slate-700 dark:bg-slate-800 dark:text-slate-300'" x-text="message.body"></p>
                    </div>
                </template>
            </div>
            <form class="mt-4 flex gap-3" @submit.prevent="send()">
                <input x-model="text" class="min-w-0 flex-1 rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900" :placeholder="@js(__('panel.chat.input_placeholder'))">
                <button class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-500">{{ __('panel.chat.send') }}</button>
            </form>
        </div>

        <aside class="panel-card h-fit p-5">
            <h2 class="mb-3 font-semibold">{{ __('panel.chat.suggested_prompts') }}</h2>
            <div class="space-y-2">
                @foreach ($assistant['prompts'] as $prompt)
                    <button type="button" @click="ask({{ Js::from($prompt) }})" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-left text-sm hover:border-emerald-500/50 dark:border-slate-700">
                        {{ $prompt['q'] }}
                    </button>
                @endforeach
            </div>
            <p class="panel-muted mt-4 text-xs">{{ __('panel.chat.demo_note') }}</p>
        </aside>
    </section>
</div>
@endsection
