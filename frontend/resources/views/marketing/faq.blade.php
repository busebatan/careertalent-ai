@extends('marketing.layouts.marketing')
 
@section('title', 'SSS / FAQ | CareerTalent AI')
 
@section('content')
<div x-data="{ lang: 'tr', open: null }" class="min-h-screen bg-[#0a0e14] text-white">
 
    {{-- ============ HERO ============ --}}
    <section class="max-w-4xl mx-auto px-6 pt-24 pb-12 text-center">
        <span class="inline-block px-4 py-1 mb-6 text-xs font-medium rounded-full border border-[#22c55e]/30 text-[#22c55e] bg-[#22c55e]/5">
            <span x-show="lang === 'tr'">SIKÇA SORULAN SORULAR</span>
            <span x-show="lang === 'en'" x-cloak>FREQUENTLY ASKED QUESTIONS</span>
        </span>
 
        <h1 class="text-4xl md:text-5xl font-bold mb-4 leading-tight">
            <span x-show="lang === 'tr'">Aklınıza takılan bir şey mi var?</span>
            <span x-show="lang === 'en'" x-cloak>Got a question in mind?</span>
        </h1>
 
        <p class="text-gray-400 text-lg max-w-2xl mx-auto">
            <span x-show="lang === 'tr'">CareerTalent AI platformunun nasıl çalıştığı, analiz süreçleri ve sunduğumuz araçlar hakkında merak edilenler.</span>
            <span x-show="lang === 'en'" x-cloak>Everything you need to know about how CareerTalent AI works, our analysis processes, and the tools we provide.</span>
        </p>
 
        {{-- Dil değiştirici butonlar --}}
        <div class="mt-6 inline-flex rounded-full border border-white/10 p-1 bg-white/5">
            <button @click="lang = 'tr'"
                    :class="lang === 'tr' ? 'bg-[#22c55e] text-black' : 'text-gray-400'"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition">TR</button>
            <button @click="lang = 'en'"
                    :class="lang === 'en' ? 'bg-[#22c55e] text-black' : 'text-gray-400'"
                    class="px-4 py-1.5 rounded-full text-sm font-medium transition">EN</button>
        </div>
    </section>
 
    {{-- ============ FAQ GROUPS ============ --}}
    <section class="max-w-3xl mx-auto px-6 pb-24">
 
        @php
        $faqs = [
            [
                'category' => ['tr' => 'Genel İşleyiş', 'en' => 'How It Works'],
                'items' => [
                    [
                        'q' => ['tr' => 'CareerTalent AI nedir?', 'en' => 'What is CareerTalent AI?'],
                        'a' => [
                            'tr' => 'CareerTalent AI, kariyer hedeflerinize ulaşmanız için yapay zeka destekli bir rehberdir. CV\'nizi ve yeteneklerinizi analiz ederek, hedeflediğiniz role ne kadar uygun olduğunuzu ölçer ve size özel bir kariyer rotası çizer.',
                            'en' => 'CareerTalent AI is an AI-powered guide to help you reach your career goals. It analyzes your CV and skills, measures your compatibility with your target role, and creates a customized career path for you.',
                        ],
                    ],
                    [
                        'q' => ['tr' => 'Yapay zeka CV\'mi nasıl analiz ediyor?', 'en' => 'How does the AI analyze my CV?'],
                        'a' => [
                            'tr' => 'Sisteme yüklediğiniz CV\'niz, seçtiğiniz hedef roldeki güncel sektör gereksinimleriyle (aranan yetenekler, diller, araçlar) karşılaştırılır. Yapay zekamız sahip olduğunuz yetenekleri haritalandırır, eksiklerinizi tam olarak tespit eder ve bir "Genel Uyum Puanı" hesaplar.',
                            'en' => 'Your uploaded CV is compared against real-time industry requirements for your target role. Our AI maps your existing skills, pinpoints your exact gaps, and calculates an "Overall Match Score".',
                        ],
                    ],
                    [
                        'q' => ['tr' => 'Yapay zekanın verdiği "To-Do List" (Kariyer Rotası) nedir?', 'en' => 'What is the AI-generated To-Do List?'],
                        'a' => [
                            'tr' => 'Uyum analizi sonrasında, hedef rolünüzle aranızdaki yetenek boşluklarını kapatmanız için yapay zeka size özel, adım adım bir görev listesi (To-Do List) oluşturur. Bu liste, hangi eğitimleri almanız, hangi araçları öğrenmeniz veya hangi projeleri yapmanız gerektiğini söyler.',
                            'en' => 'Following the match analysis, the AI generates a personalized, step-by-step action plan to close your skill gaps. This To-Do List tells you exactly which courses to take, tools to learn, or projects to build.',
                        ],
                    ],
                ],
            ],
            [
                'category' => ['tr' => 'Özellikler ve Araçlar', 'en' => 'Features & Tools'],
                'items' => [
                    [
                        'q' => ['tr' => '"Beceri Grafiği" (Radar Chart) nedir?', 'en' => 'What is the Skill Graph (Radar Chart)?'],
                        'a' => [
                            'tr' => 'Beceri Grafiği, mevcut yetenekleriniz ile hedeflediğiniz rolün beklentilerini tek bir görsel ağda karşılaştıran canlı bir radardır. Hangi alanlarda (örneğin: Programlama, İletişim) güçlü olduğunuzu ve nerelerde gelişmeniz gerektiğini tek bakışta görebilirsiniz.',
                            'en' => 'The Skill Graph is a live visual radar comparing your current skills with your target role\'s expectations. It allows you to see at a glance where you excel (e.g., Programming, Communication) and where you need improvement.',
                        ],
                    ],
                    [
                        'q' => ['tr' => 'Platform üzerinden yeni bir CV oluşturabilir miyim?', 'en' => 'Can I build a new CV on the platform?'],
                        'a' => [
                            'tr' => 'Evet! Sistemde yer alan akıllı "CV Oluşturucu" aracımızla, yapay zekanın analiz ettiği güçlü yönlerinizi öne çıkaran, ATS (Aday Takip Sistemi) uyumlu profesyonel bir CV hazırlayabilirsiniz.',
                            'en' => 'Yes! With our smart "CV Builder" tool, you can create an ATS-friendly, professional CV that highlights the strengths identified by our AI analysis.',
                        ],
                    ],
                ],
            ],
            [
                'category' => ['tr' => 'Erişim ve Güvenlik', 'en' => 'Access & Security'],
                'items' => [
                    [
                        'q' => ['tr' => 'CareerTalent AI kullanmak ücretli mi?', 'en' => 'Is CareerTalent AI free to use?'],
                        'a' => [
                            'tr' => 'CareerTalent AI şu an lansman ve pilot aşamasında olduğu için analiz, radar grafiği ve to-do listesi gibi temel özelliklerin tamamını ücretsiz olarak deneyimleyebilirsiniz.',
                            'en' => 'As CareerTalent AI is currently in its launch and pilot phase, you can experience all core features—like the analysis, radar chart, and to-do lists—completely for free.',
                        ],
                    ],
                    [
                        'q' => ['tr' => 'Yüklediğim CV ve kişisel verilerim güvende mi?', 'en' => 'Is my CV and personal data secure?'],
                        'a' => [
                            'tr' => 'Kesinlikle. Kariyer verileriniz, becerileriniz ve CV bilgileriniz yalnızca size özel kariyer analizinizi yapmak amacıyla kullanılır. Verileriniz gizli tutulur ve onayınız olmadan hiçbir kurumla paylaşılmaz.',
                            'en' => 'Absolutely. Your career data, skills, and CV information are used strictly to perform your personal career analysis. Your data is kept confidential and never shared with third parties without your consent.',
                        ],
                    ],
                ],
            ],
        ];
        @endphp
 
        <div class="space-y-10">
            @foreach ($faqs as $groupIndex => $group)
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[#22c55e] mb-4">
                    <span x-show="lang === 'tr'">{{ $group['category']['tr'] }}</span>
                    <span x-show="lang === 'en'" x-cloak>{{ $group['category']['en'] }}</span>
                </h2>
 
                <div class="space-y-3">
                    @foreach ($group['items'] as $itemIndex => $item)
                    @php $uid = $groupIndex . '-' . $itemIndex; @endphp
                    <div class="border border-white/10 rounded-xl overflow-hidden bg-white/[0.02]">
                        <button
                            @click="open === '{{ $uid }}' ? open = null : open = '{{ $uid }}'"
                            class="w-full flex items-center justify-between text-left px-5 py-4 hover:bg-white/[0.03] transition">
                            <span class="font-medium pr-4">
                                <span x-show="lang === 'tr'">{{ $item['q']['tr'] }}</span>
                                <span x-show="lang === 'en'" x-cloak>{{ $item['q']['en'] }}</span>
                            </span>
                            <span class="text-[#22c55e] text-xl flex-shrink-0 transition-transform"
                                  :class="open === '{{ $uid }}' ? 'rotate-45' : ''">+</span>
                        </button>
                        <div x-show="open === '{{ $uid }}'" x-collapse class="px-5 pb-4 text-gray-400 text-sm leading-relaxed">
                            <span x-show="lang === 'tr'">{{ $item['a']['tr'] }}</span>
                            <span x-show="lang === 'en'" x-cloak>{{ $item['a']['en'] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
 
        {{-- ============ STILL HAVE QUESTIONS ============ --}}
        <div class="mt-16 text-center border-t border-white/10 pt-12">
            <h3 class="text-xl font-semibold mb-2">
                <span x-show="lang === 'tr'">Hâlâ sorunuz mu var?</span>
                <span x-show="lang === 'en'" x-cloak>Still have questions?</span>
            </h3>
            <p class="text-gray-400 mb-6">
                <span x-show="lang === 'tr'">Bize ulaşın, en kısa sürede dönüş yapalım.</span>
                <span x-show="lang === 'en'" x-cloak>Reach out and we'll get back to you shortly.</span>
            </p>
            <a href="{{ Route::has('contact') ? route('contact') : '#' }}"
               class="inline-block px-6 py-3 rounded-full bg-[#22c55e] text-black font-medium hover:opacity-90 transition">
                <span x-show="lang === 'tr'">İletişime Geç</span>
                <span x-show="lang === 'en'" x-cloak>Contact Us</span>
            </a>
        </div>
    </section>
</div>
@endsection