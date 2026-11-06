<?php

namespace Tests\Feature;

use App\Support\ApiDocumentation;
use Tests\TestCase;

class DocsDownloadTest extends TestCase
{
    public function test_all_documents_download_as_standalone_html_with_current_origin_and_shared_examples(): void
    {
        foreach (['email', 'sms', 'portal'] as $channel) {
            $doc = ApiDocumentation::for($channel, 'https://docs.example.test/api/v1/notifications');
            $response = $this->get("https://docs.example.test/docs/{$channel}/download/html")->assertOk();

            $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
            $this->assertSame("attachment; filename=\"message-api-{$channel}.html\"", $response->headers->get('Content-Disposition'));
            $html = $response->getContent();
            $this->assertStringContainsString('<style>', $html);
            $this->assertStringContainsString('data-copy="example-0"', $html);
            $this->assertStringContainsString("document.execCommand('copy')", $html);
            $this->assertStringNotContainsString('<link ', $html);
            $this->assertStringNotContainsString('<script src=', $html);
            $this->assertStringContainsString('https://docs.example.test/api/v1/notifications', $html);
            $this->assertStringContainsString($doc['title'], $html);
            foreach ($doc['examples'] as $example) {
                $this->assertStringContainsString($example['title'], $html);
                $this->assertStringContainsString(json_decode($example['body'], true, 512, JSON_THROW_ON_ERROR)['request_id'], $html);
            }
            foreach ($doc['fields'] as $field) {
                $this->assertStringContainsString($field[0], $html);
            }
            foreach ($doc['errors'] as $error) {
                $this->assertStringContainsString($error[1], $html);
            }
            foreach ($doc['extraFields'] ?? [] as $field) {
                $this->assertStringContainsString($field[0], $html);
            }
            foreach ($doc['operations'] ?? [] as $operation) {
                $this->assertStringContainsString($operation[1], $html);
            }
        }
    }

    public function test_all_documents_download_as_pdf_with_embedded_chinese_font(): void
    {
        foreach (['email', 'sms', 'portal'] as $channel) {
            $response = $this->get("https://docs.example.test/docs/{$channel}/download/pdf")->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertSame("attachment; filename=\"message-api-{$channel}.pdf\"", $response->headers->get('Content-Disposition'));
            $pdf = $response->getContent();
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertGreaterThan(10000, strlen($pdf));
        }
    }

    public function test_download_routes_reject_unknown_channels_and_formats(): void
    {
        $this->get('/docs/unknown/download/html')->assertNotFound();
        $this->get('/docs/email/download/docx')->assertNotFound();
    }
}
