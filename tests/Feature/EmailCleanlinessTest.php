<?php

namespace Tests\Feature;

use App\Support\Email\HtmlToText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailCleanlinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_is_converted_to_readable_plain_text(): void
    {
        $html = '<html><head><title>T</title><style>p{color:red}</style></head><body>'
            .'<p>Your code is <strong>123456</strong>.</p><p>Visit <a href="https://example.com/verify">our site</a>&nbsp;now.</p>'
            .'<script>alert(1)</script></body></html>';

        $text = HtmlToText::convert($html);

        $this->assertStringContainsString('Your code is 123456.', $text);
        $this->assertStringContainsString('our site (https://example.com/verify)', $text);
        $this->assertStringNotContainsString('color:red', $text);
        $this->assertStringNotContainsString('alert', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function test_email_logo_route_serves_an_image(): void
    {
        $response = $this->get(route('email.logo'));

        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }
}
