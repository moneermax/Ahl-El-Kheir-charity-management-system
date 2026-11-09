<?php
declare(strict_types=1);

// Regression coverage for the large sponsorship arrears HTML response.
// Each sponsorship code is wrapped in <code>, which the global translator protects.
$_COOKIE['ak_lang'] = 'ar';
$_GET = [];
$_SERVER['SCRIPT_NAME'] = '/modules/sponsorships/arrears.php';

require_once dirname(__DIR__) . '/config/lang.php';

$blockCount = 3560;
$html = '<!DOCTYPE html><html><body><table><tbody>';
for ($i = 1; $i <= $blockCount; $i++) {
    $code = 'IMP-SH-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT);
    $html .= '<tr><td><code>' . $code . '</code></td><td>بيانات الكفالة</td></tr>';
}
$html .= '</tbody></table></body></html>';

$translated = ak_translate_page($html);
if (substr_count($translated, '<code>') !== $blockCount) {
    throw new RuntimeException('Protected code block count changed during page translation.');
}
if (str_contains($translated, '__AK_I18N_PROTECTED_')) {
    throw new RuntimeException('A protected-block placeholder leaked into the rendered page.');
}
if (!str_contains($translated, '<code>IMP-SH-000001</code>')
    || !str_contains($translated, '<code>IMP-SH-003560</code>')) {
    throw new RuntimeException('Protected sponsorship codes were changed or lost.');
}

echo 'PASS | protected_code_blocks=' . $blockCount . ' | restoration=PASS | placeholder_leak=NONE' . PHP_EOL;
