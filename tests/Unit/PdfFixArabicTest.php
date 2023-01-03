<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PdfFixArabicTest extends TestCase
{
    public function test_leaves_english_html_unchanged(): void
    {
        $html = '<html><head></head><body><p>Invoice #123</p></body></html>';

        $this->assertSame($html, pdfFixArabic($html));
    }

    public function test_shapes_arabic_and_forces_dejavu_font(): void
    {
        $html = '<html><head></head><body style="font-family: Verdana, Arial, Helvetica, sans-serif;">'
            . '<p>ويمثله في هذا العقد</p></body></html>';

        $fixed = pdfFixArabic($html);

        $this->assertStringContainsString('data-pdf-arabic-fixed', $fixed);
        $this->assertStringContainsString('DejaVu Sans', $fixed);
        $this->assertStringNotContainsString('ويمثله في هذا العقد', $fixed);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $fixed);
    }

    public function test_decodes_arabic_numeric_entities_before_shaping(): void
    {
        $html = '<html><body>&#x0648;&#x064A;&#x0645;&#x062B;&#x0644;&#x0647;</body></html>';

        $fixed = pdfFixArabic($html);

        $this->assertStringContainsString('data-pdf-arabic-fixed', $fixed);
        $this->assertStringNotContainsString('&#x0648;', $fixed);
    }

    public function test_is_idempotent(): void
    {
        $html = '<html><head></head><body>ويمثله في هذا العقد</body></html>';

        $once = pdfFixArabic($html);
        $twice = pdfFixArabic($once);

        $this->assertSame($once, $twice);
    }
}
