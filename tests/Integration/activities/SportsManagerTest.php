<?php
namespace Tests\Integration\activities;

use App\API\Modules\activities\SportsManager;
use PHPUnit\Framework\TestCase;

class SportsManagerTest extends TestCase
{
    private SportsManager $manager;

    protected function setUp(): void
    {
        if (!getenv('TEST_DB_HOST')) {
            $this->markTestSkipped('TEST_DB_HOST not set — skipping integration test');
        }
        $this->manager = new SportsManager();
    }

    public function testListTeamsReturnsArray(): void
    {
        $result = $this->manager->listTeams();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
