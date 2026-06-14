<?php

namespace Tests\Unit;

use App\Services\EspnApiService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EspnApiServiceParseMinuteTest extends TestCase
{
    private EspnApiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EspnApiService;
    }

    private function parseMinute(string $raw): string
    {
        $method = new ReflectionMethod(EspnApiService::class, 'parseMinute');
        $method->setAccessible(true);

        return $method->invoke($this->service, $raw);
    }

    public function test_plain_minute(): void
    {
        $this->assertSame('45', $this->parseMinute('45'));
    }

    public function test_minute_with_apostrophe(): void
    {
        $this->assertSame('45', $this->parseMinute("45'"));
    }

    public function test_extra_time_minute_with_apostrophe(): void
    {
        $this->assertSame('45+5', $this->parseMinute("45+5'"));
    }

    public function test_extra_time_minute_without_apostrophe(): void
    {
        $this->assertSame('90+3', $this->parseMinute('90+3'));
    }

    public function test_mm_colon_ss_format(): void
    {
        $this->assertSame('45', $this->parseMinute('45:00'));
    }

    public function test_extra_time_mm_colon_ss_format(): void
    {
        $this->assertSame('90+3', $this->parseMinute('90+3:00'));
    }

    public function test_trims_whitespace(): void
    {
        $this->assertSame('78', $this->parseMinute('  78  '));
    }
}
