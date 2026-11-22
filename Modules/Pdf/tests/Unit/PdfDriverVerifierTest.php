<?php

namespace Modules\Pdf\Tests\Unit;

use Modules\Pdf\Services\Driver\PdfDriverConfigurator;
use Modules\Pdf\Services\Driver\PdfDriverVerifier;
use Modules\Pdf\Services\Rendering\ArabicHtmlProcessor;
use ReflectionMethod;
use Tests\TestCase;

class PdfDriverVerifierTest extends TestCase
{
    public function test_receipt_size_is_read_from_a_compressed_pdf_object_stream(): void
    {
        $objectStream = gzcompress('1 0 <</Type /Page /MediaBox [0 0 226.771654 566.929134]>>');
        $pdf = "%PDF-1.7\n1 0 obj\n<</Type /ObjStm /Filter /FlateDecode>>\nstream\n"
            .$objectStream
            ."\nendstream\nendobj\n%%EOF";
        $verifier = new PdfDriverVerifier(new PdfDriverConfigurator, new ArabicHtmlProcessor);
        $method = new ReflectionMethod($verifier, 'hasReceiptSize');

        $this->assertTrue($method->invoke($verifier, $pdf));
    }
}
