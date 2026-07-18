@extends('marketing.layouts.marketing')

@section('title', __('marketing.contact.title'))

@section('content')
<div class="min-h-screen bg-[#0a0e14] pt-24 pb-20">
    <div class="max-w-3xl mx-auto px-6">
        
        {{-- Header --}}
        <div class="text-center mb-12">
            <span class="inline-block px-4 py-1 mb-6 text-xs font-medium rounded-full border border-[#22c55e]/30 text-[#22c55e] bg-[#22c55e]/5 uppercase tracking-wider">
                {{ __('marketing.contact.badge') }}
            </span>
            <h2 class="text-3xl md:text-5xl font-bold mb-6 leading-tight text-white">
                {{ __('marketing.contact.heading') }}
            </h2>
            <p class="text-gray-400 text-lg">
                {{ __('marketing.contact.intro') }}
            </p>
        </div>

        {{-- Form Box --}}
        <div class="p-8 md:p-10 rounded-3xl bg-white/[0.02] border border-white/10 relative overflow-hidden shadow-2xl">
            
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-[#22c55e]/10 rounded-full blur-3xl pointer-events-none"></div>

            <form action="#" method="POST" class="space-y-6 relative z-10">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">{{ __('marketing.contact.form.name') }}</label>
                        <input type="text" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-600 focus:outline-none focus:border-[#22c55e]/50 transition" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">{{ __('marketing.contact.form.email') }}</label>
                        <input type="email" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-600 focus:outline-none focus:border-[#22c55e]/50 transition" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">{{ __('marketing.contact.form.message') }}</label>
                    <textarea rows="4" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-600 focus:outline-none focus:border-[#22c55e]/50 transition"></textarea>
                </div>

                <button type="submit" class="w-full py-4 mt-2 rounded-xl bg-[#22c55e] text-black font-semibold hover:opacity-90 hover:scale-[0.99] transition-all">
                    {{ __('marketing.contact.form.submit') }}
                </button>
            </form>
        </div>

    </div>
</div>
@endsection