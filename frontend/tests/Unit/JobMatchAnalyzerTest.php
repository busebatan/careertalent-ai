<?php

namespace Tests\Unit;

use App\Services\JobMatchAnalyzer;
use Tests\TestCase;

class JobMatchAnalyzerTest extends TestCase
{
    public function test_kariyer_net_catalog_entry_returns_match_score(): void
    {
        $analyzer = new JobMatchAnalyzer(['SQL', 'Python', 'Excel', 'Tableau', 'İngilizce'], 42);

        $result = $analyzer->analyze('https://www.kariyer.net/is-ilani/junior-veri-analisti-fintech');

        $this->assertSame('Junior Veri Analisti', $result['title']);
        $this->assertSame('kariyer.net', $result['source']);
        $this->assertGreaterThanOrEqual(50, $result['match_score']);
        $this->assertContains('SQL', $result['matched_skills']);
    }

    public function test_invalid_url_returns_validation_error(): void
    {
        $analyzer = new JobMatchAnalyzer(['SQL'], 42);

        $this->expectException(\InvalidArgumentException::class);

        $analyzer->analyze('   ');
    }

    public function test_linkedin_jobs_url_is_recognized(): void
    {
        $analyzer = new JobMatchAnalyzer(['SQL', 'Python', 'Pandas'], 42);

        $result = $analyzer->analyze('https://www.linkedin.com/jobs/view/123456');

        $this->assertSame('LinkedIn', $result['source']);
        $this->assertNotEmpty($result['title']);
    }
}
