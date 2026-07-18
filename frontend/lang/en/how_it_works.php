<?php

return [

    'title' => 'How It Works — CareerTalent AI',

    'hero' => [
        'eyebrow'         => 'The Process',
        'title'           => 'One thread, from resume to offer.',
        'subtitle'        => 'CareerTalent AI reads your CV, builds the plan that closes your gaps, and gets you interview-ready — start to finish, in three steps.',
        'cta_primary'     => 'Get started free',
        'cta_secondary'   => 'Watch the overview',
        'video_url_label' => 'app.careertalent.ai/overview',
        'video_duration'  => '2:14',
        'video_caption'   => 'A two-minute walkthrough of the full process — video coming soon.',
    ],

    'stats' => [
        'eyebrow' => 'By the numbers',
        'items'   => [
            ['value' => 3,   'suffix' => '',      'label' => 'steps to go from CV to offer'],
            ['value' => 500, 'suffix' => '+',     'label' => 'practice interview questions in the bank'],
            ['value' => 21,  'suffix' => ' days',  'label' => 'average prep time to interview-ready'],
            ['value' => 100, 'suffix' => '%',     'label' => 'personalized to your own CV'],
        ],
    ],

    'process' => [
        'eyebrow'    => 'Step by step',
        'title'      => 'Follow the thread.',
        'subtitle'   => 'Every step below feeds the next — nothing you do here goes to waste.',
        'powered_by' => 'Powered by',
        'step_label' => 'Step',
    ],

    // Order here is the order the steps render in — insertion order is preserved.
    'steps' => [
        'analyze' => [
            'nav_label'      => 'Analyze',
            'chips'          => ['CV Merkezi', 'CV Oluşturucu'],
            'title'          => 'Upload once. See everything.',
            'desc'           => 'Drop in your CV and CareerTalent AI reads it the way a recruiter would — parsing your skills, flagging the gaps, and scoring your fit against the roles you actually want on a radar chart you can act on.',
            'path'           => 'cv-merkezi',
            'video_label'    => 'Watch: Analyze & Optimize',
            'benefits_label' => 'What you get',
            'benefits'       => [
                'A skills breakdown recruiters actually look for',
                'A fit score for every role you\'re targeting',
                'A clear list of what\'s missing, ranked by impact',
            ],
        ],
        'plan' => [
            'nav_label'      => 'Plan',
            'chips'          => ['Kariyer Rotam', 'Yetenek Pasaportu'],
            'title'          => 'Turn gaps into a plan.',
            'desc'           => 'Every missing skill becomes a task on your personal roadmap. Work through them at your own pace, and watch your Talent Passport fill in as proof of what you\'ve actually learned.',
            'path'           => 'kariyer-rotam',
            'video_label'    => 'Watch: Plan Your Path',
            'benefits_label' => 'What you get',
            'benefits'       => [
                'A personal roadmap built from your actual gaps',
                'Tasks sized to fit around your schedule',
                'A Talent Passport that fills in as proof',
            ],
        ],
        'land' => [
            'nav_label'      => 'Land',
            'chips'          => ['İş Fırsatları', 'Mülakat Hazırlığı'],
            'title'          => 'Apply with confidence.',
            'desc'           => 'See instant match scores against live job listings, rehearse with the mock interview simulator, and track every application in one place — from first message to offer.',
            'path'           => 'is-firsatlari',
            'video_label'    => 'Watch: Land the Job',
            'benefits_label' => 'What you get',
            'benefits'       => [
                'Live match scores against real job listings',
                'A mock interview simulator to rehearse with',
                'One dashboard to track every application',
            ],
        ],
    ],

    'video' => [
        'toast_hero' => 'Full video coming soon',
        'toast_step' => 'Coming soon',
    ],

    'demo' => [
        'eyebrow'      => 'Try it yourself',
        'title'        => 'See what a match score actually looks like.',
        'subtitle'     => 'Pick a target role and watch how CareerTalent AI would read a CV against it.',
        'role_label'   => 'Target role',
        'score_label'  => 'Match score',
        'matched_label' => 'Already matches',
        'gaps_label'   => 'Still missing',
        'caption'      => 'Illustrative example — your real score is calculated from your own CV.',
        'cta'          => 'Upload your CV to get your real score',
        'roles'        => [
            [
                'key'     => 'dev',
                'label'   => 'Software Developer',
                'score'   => 72,
                'matched' => ['Git & version control', 'REST API integration', 'Code review in a team setting'],
                'gaps'    => ['Cloud infrastructure (AWS/GCP)', 'Writing automated tests'],
            ],
            [
                'key'     => 'data',
                'label'   => 'Data Analyst',
                'score'   => 61,
                'matched' => ['SQL queries', 'Reporting in Excel/Sheets'],
                'gaps'    => ['Data cleaning in Python', 'Visualization tools (Tableau/Power BI)', 'Basic statistical testing'],
            ],
            [
                'key'     => 'pm',
                'label'   => 'Product Manager',
                'score'   => 55,
                'matched' => ['Running user interviews'],
                'gaps'    => ['Roadmap prioritization', 'Defining metrics (KPIs)', 'Working with technical teams'],
            ],
        ],
    ],

    'faq' => [
        'eyebrow' => 'Questions',
        'title'   => 'Before you start',
        'items'   => [
            [
                'q' => 'Is CareerTalent AI free to use?',
                'a' => 'Yes — creating an account, uploading your CV, and getting your first match score is completely free.',
            ],
            [
                'q' => 'How long does the whole process take?',
                'a' => 'Getting your first analysis takes under two minutes. Closing your gaps is self-paced — some people finish in a weekend, others spread it over a few weeks.',
            ],
            [
                'q' => 'Do I need a finished CV to start?',
                'a' => 'No. A rough draft or even an old CV is enough — CareerTalent AI shows you exactly what to add or fix.',
            ],
            [
                'q' => 'Which languages does it support?',
                'a' => 'Both the platform and your analysis are available in Turkish and English — switch anytime from the site menu.',
            ],
        ],
    ],

    'sticky_cta' => [
        'text'   => 'Ready to see your own match score?',
        'button' => 'Get started free',
    ],

    'closing' => [
        'title'         => 'See your own roadmap.',
        'desc'          => 'Upload your CV and get your first match score in under two minutes.',
        'cta_primary'   => 'Get started free',
        'cta_secondary' => 'Sign in',
    ],

];