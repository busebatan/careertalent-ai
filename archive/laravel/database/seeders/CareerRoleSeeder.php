<?php

namespace Database\Seeders;

use App\Models\CareerRole;
use App\Models\Cohort;
use Illuminate\Database\Seeder;

class CareerRoleSeeder extends Seeder
{
    public function run(): void
    {
        Cohort::firstOrCreate(
            ['name' => 'YZTA Grup 92'],
            ['bootcamp' => 'YZTA']
        );

        $path = base_path('data/roles/bootcamp_roles.json');
        $roles = json_decode(file_get_contents($path), true)['roles'];

        foreach ($roles as $role) {
            CareerRole::updateOrCreate(
                ['slug' => $role['id']],
                [
                    'title' => $role['title'],
                    'description' => $role['description'],
                    'required_skills' => $role['required_skills'],
                    'weeks_template' => $role['weeks_template'],
                ]
            );
        }
    }
}
