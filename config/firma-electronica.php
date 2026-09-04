<?php

return [
    'exigir_certificado_confiable' => env(
        'FIRMA_EXIGIR_CERTIFICADO_CONFIABLE',
        env('APP_ENV') === 'production',
    ),
    'pdfsig_binary' => env('PDFSIG_BINARY'),
    'nss_dir' => env('FIRMA_NSS_DIR'),
    'pdfinfo_binary' => env('PDFINFO_BINARY'),
    'pdftotext_binary' => env('PDFTOTEXT_BINARY'),
    'pdftoppm_binary' => env('PDFTOPPM_BINARY'),
    'qpdf_binary' => env('QPDF_BINARY'),
    'render_dpi' => 110,
    'max_pages' => (int) env('FIRMA_MAX_PAGES', 40),
    'max_page_points' => (int) env('FIRMA_MAX_PAGE_POINTS', 1440),
    'max_render_pixels' => (int) env('FIRMA_MAX_RENDER_PIXELS', 100000000),
];
