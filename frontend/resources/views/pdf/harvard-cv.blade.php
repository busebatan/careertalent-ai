@php
    $isTurkish = $language === 'tr';
    $labels = $isTurkish
        ? ['summary' => 'Özet', 'education' => 'Eğitim', 'experience' => 'Deneyim', 'skills' => 'Beceriler', 'projects' => 'Projeler', 'certificates' => 'Sertifikalar']
        : ['summary' => 'Summary', 'education' => 'Education', 'experience' => 'Experience', 'skills' => 'Skills', 'projects' => 'Projects', 'certificates' => 'Certifications'];
    $optionalLabels = $isTurkish
        ? [
            'awards' => 'Ödüller ve başarılar', 'volunteer' => 'Gönüllülük', 'publications' => 'Yayınlar',
            'courses' => 'Kurslar ve eğitimler', 'languages' => 'Diller', 'leadership' => 'Liderlik ve aktiviteler',
            'affiliations' => 'Üyelikler', 'references' => 'Referanslar', 'interests' => 'İlgi alanları',
            'research' => 'Araştırma deneyimi', 'additional' => 'Ek bilgiler',
        ]
        : [
            'awards' => 'Awards & honors', 'volunteer' => 'Volunteer experience', 'publications' => 'Publications',
            'courses' => 'Courses & training', 'languages' => 'Languages', 'leadership' => 'Leadership & activities',
            'affiliations' => 'Affiliations', 'references' => 'References', 'interests' => 'Interests',
            'research' => 'Research experience', 'additional' => 'Additional information',
        ];
    $personal = $cv['personal'];
    $contact = array_values(array_filter([$personal['email'], $personal['phone'], $personal['location'], $personal['linkedin']]));
@endphp
<!doctype html>
<html lang="{{ $language }}">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4; margin: 12mm; }
        html, body { margin: 0; padding: 0; color: #000; background: #fff; }
        body { font-family: Georgia, 'Times New Roman', serif; font-size: 10.5pt; line-height: 1.34; }
        .cv { box-sizing: border-box; width: 100%; }
        h1 { margin: 0 0 3pt; text-align: center; font-size: 18pt; line-height: 1.15; letter-spacing: .02em; }
        .contact { margin: 0 0 11pt; text-align: center; font-size: 9.5pt; overflow-wrap: anywhere; }
        .section { margin: 0; }
        h2 { margin: 12pt 0 5pt; padding: 0 0 2pt; border-bottom: .75pt solid #000; font-size: 10.5pt; line-height: 1.2; letter-spacing: .05em; text-transform: uppercase; break-after: avoid; page-break-after: avoid; }
        p { margin: 3pt 0; overflow-wrap: anywhere; }
        .entry { margin: 0 0 7pt; break-inside: avoid; page-break-inside: avoid; }
        .entry-header, .entry-sub { display: table; width: 100%; table-layout: fixed; }
        .entry-header span, .entry-sub span { display: table-cell; vertical-align: top; overflow-wrap: anywhere; }
        .entry-header span:last-child, .entry-sub span:last-child { text-align: right; padding-left: 14pt; }
        .entry-header { font-size: 10pt; font-weight: 700; }
        .entry-sub { font-size: 9.5pt; font-style: italic; }
        ul { margin: 3pt 0 0 15pt; padding: 0; }
        li { margin: 0 0 2pt; break-inside: avoid; page-break-inside: avoid; overflow-wrap: anywhere; }
        .plain-entry { break-inside: avoid; page-break-inside: avoid; }
        .muted { color: #333; font-size: 9pt; }
    </style>
</head>
<body>
<main class="cv">
    <h1>{{ $personal['full_name'] !== '' ? $personal['full_name'] : 'CV' }}</h1>
    @if ($contact !== [])
        <p class="contact">{{ implode(' · ', $contact) }}</p>
    @endif

    @if ($personal['summary'] !== '')
        <section class="section"><h2>{{ $labels['summary'] }}</h2><p>{{ $personal['summary'] }}</p></section>
    @endif

    @if ($cv['education'] !== [])
        <section class="section"><h2>{{ $labels['education'] }}</h2>
            @foreach ($cv['education'] as $entry)
                <article class="entry">
                    <div class="entry-header"><span>{{ $entry['institution'] }}</span><span>{{ $entry['location'] }}</span></div>
                    <div class="entry-sub"><span>{{ $entry['degree'] }}</span><span>{{ trim($entry['start'].' - '.$entry['end'], ' -') }}</span></div>
                    @if ($entry['details'] !== '')<p>{{ $entry['details'] }}</p>@endif
                </article>
            @endforeach
        </section>
    @endif

    @if ($cv['experience'] !== [])
        <section class="section"><h2>{{ $labels['experience'] }}</h2>
            @foreach ($cv['experience'] as $entry)
                <article class="entry">
                    <div class="entry-header"><span>{{ $entry['organization'] }}</span><span>{{ $entry['location'] }}</span></div>
                    <div class="entry-sub"><span>{{ $entry['title'] }}</span><span>{{ trim($entry['start'].' - '.$entry['end'], ' -') }}</span></div>
                    @if ($entry['bullets'] !== [])<ul>@foreach ($entry['bullets'] as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>@endif
                </article>
            @endforeach
        </section>
    @endif

    @if ($cv['skills'] !== [])
        <section class="section"><h2>{{ $labels['skills'] }}</h2>
            @foreach ($cv['skills'] as $entry)<p class="plain-entry">@if ($entry['category'] !== '')<strong>{{ $entry['category'] }}:</strong> @endif{{ $entry['items'] }}</p>@endforeach
        </section>
    @endif

    @if ($cv['projects'] !== [])
        <section class="section"><h2>{{ $labels['projects'] }}</h2>
            @foreach ($cv['projects'] as $entry)
                <article class="entry">
                    <div class="entry-header"><span>{{ $entry['name'] }}</span><span>{{ trim($entry['start'].($entry['end'] !== '' ? ' - '.$entry['end'] : ''), ' -') }}</span></div>
                    @if ($entry['link'] !== '')<p class="muted">{{ $entry['link'] }}</p>@endif
                    @if ($entry['description'] !== '')<p>{{ $entry['description'] }}</p>@endif
                </article>
            @endforeach
        </section>
    @endif

    @if ($cv['certificates'] !== [])
        <section class="section"><h2>{{ $labels['certificates'] }}</h2>
            @foreach ($cv['certificates'] as $entry)<p class="plain-entry">{{ implode(', ', array_filter([$entry['name'], $entry['issuer'], $entry['date']])) }}</p>@endforeach
        </section>
    @endif

    @foreach (($cv['enabledOptional'] ?? []) as $sectionKey)
        @php($entries = $cv['optional'][$sectionKey] ?? [])
        @continue($entries === [])
        <section class="section">
            <h2>{{ $optionalLabels[$sectionKey] }}</h2>

            @if ($sectionKey === 'awards')
                @foreach ($entries as $entry)
                    <p class="plain-entry">
                        <strong>{{ $entry['title'] }}</strong>
                        @if ($entry['issuer'] !== '') · {{ $entry['issuer'] }} @endif
                        @if ($entry['date'] !== '') ({{ $entry['date'] }}) @endif
                        @if ($entry['details'] !== '') — {{ $entry['details'] }} @endif
                    </p>
                @endforeach
            @elseif (in_array($sectionKey, ['volunteer', 'leadership'], true))
                @foreach ($entries as $entry)
                    <article class="entry">
                        <div class="entry-header"><span>{{ $entry['organization'] }}</span><span>{{ $entry['location'] }}</span></div>
                        <div class="entry-sub"><span>{{ $entry['role'] }}</span><span>{{ trim($entry['start'].($entry['end'] !== '' ? ' - '.$entry['end'] : ''), ' -') }}</span></div>
                        @if ($entry['bullets'] !== [])<ul>@foreach ($entry['bullets'] as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>@endif
                    </article>
                @endforeach
            @elseif ($sectionKey === 'publications')
                @foreach ($entries as $entry)
                    <article class="entry">
                        <p><strong>{{ $entry['title'] }}</strong>@if ($entry['publisher'] !== ''), {{ $entry['publisher'] }}@endif @if ($entry['date'] !== '')({{ $entry['date'] }})@endif</p>
                        @if ($entry['link'] !== '')<p class="muted">{{ $entry['link'] }}</p>@endif
                        @if ($entry['description'] !== '')<p>{{ $entry['description'] }}</p>@endif
                    </article>
                @endforeach
            @elseif ($sectionKey === 'courses')
                @foreach ($entries as $entry)
                    <article class="entry">
                        <p><strong>{{ $entry['name'] }}</strong>@if ($entry['institution'] !== ''), {{ $entry['institution'] }}@endif @if ($entry['date'] !== '')({{ $entry['date'] }})@endif</p>
                        @if ($entry['description'] !== '')<p>{{ $entry['description'] }}</p>@endif
                    </article>
                @endforeach
            @elseif ($sectionKey === 'languages')
                @foreach ($entries as $entry)<p class="plain-entry">{{ $entry['language'] }}@if ($entry['level'] !== '') — {{ $entry['level'] }}@endif</p>@endforeach
            @elseif ($sectionKey === 'affiliations')
                @foreach ($entries as $entry)
                    <p class="plain-entry">
                        {{ $entry['name'] }}@if ($entry['role'] !== ''), {{ $entry['role'] }}@endif
                        @if ($entry['start'] !== '' || $entry['end'] !== '') ({{ trim($entry['start'].($entry['end'] !== '' ? ' - '.$entry['end'] : ''), ' -') }}) @endif
                    </p>
                @endforeach
            @elseif ($sectionKey === 'references')
                @foreach ($entries as $entry)
                    <p class="plain-entry">
                        <strong>{{ $entry['name'] }}</strong>@if ($entry['title'] !== ''), {{ $entry['title'] }}@endif
                        @if ($entry['organization'] !== '') — {{ $entry['organization'] }} @endif
                        @if ($entry['contact'] !== '') · {{ $entry['contact'] }} @endif
                    </p>
                @endforeach
            @elseif ($sectionKey === 'interests')
                @foreach ($entries as $entry)<p class="plain-entry">{{ $entry['items'] }}</p>@endforeach
            @elseif ($sectionKey === 'research')
                @foreach ($entries as $entry)
                    <article class="entry">
                        <div class="entry-header"><span>{{ $entry['title'] }}</span><span>{{ trim($entry['start'].($entry['end'] !== '' ? ' - '.$entry['end'] : ''), ' -') }}</span></div>
                        @if ($entry['institution'] !== '')<div class="entry-sub"><span>{{ $entry['institution'] }}</span></div>@endif
                        @if ($entry['description'] !== '')<p>{{ $entry['description'] }}</p>@endif
                    </article>
                @endforeach
            @elseif ($sectionKey === 'additional')
                @foreach ($entries as $entry)<p class="plain-entry">{{ $entry['body'] }}</p>@endforeach
            @endif
        </section>
    @endforeach
</main>
</body>
</html>
