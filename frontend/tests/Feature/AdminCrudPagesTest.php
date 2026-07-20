<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminCrudPagesTest extends TestCase
{
    private function adminSession(array $permissions = [], string $role = 'super_admin'): array
    {
        return [
            'auth.access_token' => 'admin-token',
            'auth.user' => [
                'id' => 27,
                'full_name' => 'Yönetici',
                'email' => 'admin@example.com',
                'is_admin' => true,
                'is_active' => true,
                'role' => $role,
                'admin_permissions' => $permissions,
                'must_change_password' => false,
            ],
        ];
    }

    private function student(): array
    {
        return [
            'id' => 42, 'full_name' => 'Aday Kullanıcı', 'email' => 'aday@example.com',
            'is_active' => true, 'preferred_locale' => 'tr', 'must_change_password' => false,
            'created_at' => '2026-07-19T12:00:00+00:00',
        ];
    }

    public function test_crud_pages_render_typed_records_and_all_super_admin_actions(): void
    {
        $student = $this->student();
        Http::fake([
            'http://localhost:8000/api/v1/admin/students' => Http::response(['total' => 1, 'students' => [$student]]),
            'http://localhost:8000/api/v1/admin/applications' => Http::response([
                'total' => 1, 'student_options' => [$student], 'applications' => [[
                    'id' => 'app-1', 'user_id' => 42, 'student_name' => 'Aday Kullanıcı', 'student_email' => 'aday@example.com',
                    'company' => 'Acme', 'role' => 'Data Analyst', 'stage' => 'applied', 'next_action' => 'Portfolyo',
                    'note' => null, 'applied_at' => '2026-07-19T12:00:00+00:00',
                ]],
            ]),
            'http://localhost:8000/api/v1/admin/interviews' => Http::response([
                'total' => 1, 'student_options' => [$student], 'interviews' => [[
                    'id' => 'int-1', 'user_id' => 42, 'student_name' => 'Aday Kullanıcı', 'student_email' => 'aday@example.com',
                    'target_role' => 'Data Analyst', 'status' => 'active', 'language' => 'tr',
                    'question_count' => 5, 'answer_count' => 2, 'created_at' => '2026-07-19T12:00:00+00:00',
                ]],
            ]),
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
        ]);

        $studentPage = $this->withSession($this->adminSession())->get('/admin/ogrenciler');
        $studentPage->assertOk()->assertSee('Aday Kullanıcı')
            ->assertSee('action="'.route('admin.students.store').'"', false)
            ->assertSee('admin-data-table', false)
            ->assertSee('detailUrlTemplate', false)
            ->assertSee('__ID__', false)
            ->assertSee(__('admin.students.edit'), false)
            ->assertSee(__('admin.students.delete'), false)
            ->assertDontSee('action="'.route('admin.students.update', 42).'"', false);

        $applicationPage = $this->withSession($this->adminSession())->get('/admin/basvurular');
        $applicationPage->assertOk()->assertSee('Acme · Data Analyst')
            ->assertSee('action="'.route('admin.applications.store').'"', false)
            ->assertSee('action="'.route('admin.applications.update', 'app-1').'"', false)
            ->assertSee('action="'.route('admin.applications.destroy', 'app-1').'"', false);

        $interviewPage = $this->withSession($this->adminSession())->get('/admin/mulakatlar');
        $interviewPage->assertOk()->assertSee('5 soru')->assertSee('2 yanıt')
            ->assertSee('action="'.route('admin.interviews.store').'"', false)
            ->assertSee('action="'.route('admin.interviews.update', 'int-1').'"', false)
            ->assertSee('action="'.route('admin.interviews.destroy', 'int-1').'"', false);
    }

    public function test_view_only_admin_sees_records_without_write_or_delete_actions(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/students' => Http::response(['total' => 1, 'students' => [$this->student()]]),
            'http://localhost:8000/health' => Http::response(['status' => 'ok']),
        ]);

        $response = $this->withSession($this->adminSession(['dashboard.view', 'students.view'], 'admin'))
            ->get('/admin/ogrenciler');

        $response->assertOk()->assertSee('Aday Kullanıcı')
            ->assertSee('admin-data-table', false)
            ->assertSee('openDrawer(student)', false)
            ->assertDontSee('action="'.route('admin.students.store').'"', false)
            ->assertDontSee(__('admin.students.edit'), false)
            ->assertDontSee(__('admin.students.delete'), false);
    }

    public function test_crud_forms_forward_only_validated_payloads_and_delete_methods(): void
    {
        Http::fake([
            'http://localhost:8000/api/v1/admin/students' => Http::response($this->student(), 201),
            'http://localhost:8000/api/v1/admin/students/42' => Http::response($this->student()),
            'http://localhost:8000/api/v1/admin/applications' => Http::response(['id' => 'app-1'], 201),
            'http://localhost:8000/api/v1/admin/applications/app-1' => Http::response(['id' => 'app-1']),
            'http://localhost:8000/api/v1/admin/interviews' => Http::response(['id' => 'int-1'], 201),
            'http://localhost:8000/api/v1/admin/interviews/int-1' => Http::response(['id' => 'int-1']),
        ]);
        $session = $this->adminSession();

        $this->withSession($session)->post('/admin/ogrenciler', [
            'full_name' => 'Aday Kullanıcı', 'email' => 'aday@example.com', 'preferred_locale' => 'tr', 'is_active' => '1',
            'temporary_password' => 'GeciciParola123!', 'temporary_password_confirmation' => 'GeciciParola123!', 'unexpected' => 'drop',
        ])->assertRedirect('/admin/ogrenciler');
        $this->withSession($session)->delete('/admin/ogrenciler/42')->assertRedirect('/admin/ogrenciler');
        $this->withSession($session)->post('/admin/basvurular', [
            'user_id' => 42, 'company' => 'Acme', 'role' => 'Data Analyst', 'stage' => 'applied', 'next_action' => 'Portfolyo', 'note' => '',
        ])->assertRedirect('/admin/basvurular');
        $this->withSession($session)->delete('/admin/basvurular/app-1')->assertRedirect('/admin/basvurular');
        $this->withSession($session)->post('/admin/mulakatlar', ['user_id' => 42, 'language' => 'tr'])->assertRedirect('/admin/mulakatlar');
        $this->withSession($session)->delete('/admin/mulakatlar/int-1')->assertRedirect('/admin/mulakatlar');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8000/api/v1/admin/students'
            && ! isset($request['unexpected']) && $request['is_active'] === true);
        foreach (['students/42', 'applications/app-1', 'interviews/int-1'] as $path) {
            Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
                && $request->url() === "http://localhost:8000/api/v1/admin/{$path}");
        }
    }
}
