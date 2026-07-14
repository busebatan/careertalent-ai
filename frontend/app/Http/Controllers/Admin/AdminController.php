<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;

class AdminController extends Controller
{
    /**
     * @var array<string, array{route: string, icon: string}>
     */
    private const MODULE_KEYS = [
        'students' => ['route' => 'admin.students', 'icon' => 'profile'],
        'readiness' => ['route' => 'admin.readiness', 'icon' => 'ladder'],
        'skill-passport' => ['route' => 'admin.skill-passport', 'icon' => 'passport'],
        'job-radar' => ['route' => 'admin.job-radar', 'icon' => 'radar'],
        'applications' => ['route' => 'admin.applications', 'icon' => 'applications'],
        'interviews' => ['route' => 'admin.interviews', 'icon' => 'interview'],
    ];

    public function dashboard(CareerTalentApiClient $api)
    {
        $response = $api->adminDashboard();
        $data = is_array($response['body']) ? $response['body'] : [];

        return $this->adminView('admin.dashboard', [
            'stats' => $data['stats'] ?? [],
            'recentStudents' => $data['recent_students'] ?? [],
            'modules' => $this->modules($data['module_counts'] ?? []),
            'adminError' => $response['ok'] ? null : $this->apiError($response['error']),
        ], $api);
    }

    public function students(CareerTalentApiClient $api) { return $this->page('students', $api); }
    public function readiness(CareerTalentApiClient $api) { return $this->page('readiness', $api); }
    public function skillPassport(CareerTalentApiClient $api) { return $this->page('skill-passport', $api); }
    public function jobRadar(CareerTalentApiClient $api) { return $this->page('job-radar', $api); }
    public function applications(CareerTalentApiClient $api) { return $this->page('applications', $api); }
    public function interviews(CareerTalentApiClient $api) { return $this->page('interviews', $api); }

    private function page(string $key, CareerTalentApiClient $api)
    {
        abort_unless(isset(self::MODULE_KEYS[$key]), 404);

        $module = $this->moduleDefinition($key);
        $response = $api->adminModule($key);
        $data = is_array($response['body']) ? $response['body'] : [];

        return $this->adminView('admin.page', [
            'page' => [
                'title' => $data['title'] ?? $module['title'],
                'subtitle' => $data['subtitle'] ?? $module['description'],
                'total' => $data['total'] ?? 0,
                'rows' => $data['rows'] ?? [],
            ],
            'adminError' => $response['ok'] ? null : $this->apiError($response['error']),
        ], $api);
    }

    /**
     * @param  mixed  $counts
     * @return list<array{key: string, title: string, description: string, route: string, icon: string, count: int}>
     */
    private function modules(mixed $counts): array
    {
        $counts = is_array($counts) ? $counts : [];

        return array_map(function (string $key) use ($counts): array {
            $module = $this->moduleDefinition($key);

            return [
                'key' => $key,
                ...$module,
                'count' => is_int($counts[$key] ?? null) ? $counts[$key] : 0,
            ];
        }, array_keys(self::MODULE_KEYS));
    }

    /**
     * @return array{title: string, description: string, route: string, icon: string}
     */
    private function moduleDefinition(string $key): array
    {
        $meta = self::MODULE_KEYS[$key];

        return [
            'title' => __("admin.modules.{$key}.title"),
            'description' => __("admin.modules.{$key}.description"),
            'route' => $meta['route'],
            'icon' => $meta['icon'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function adminView(string $view, array $data, CareerTalentApiClient $api)
    {
        return view($view, array_merge($data, [
            'apiHealth' => $api->health(),
            'adminNav' => $this->adminNav(),
        ]));
    }

    /**
     * @return list<array{route: string, label: string, icon: string}>
     */
    private function adminNav(): array
    {
        return [
            ['route' => 'admin.dashboard', 'label' => __('admin.nav.dashboard'), 'icon' => 'dashboard'],
            ...array_map(
                fn (string $key): array => [
                    'route' => self::MODULE_KEYS[$key]['route'],
                    'label' => __("admin.modules.{$key}.title"),
                    'icon' => self::MODULE_KEYS[$key]['icon'],
                ],
                array_keys(self::MODULE_KEYS),
            ),
        ];
    }

    private function apiError(?string $error): string
    {
        return $error
            ? __('admin.errors.api_unavailable', ['error' => $error])
            : __('admin.errors.api_unavailable_generic');
    }
}
