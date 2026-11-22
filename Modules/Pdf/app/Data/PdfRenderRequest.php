<?php

namespace Modules\Pdf\Data;

use Modules\Pdf\Enums\PdfDocumentType;

readonly class PdfRenderRequest
{
    /**
     * Create an immutable PDF render request.
     *
     * @param  array<string, mixed>  $data
     * @param  array{width:float,height:float,unit?:string}|null  $paperSize
     * @param  array{top:float,right:float,bottom:float,left:float,unit?:string}  $margins
     * @param  array{title?:string,author?:string,subject?:string,keywords?:string,creator?:string}  $metadata
     */
    public function __construct(
        public PdfDocumentType $documentType,
        public string $view,
        public array $data = [],
        public ?string $format = 'a4',
        public ?array $paperSize = null,
        public string $orientation = 'portrait',
        public array $margins = ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0, 'unit' => 'mm'],
        public array $metadata = [],
    ) {}
}
