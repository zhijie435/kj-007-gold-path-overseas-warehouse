<?php

namespace Tests\Unit\Models;

use App\Models\OverseaWarehouseConfig;
use PHPUnit\Framework\TestCase;

class OverseaWarehouseConfigTest extends TestCase
{
    protected function createConfig(array $attrs = []): OverseaWarehouseConfig
    {
        $config = new OverseaWarehouseConfig();
        foreach ($attrs as $key => $value) {
            $config->{$key} = $value;
        }
        return $config;
    }

    public function test_isActive_returns_true_for_active(): void
    {
        $config = $this->createConfig(['status' => 'active']);
        $this->assertTrue($config->isActive());
    }

    public function test_isActive_returns_false_for_other_statuses(): void
    {
        $config = $this->createConfig(['status' => 'testing']);
        $this->assertFalse($config->isActive());

        $config2 = $this->createConfig(['status' => 'error']);
        $this->assertFalse($config2->isActive());
    }

    public function test_isTesting_returns_true(): void
    {
        $config = $this->createConfig(['status' => 'testing']);
        $this->assertTrue($config->isTesting());
    }

    public function test_isError_returns_true(): void
    {
        $config = $this->createConfig(['status' => 'error']);
        $this->assertTrue($config->isError());
    }

    public function test_getSupportedCountriesArray_decodes_json(): void
    {
        $config = $this->createConfig(['supported_countries' => json_encode(['US', 'CA', 'GB'])]);
        $this->assertSame(['US', 'CA', 'GB'], $config->getSupportedCountriesArray());
    }

    public function test_getSupportedCountriesArray_empty_string_returns_empty(): void
    {
        $config = $this->createConfig();
        $this->assertEmpty($config->getSupportedCountriesArray());
    }

    public function test_getSupportedCountriesArray_invalid_json_returns_empty(): void
    {
        $config = $this->createConfig(['supported_countries' => 'not-json']);
        $this->assertEmpty($config->getSupportedCountriesArray());
    }

    public function test_supportsCountry_matches_case_insensitive(): void
    {
        $config = $this->createConfig(['supported_countries' => json_encode(['US', 'CA', 'GB'])]);
        $this->assertTrue($config->supportsCountry('US'));
        $this->assertTrue($config->supportsCountry('us'));
        $this->assertTrue($config->supportsCountry('Us'));
        $this->assertFalse($config->supportsCountry('DE'));
    }

    public function test_supportsCountry_empty_list_supports_all(): void
    {
        $config = $this->createConfig();
        $this->assertTrue($config->supportsCountry('US'));
        $this->assertTrue($config->supportsCountry('ANY'));
    }
}
