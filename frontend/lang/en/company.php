<?php

return [
    'brand' => 'CareerTalent Company',
    'nav' => ['general' => 'General', 'organization' => 'Organization Management', 'dashboard' => 'Company Overview', 'team' => 'Team & Permissions', 'profile' => 'Company Profile', 'open_menu' => 'Open menu', 'marketing_site' => 'Main site'],
    'header' => ['secure_context' => 'Organization context verified'],
    'organization' => ['active' => 'Active organization'],
    'roles' => ['owner' => 'Owner', 'admin' => 'Company Admin', 'recruiter' => 'Recruiter', 'hiring_manager' => 'Hiring Manager', 'viewer' => 'Viewer'],
    'status' => ['active' => 'Active', 'suspended' => 'Suspended'],
    'permissions' => [
        'dashboard.view' => 'View company overview',
        'organization.update' => 'Update company profile',
        'members.view' => 'View team members',
        'members.invite' => 'Invite team members',
        'members.manage' => 'Manage members and permissions',
    ],
    'dashboard' => ['title' => 'Company Overview', 'subtitle' => 'Live organization, team access, and onboarding summary.', 'members_total' => 'Team members', 'members_active' => 'Active members', 'invitations' => 'Pending invitations', 'foundation_title' => 'Secure company foundation is ready', 'foundation_text' => 'Complete company information and team roles before opening hiring modules.', 'manage_team' => 'Manage team'],
    'profile' => ['title' => 'Company Profile', 'subtitle' => 'Organization information used across candidate and team surfaces.', 'name' => 'Organization name', 'billing_email' => 'Billing email', 'website' => 'Website', 'save' => 'Save details', 'updated' => 'Company profile updated.'],
    'team' => [
        'title' => 'Team & Permissions', 'subtitle' => 'Each member sees and uses only the company permissions assigned to them.',
        'invite_title' => 'Invite a new team member', 'email' => 'Email', 'role' => 'Role', 'status_label' => 'Status', 'permissions' => 'Permissions',
        'invite' => 'Create invitation', 'invited' => 'Team invitation created.', 'invite_link' => 'One-time invitation link',
        'edit' => 'Edit member and permissions', 'save' => 'Save', 'updated' => 'Team membership updated.',
        'pending' => 'Pending invitations', 'empty' => 'No team members yet.',
    ],
];
