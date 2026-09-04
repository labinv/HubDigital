<?php

return [
    'driver' => env('DOCUMENT_EXTRACTION_DRIVER', 'local'),
    'minimum_text_length' => (int) env('DOCUMENT_EXTRACTION_MIN_TEXT', 80),
    'ocr_languages' => env('DOCUMENT_EXTRACTION_OCR_LANGUAGES', 'spa+eng'),
    'ocr_max_pages' => (int) env('DOCUMENT_EXTRACTION_OCR_MAX_PAGES', 25),
    'ocr_dpi' => (int) env('DOCUMENT_EXTRACTION_OCR_DPI', 200),
];
