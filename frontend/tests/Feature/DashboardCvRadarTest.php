<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardCvRadarTest extends TestCase
{
    public function test_dashboard_shows_empty_state_without_cv_analysis(): void
    {
        $response = $this->get(route('panel.dashboard'));
        $response->assertOk()
            ->assertSee('data-dashboard-cv-empty', false)
            ->assertSee(__('panel.skill_radar.empty_title'), false)
            ->assertSee('href="'.route('panel.cv-builder').'"', false)
            ->assertDontSee('data-dashboard-cv-actions', false)
            ->assertDontSee('id="yetenek-radari"', false);
    }

    public function test_dashboard_includes_mobile_navigation_shell(): void
    {
        $this->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee('id="panel-sidebar"', false)
            ->assertSee('panel-mobile-sidebar', false)
            ->assertSee('data-lucide="menu"', false)
            ->assertSee(__('panel.nav.open_menu'), false);
    }

    public function test_dashboard_shows_api_radar_after_cv_analysis(): void
    {
        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready', 'current_role' => 'İş Analisti', 'created_at' => '2026-07-04T00:00:00Z',
                'radar' => [['label' => 'Excel', 'score' => 82, 'target' => 70], ['label' => 'İletişim', 'score' => 90, 'target' => 90]], 'career_ladder' => [['title' => 'İş Analisti', 'readiness' => 68]],
            ], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $response = $this->get(route('panel.dashboard'));
        $response->assertOk()
            ->assertSee('id="yetenek-radari"', false)
            ->assertSee('data-skill-radar-layout="split"', false)
            ->assertSee('data-skill-radar-alignment="intro-centered"', false)
            ->assertSee('md:grid-cols-[minmax(0,35rem)_minmax(15rem,18rem)]', false)
            ->assertSee('max-w-[35rem]', false)
            ->assertSee('md:mx-auto', false)
            ->assertDontSee('md:ml-10', false)
            ->assertDontSee('md:ml-auto', false)
            ->assertSee('data-dashboard-cv-empty', false)
            ->assertDontSee('data-dashboard-cv-actions', false)
            ->assertSee('İş Analisti', false)
            ->assertSee('%86', false)
            ->assertSee(__('panel.skill_radar.from_cv_analysis'), false)
            ->assertDontSee(__('panel.skill_radar.subtitle', ['role' => 'İş Analisti']));
    }

    public function test_long_radar_labels_are_rendered_outside_the_plot_safe_zone(): void
    {
        $labels = [
            'Frontend Geliştirme',
            'Mobil Geliştirme (Flutter / Kotlin)',
            'Backend Geliştirme (PHP, Laravel)',
            'Yapay Zeka Makine Öğrenmesi',
            'Proje Yönetimi',
            'Girişimcilik',
            'Sunucu ve DevOps',
        ];

        Http::fake([
            'http://localhost:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://localhost:8000/api/v1/career/analysis/current' => Http::response([
                'status' => 'ready',
                'current_role' => 'Yazılım Geliştirici',
                'created_at' => '2026-07-20T00:00:00Z',
                'radar' => array_map(
                    fn (string $label): array => ['label' => $label, 'score' => 70, 'target' => 85],
                    $labels,
                ),
                'career_ladder' => [],
            ], 200),
            'http://localhost:8000/api/v1/career/targets' => Http::response([], 200),
            'http://localhost:8000/*' => Http::response([], 200),
        ]);

        $response = $this->get(route('panel.dashboard'));
        $response->assertOk()->assertSee('data-radar-label-safe-layout', false);

        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $plot = $xpath->query('//*[@data-radar-plot-safe-zone]')->item(0);
        $boxes = $xpath->query('//*[@data-radar-label-box]');

        $this->assertNotNull($plot);
        $this->assertCount(count($labels), $boxes);

        $plotMinX = (float) $plot->getAttribute('data-radar-plot-min-x');
        $plotMinY = (float) $plot->getAttribute('data-radar-plot-min-y');
        $plotMaxX = (float) $plot->getAttribute('data-radar-plot-max-x');
        $plotMaxY = (float) $plot->getAttribute('data-radar-plot-max-y');
        $verticalLabelGap = 8.0;
        $svgEdgeInset = 4.0;
        $svgSize = 360.0;

        foreach ($boxes as $box) {
            $x = (float) $box->getAttribute('x');
            $y = (float) $box->getAttribute('y');
            $width = (float) $box->getAttribute('width');
            $height = (float) $box->getAttribute('height');
            $overlapsPlot = $x < $plotMaxX && ($x + $width) > $plotMinX
                && $y < $plotMaxY && ($y + $height) > $plotMinY;

            $this->assertFalse($overlapsPlot, 'Radar etiketi grafik güvenli alanına girmemeli: '.$box->textContent);

            match ($box->getAttribute('data-radar-label-side')) {
                'top' => $this->assertEqualsWithDelta(
                    $verticalLabelGap,
                    $plotMinY - ($y + $height),
                    0.01,
                    'Üst radar etiketi grafiğe yakın durmalı: '.$box->textContent,
                ),
                'bottom' => $this->assertEqualsWithDelta(
                    $verticalLabelGap,
                    $y - $plotMaxY,
                    0.01,
                    'Alt radar etiketi grafiğe yakın durmalı: '.$box->textContent,
                ),
                'left' => $this->assertEqualsWithDelta(
                    $svgEdgeInset,
                    $x,
                    0.01,
                    'Sol radar etiketi sabit kalmalı: '.$box->textContent,
                ),
                'right' => $this->assertEqualsWithDelta(
                    $svgEdgeInset,
                    $svgSize - ($x + $width),
                    0.01,
                    'Sağ radar etiketi sabit kalmalı: '.$box->textContent,
                ),
                default => $this->fail('Bilinmeyen radar etiket yönü: '.$box->textContent),
            };
        }
    }
}
