@extends('app.layouts.app')
@section('title', __('panel.chat.title'))
@section('content')
<div class="mx-auto max-w-4xl" x-data="careerChat({{ Js::from($messages) }}, @js(route('panel.chat.send')), @js(route('panel.chat.clear')), {{ Js::from(['failed' => __('panel.chat.failed')]) }})">
    <header class="mb-8 flex items-start justify-between gap-4">
        <div><h1 class="mb-1 text-2xl font-bold">{{ __('panel.chat.title') }}</h1><p class="text-slate-600 dark:text-slate-400">{{ __('panel.chat.subtitle') }}</p></div>
        <button type="button" class="panel-outline-btn" @click="clear()" x-show="messages.length">{{ __('panel.chat.clear') }}</button>
    </header>
    @if ($chatError)<p class="mb-4 rounded-xl bg-red-500/10 p-3 text-sm text-red-700 dark:text-red-200">{{ $chatError }}</p>@endif
    <section class="panel-card flex min-h-[32rem] flex-col p-5">
        <div class="flex-1 space-y-3 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-100 p-4 dark:border-slate-800 dark:bg-slate-950/70">
            <div x-show="!messages.length" class="rounded-2xl rounded-tl-sm border border-emerald-500/20 bg-emerald-500/10 p-3 text-sm text-slate-800 dark:text-slate-200">{{ __('panel.chat.ready_message') }}</div>
            <template x-for="message in messages" :key="message.id"><div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'"><div class="max-w-[85%]"><p class="rounded-2xl p-3 text-sm" :class="message.role === 'user' ? 'rounded-tr-sm bg-emerald-600 text-white' : 'rounded-tl-sm border border-slate-200 bg-white text-slate-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'" x-text="message.content"></p><template x-if="message.meta?.suggested_actions?.length"><ul class="mt-2 space-y-1 text-xs text-emerald-700"><template x-for="action in message.meta.suggested_actions" :key="action"><li x-text="'• ' + action"></li></template></ul></template></div></div></template>
            <p x-show="sending" class="text-sm text-slate-500">{{ __('panel.chat.thinking') }}</p>
        </div>
        <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600"></p>
        <form class="mt-4 flex gap-3" @submit.prevent="send()"><input x-model="text" class="panel-input-block min-w-0 flex-1 rounded-xl" :placeholder="@js(__('panel.chat.input_placeholder'))"><button :disabled="sending" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:opacity-60">{{ __('panel.chat.send') }}</button></form>
    </section>
</div>
@endsection
