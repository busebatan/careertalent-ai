<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    public function test_ana_sayfa_acilir(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('CareerTalent AI');
        $response->assertSee('Gelecek kaygını');
    }

    public function test_ozellikler_sayfasi_acilir(): void
    {
        $this->get('/ozellikler')->assertStatus(200)->assertSee('Özellikler');
    }

    public function test_panel_ozet_sayfasi_acilir(): void
    {
        $this->get('/panel')->assertStatus(200)->assertSee('Hoş geldin');
    }
}
