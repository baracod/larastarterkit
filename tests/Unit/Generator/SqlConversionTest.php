<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Baracod\Larastarterkit\Generator\Traits\SqlConversion;
use PHPUnit\Framework\TestCase;

final class SqlConversionTest extends TestCase
{
    use SqlConversion;

    public function test_varchar_maps_to_string(): void
    {
        $this->assertSame('string', $this->sqlToPhpType('varchar'));
    }

    public function test_int_maps_to_int(): void
    {
        $this->assertSame('int', $this->sqlToPhpType('int'));
    }

    public function test_bigint_maps_to_int(): void
    {
        $this->assertSame('int', $this->sqlToPhpType('bigint'));
    }

    public function test_datetime_maps_to_datetime(): void
    {
        $this->assertSame('datetime', $this->sqlToPhpType('datetime'));
    }

    public function test_timestamp_maps_to_datetime(): void
    {
        $this->assertSame('datetime', $this->sqlToPhpType('timestamp'));
    }

    public function test_time_maps_to_time_not_integer(): void
    {
        $this->assertSame('time', $this->sqlToPhpType('time'));
    }

    public function test_year_maps_to_integer(): void
    {
        $this->assertSame('integer', $this->sqlToPhpType('year'));
    }

    public function test_tinyint_one_maps_to_bool(): void
    {
        $this->assertSame('bool', $this->sqlToPhpType('tinyint(1)'));
    }

    public function test_json_maps_to_array(): void
    {
        $this->assertSame('array', $this->sqlToPhpType('json'));
    }

    public function test_enum_maps_to_array(): void
    {
        $this->assertSame('array', $this->sqlToPhpType('enum'));
    }

    public function test_unknown_type_maps_to_mixed(): void
    {
        $this->assertSame('mixed', $this->sqlToPhpType('some_unknown_type'));
    }

    public function test_decimal_maps_to_float(): void
    {
        $this->assertSame('float', $this->sqlToPhpType('decimal'));
    }

    public function test_text_maps_to_string(): void
    {
        $this->assertSame('string', $this->sqlToPhpType('text'));
    }

    public function test_boolean_maps_to_bool(): void
    {
        $this->assertSame('bool', $this->sqlToPhpType('boolean'));
    }

    public function test_float_maps_to_float(): void
    {
        $this->assertSame('float', $this->sqlToPhpType('float'));
    }

    public function test_date_maps_to_date(): void
    {
        $this->assertSame('date', $this->sqlToPhpType('date'));
    }
}
