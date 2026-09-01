<?php
/**
 * Public academic calendar PDF download.
 *
 * Flow: PrintService generates the PDF → DownloadService streams it.
 * No authentication required — this is a public resource.
 */
require_once __DIR__ . '/vendor/autoload.php';

use App\Config\Config;
use App\API\Services\PrintService;
use App\API\Services\DownloadService;

try {
    Config::init();

    $printService = new PrintService();
    $downloadService = new DownloadService();

    $yearId = !empty($_GET['year']) ? (int) $_GET['year'] : null;
    $data = $yearId ? ['academicYear' => $yearId] : [];

    // PrintService generates the PDF on disk and returns the absolute file path
    $pdfPath = $printService->printAcademicCalendar($data);

    // Determine a friendly download filename
    $yearLabel = 'calendar';
    if ($yearId) {
        try {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("SELECT year_code FROM academic_years WHERE id = ?");
            $stmt->execute([$yearId]);
            $code = $stmt->fetchColumn();
            if ($code) $yearLabel = $code;
        } catch (\Throwable $e) { /* use default label */ }
    }

    $filename = 'Kingsway_Academic_Calendar_' . $yearLabel . '.pdf';

    // DownloadService streams the file to the browser with proper headers
    $downloadService->streamAbsolutePath($pdfPath, $filename, 'application/pdf', 'attachment');

    // streamAbsolutePath() calls exit, so nothing below executes

} catch (\Throwable $e) {
    \App\API\Services\Logger::legacyError('[calendar_download] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Download Error</title></head><body>'
       . '<h2>Unable to generate calendar PDF</h2>'
       . '<p>Please try again later or contact the school office.</p>'
       . '</body></html>';
    exit;
}
