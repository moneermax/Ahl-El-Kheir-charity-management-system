<?php
declare(strict_types=1);

/**
 * Build a locally generated QR image for an existing sponsor.
 *
 * The QR contains only the URL of the existing permission-checked sponsor
 * profile. It never embeds the sponsor's private details.
 */
if (!function_exists('akSponsorQrDataUri')) {
    function akSponsorQrDataUri(int $sponsorId): string
    {
        if ($sponsorId <= 0 || !defined('APP_URL')) {
            return '';
        }

        $barcodeLibrary = dirname(__DIR__, 2) . '/TCPDF/tcpdf_barcodes_2d.php';
        if (!is_file($barcodeLibrary)) {
            error_log('Sponsor QR unavailable: bundled TCPDF 2D barcode library is missing.');
            return '';
        }

        require_once $barcodeLibrary;
        if (!class_exists('TCPDF2DBarcode')) {
            error_log('Sponsor QR unavailable: TCPDF2DBarcode class could not be loaded.');
            return '';
        }

        $targetUrl = APP_URL . 'modules/sponsors/view.php?id=' . $sponsorId;
        $barcode = new TCPDF2DBarcode($targetUrl, 'QRCODE,M');
        $svg = $barcode->getBarcodeSVGcode(4, 4, '#000000');

        // Inline SVG image data should not carry an XML declaration or external DTD.
        $svg = preg_replace('/<\?xml[^>]*\?>\s*/i', '', $svg) ?? $svg;
        $svg = preg_replace('/<!DOCTYPE\s+svg\b[^>]*>\s*/i', '', $svg) ?? $svg;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
