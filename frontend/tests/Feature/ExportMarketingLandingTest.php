<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportMarketingLandingTest extends TestCase
{
    public function test_marketing_export_landing_komutu_calisir(): void
    {
        $output = storage_path('framework/testing-landing');

        File::deleteDirectory($output);

        $exitCode = Artisan::call('marketing:export-landing', ['--output' => $output]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($output.'/index.html');
        $this->assertFileExists($output.'/ozellikler/index.html');
        $this->assertFileExists($output.'/nasil-calisir/index.html');
        $this->assertFileExists($output.'/bootcamp/index.html');
        $this->assertStringContainsString('Gelecek kaygını', File::get($output.'/index.html'));

        File::deleteDirectory($output);
    }

    public function test_nasil_calisir_sayfasi_acilir(): void
    {
        $this->get('/nasil-calisir')->assertStatus(200)->assertSee('Nasıl Çalışır?');
    }
}
