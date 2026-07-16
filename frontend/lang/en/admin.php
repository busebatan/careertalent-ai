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
        'profile' => 'Admin Profile',
        'accounts' => 'Admin Accounts',
        'open_menu' => 'Open menu',
        'student_panel' => 'Student panel',
        'marketing_site' => 'Marketing site',
    ],
    'nav_groups' => [
        'general' => 'GENERAL',
        'data' => 'DATA MANAGEMENT',
        'students' => 'STUDENT MANAGEMENT',
        'operations' => 'CAREER OPERATIONS',
    ],
    'roles' => ['super_admin' => 'Super Admin', 'admin' => 'Admin'],
    'permissions' => [
        'dashboard.view' => 'View admin overview',
        'career_data.manage' => 'Manage career data',
        'students.view' => 'View students',
        'readiness.view' => 'View readiness analysis',
        'skill_passport.view' => 'View skill passports',
        'job_radar.view' => 'View job radar',
        'applications.view' => 'View applications',
        'interviews.view' => 'View interviews',
    ],
    'profile' => [
        'title' => 'Admin Profile', 'subtitle' => 'Update your administrator account details and password.',
        'identity' => 'Account identity', 'full_name' => 'Full name', 'email' => 'Email', 'role' => 'Role',
        'status' => 'Account status', 'active' => 'Active', 'inactive' => 'Inactive',
        'current_password' => 'Current password', 'new_password' => 'New password',
        'new_password_hint' => 'Leave blank to keep it unchanged.', 'save' => 'Update profile',
        'saved' => 'Admin profile updated.', 'login_again' => 'Account updated. Sign in again with the new details.',
        'password_required' => 'Change your temporary password to continue.',
    ],
    'accounts' => [
        'title' => 'Admin Accounts', 'subtitle' => 'Create authorized people, assign module access, and activate or deactivate accounts.',
        'create' => 'Create new admin', 'full_name' => 'Full name', 'email' => 'Email',
        'temporary_password' => 'Temporary password', 'temporary_password_confirmation' => 'Confirm temporary password',
        'permissions' => 'Module permissions', 'status' => 'Account status', 'active' => 'Active', 'inactive' => 'Inactive',
        'must_change_password' => 'Waiting for password change on first login', 'save' => 'Update account',
        'reset_password' => 'New temporary password (optional)', 'created' => 'Admin account created.',
        'updated' => 'Admin account updated.', 'empty' => 'No other admin account yet.',
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
