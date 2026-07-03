<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class ExportMarketingLanding extends Command
{
    protected $signature = 'marketing:export-landing {--output= : landing dizini (varsayılan: repo kökü/landing)}';

    protected $description = 'Marketing Blade şablonlarını prod statik landing/ çıktısına aktarır';

    /** @var array<string, string> */
    private array $pages = [
        'index.html' => 'marketing.home',
        'ozellikler/index.html' => 'marketing.features',
        'nasil-calisir/index.html' => 'marketing.how-it-works',
        'bootcamp/index.html' => 'marketing.bootcamp',
    ];

    public function handle(): int
    {
        $output = $this->option('output')
            ?: dirname(base_path()).DIRECTORY_SEPARATOR.'landing';

        if (! is_dir($output) && ! mkdir($output, 0755, true) && ! is_dir($output)) {
            $this->error("landing dizini oluşturulamadı: {$output}");

            return self::FAILURE;
        }

        foreach ($this->pages as $relativePath => $viewName) {
            if (! View::exists($viewName)) {
                $this->error("View bulunamadı: {$viewName}");

                return self::FAILURE;
            }

            $html = View::make($viewName)->render();
            $target = $output.DIRECTORY_SEPARATOR.$relativePath;
            $dir = dirname($target);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            File::put($target, $html);
            $this->line("  ✓ {$relativePath}");
        }

        $this->info("landing/ export tamam: {$output}");
        $this->comment('Kaynak: frontend/resources/views/marketing/*.blade.php — landing/ dosyalarını elle düzenlemeyin.');

        return self::SUCCESS;
    }
}
