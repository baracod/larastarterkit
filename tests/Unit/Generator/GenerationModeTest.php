<?php

declare(strict_types=1);

namespace Tests\Unit\Generator;

use Baracod\Larastarterkit\Generator\GenerationMode;
use PHPUnit\Framework\TestCase;

final class GenerationModeTest extends TestCase
{
    public function test_expand_fullstack_returns_fourteen_modes(): void
    {
        $modes = GenerationMode::expandFullstack();

        $this->assertCount(14, $modes);
    }

    public function test_expand_fullstack_does_not_contain_bundle_modes(): void
    {
        $modes = GenerationMode::expandFullstack();

        $this->assertNotContains(GenerationMode::BACKEND_FULL, $modes);
        $this->assertNotContains(GenerationMode::FRONTEND_FULL, $modes);
        $this->assertNotContains(GenerationMode::FULLSTACK, $modes);
    }

    public function test_for_strategy_backend_returns_eight_modes(): void
    {
        $modes = GenerationMode::forStrategy('backend');

        $this->assertCount(8, $modes);
        $this->assertContains(GenerationMode::BACKEND_MIGRATION, $modes);
        $this->assertContains(GenerationMode::BACKEND_FACTORY, $modes);
        $this->assertContains(GenerationMode::BACKEND_TEST, $modes);
        $this->assertContains(GenerationMode::BACKEND_MODEL, $modes);
        $this->assertContains(GenerationMode::BACKEND_REQUEST, $modes);
        $this->assertContains(GenerationMode::BACKEND_CONTROLLER, $modes);
        $this->assertContains(GenerationMode::BACKEND_ROUTE, $modes);
        $this->assertContains(GenerationMode::BACKEND_PERMISSIONS, $modes);
    }

    public function test_for_strategy_frontend_returns_six_modes(): void
    {
        $modes = GenerationMode::forStrategy('frontend');

        $this->assertCount(6, $modes);
        $this->assertContains(GenerationMode::FRONTEND_MENU, $modes);
        $this->assertContains(GenerationMode::FRONTEND_I18N, $modes);
        $this->assertContains(GenerationMode::FRONTEND_TYPES, $modes);
        $this->assertContains(GenerationMode::FRONTEND_API, $modes);
        $this->assertContains(GenerationMode::FRONTEND_INDEX, $modes);
        $this->assertContains(GenerationMode::FRONTEND_ADDOREDIT, $modes);
    }

    public function test_for_strategy_fullstack_returns_fourteen_modes(): void
    {
        $modes = GenerationMode::forStrategy('fullstack');

        $this->assertCount(14, $modes);
    }

    public function test_for_strategy_unknown_returns_empty_array(): void
    {
        $modes = GenerationMode::forStrategy('nonexistent');

        $this->assertSame([], $modes);
    }

    public function test_label_returns_non_empty_string_for_every_case(): void
    {
        $cases = GenerationMode::cases();

        foreach ($cases as $case) {
            $this->assertNotEmpty($case->label(), "Label for {$case->name} should not be empty");
        }
    }

    public function test_group_returns_valid_group_for_every_case(): void
    {
        $backendModes = [
            GenerationMode::BACKEND_MODEL,
            GenerationMode::BACKEND_REQUEST,
            GenerationMode::BACKEND_CONTROLLER,
            GenerationMode::BACKEND_ROUTE,
            GenerationMode::BACKEND_PERMISSIONS,
            GenerationMode::BACKEND_FULL,
        ];

        $frontendModes = [
            GenerationMode::FRONTEND_TYPES,
            GenerationMode::FRONTEND_API,
            GenerationMode::FRONTEND_INDEX,
            GenerationMode::FRONTEND_ADDOREDIT,
            GenerationMode::FRONTEND_FULL,
        ];

        foreach ($backendModes as $mode) {
            $this->assertSame('backend', $mode->group(), "{$mode->name} should be in backend group");
        }

        foreach ($frontendModes as $mode) {
            $this->assertSame('frontend', $mode->group(), "{$mode->name} should be in frontend group");
        }

        $this->assertSame('fullstack', GenerationMode::FULLSTACK->group());
    }

    public function test_expand_fullstack_and_for_strategy_fullstack_have_same_content(): void
    {
        $viaExpand = array_map(fn (GenerationMode $m) => $m->value, GenerationMode::expandFullstack());
        $viaStrategy = array_map(fn (GenerationMode $m) => $m->value, GenerationMode::forStrategy('fullstack'));

        sort($viaExpand);
        sort($viaStrategy);

        $this->assertSame($viaExpand, $viaStrategy);
    }

    public function test_aliases_are_parsed_and_unknown_values_reported(): void
    {
        $parsed = GenerationMode::parseAliases(' model, form ,i18n,model, bogus');

        $this->assertSame([GenerationMode::BACKEND_MODEL, GenerationMode::FRONTEND_ADDOREDIT, GenerationMode::FRONTEND_I18N], $parsed['modes']);
        $this->assertSame(['bogus'], $parsed['unknown']);
    }

    public function test_every_executable_mode_has_a_distinct_order(): void
    {
        $orders = array_map(static fn (GenerationMode $m) => $m->order(), GenerationMode::expandFullstack());

        $this->assertSame(count($orders), count(array_unique($orders)));
        $this->assertLessThan(GenerationMode::BACKEND_MODEL->order(), GenerationMode::BACKEND_MIGRATION->order());
        $this->assertLessThan(GenerationMode::BACKEND_TEST->order(), GenerationMode::BACKEND_FACTORY->order());
    }
}
