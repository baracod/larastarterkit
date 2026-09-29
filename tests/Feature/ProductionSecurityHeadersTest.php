<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionSecurityHeadersTest extends TestCase
{
    public function test_production_loader_has_a_fresh_csp_nonce_and_pdf_frames_are_allowed(): void
    {
        $this->app->instance('env', 'production');
        $first = $this->get('/')->assertOk();
        $policy = $first->headers->get('Content-Security-Policy');
        $this->assertSame(1, preg_match("/script-src 'self' 'nonce-([^']+)'/", $policy, $matches));
        $first->assertSee('nonce="'.$matches[1].'"', false);
        $this->assertStringContainsString("frame-src 'self' blob:", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('https://fonts.googleapis.com', $policy);
        $second = $this->get('/')->assertOk();
        $this->assertNotSame($policy, $second->headers->get('Content-Security-Policy'));
    }
}
