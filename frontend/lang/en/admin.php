<?php

return [
    'brand' => 'CareerTalent Admin',
    'area_kicker' => 'Admin workspace',
    'header' => [
        'kicker' => 'Admin',
        'subtitle' => 'Management surface separate from the student panel',
        'mobile_brand' => 'CareerTalent Admin',
    ],
    'nav' => [
        'dashboard' => 'Dashboard',
        'student_panel' => 'Student panel',
        'marketing_site' => 'Marketing site',
    ],
    'modules' => [
        'students' => [
            'title' => 'Students',
            'description' => 'Active student accounts and CV/analysis status.',
        ],
        'readiness' => [
            'title' => 'Readiness Analysis',
            'description' => 'Live CV analysis status and skill counts.',
        ],
        'skill-passport' => [
            'title' => 'Skill Passport',
            'description' => 'Evidence records uploaded by students.',
        ],
        'job-radar' => [
            'title' => 'Job Radar',
            'description' => 'Job postings analyzed by students.',
        ],
        'applications' => [
            'title' => 'Applications',
            'description' => 'Application records saved by students.',
        ],
        'interviews' => [
            'title' => 'Interviews',
            'description' => 'Started interview simulations.',
        ],
    ],
    'dashboard' => [
        'title' => 'Admin Overview',
        'subtitle' => 'Active student accounts and related live records only.',
        'recent_students' => 'Recently registered students',
        'recent_students_hint' => 'Admin accounts are excluded from this list.',
        'registered_at' => 'Registered: :date',
        'registered_unknown' => 'Registered: Unknown',
        'no_students' => 'No student registrations yet.',
        'records_count' => ':count records',
        'open_records' => 'Open records',
    ],
    'page' => [
        'records_title' => 'Live records',
        'records_hint' => ':total records · Showing up to the latest 50 entries.',
        'empty_rows' => 'No live records in this module yet.',
    ],
    'errors' => [
        'api_unavailable' => 'Admin data could not be loaded: :error',
        'api_unavailable_generic' => 'Admin data could not be loaded.',
    ],
    'notifications' => [
        [
            'id' => 'admin-notif-1',
            'title' => 'New student registration',
            'body' => 'A new student account was created in the last 24 hours.',
            'time' => 'Just now',
            'unread' => true,
        ],
        [
            'id' => 'admin-notif-2',
            'title' => 'Evidence review queue',
            'body' => 'Skill passport evidence is waiting for review.',
            'time' => '1 hour ago',
            'unread' => true,
        ],
    ],
];
