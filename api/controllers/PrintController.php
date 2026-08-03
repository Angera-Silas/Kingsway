<?php
namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use function App\API\Includes\formatResponse;

/**
 * Print Controller
 * 
 * Provides API endpoints for server-side PDF generation and export.
 * Complements the client-side PrintManager for programmatic printing.
 * 
 * Endpoints:
 * - POST /api/print/table - Generate PDF from table data
 * - POST /api/print/record - Generate PDF from record data
 * - POST /api/print/certificate - Generate certificate PDF
 * - POST /api/print/export-csv - Generate CSV server-side
 * 
 * @package App\API\Controllers
 */
class PrintController extends BaseController
{
    private function guardPrint(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }
    
    /**
     * Generate PDF from table data
     * 
     * POST /api/print/table
     * 
     * Request body:
     * {
     *   "title": "Report Title",
     *   "subtitle": "Report Subtitle",
     *   "columns": [{"key": "name", "label": "Name"}],
     *   "rows": [{"name": "John"}],
     *   "summary": {"Total": "100"},
     *   "filters": {"Date": "2024-01-01"},
     *   "orientation": "landscape",
     *   "paperSize": "A4",
     *   "filename": "report"
     * }
     * 
     * @return array Response with PDF URL
     */
    public function postTable($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['rows'])) {
                return formatResponse(false, null, 'No data provided');
            }
            
            if (empty($data['columns'])) {
                return formatResponse(false, null, 'No columns provided');
            }
            
            $pdfPath = $this->prints()->printTable($data['rows'], $data);
            
            // Convert to relative URL
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
            ], 'PDF generated successfully');
            
        } catch (\Exception $e) {
            error_log('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }
    
    /**
     * Generate PDF from record data
     * 
     * POST /api/print/record
     * 
     * Request body:
     * {
     *   "title": "Record Title",
     *   "subtitle": "Record Subtitle",
     *   "sections": [
     *     {
     *       "title": "Section Title",
     *       "fields": [
     *         {"label": "Name", "value": "John"}
     *       ]
     *     }
     *   ],
     *   "orientation": "portrait",
     *   "paperSize": "A4",
     *   "filename": "record"
     * }
     * 
     * @return array Response with PDF URL
     */
    public function postRecord($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['sections'])) {
                return formatResponse(false, null, 'No sections provided');
            }
            
            $pdfPath = $this->prints()->printRecord($data, $data);
            
            // Convert to relative URL
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
            ], 'PDF generated successfully');
            
        } catch (\Exception $e) {
            error_log('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }
    
    /**
     * Generate certificate PDF
     * 
     * POST /api/print/certificate
     * 
     * Request body:
     * {
     *   "type": "academic_excellence|sports_achievement|graduation",
     *   "recipientName": "John Doe",
     *   "achievement": "Outstanding Performance",
     *   "academicYear": "2024",
     *   "certificateNumber": "CERT-001",
     *   "dateAwarded": "2024-01-15"
     * }
     * 
     * @return array Response with PDF URL
     */
    public function postCertificate($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['type'])) {
                return formatResponse(false, null, 'Certificate type is required');
            }
            
            if (empty($data['recipientName'])) {
                return formatResponse(false, null, 'Recipient name is required');
            }
            
            $pdfPath = $this->prints()->printCertificate($data['type'], $data);
            
            // Convert to relative URL
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
            ], 'Certificate generated successfully');
            
        } catch (\Exception $e) {
            error_log('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }
    
    /**
     * Generate a student portfolio PDF using the portfolio template.
     *
     * POST /api/print/portfolio
     *
     * Fetches cumulative portfolio data for the student and renders the
     * portfolio_main.php template as a PDF.
     *
     * Request body: { "student_id": 123 }
     *
     * @return array Response with PDF URL
     */
    public function postPortfolio($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];
            $studentId = (int)($data['student_id'] ?? ($id ?: 0));
            if (!$studentId) {
                return formatResponse(false, null, 'Student ID is required');
            }

            // Fetch cumulative portfolio data from the database
            $student = $this->db->query(
                "SELECT s.id, s.first_name, s.last_name, s.admission_no, s.photo,
                        c.name AS class_name, cs.stream_name
                 FROM students s
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id
                 LEFT JOIN classes c ON c.id = cs.class_id
                 WHERE s.id = :sid",
                [':sid' => $studentId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            $portfolios = $this->db->query(
                "SELECT * FROM portfolios WHERE student_id = :sid ORDER BY academic_year DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $artifacts = $this->db->query(
                "SELECT pa.*, cc.name AS competency_name, cv.name AS value_name,
                        p.academic_year
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                 LEFT JOIN core_values cv ON cv.id = pa.value_id
                 WHERE p.student_id = :sid
                 ORDER BY pa.upload_date DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $compSummary = $this->db->query(
                "SELECT cc.name AS competency_name,
                        COUNT(pa.id) AS artifact_count,
                        ROUND(AVG(pa.rating), 1) AS avg_rating,
                        MAX(pa.rating) AS highest_rating
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 JOIN core_competencies cc ON cc.id = pa.competency_id
                 WHERE p.student_id = :sid AND pa.competency_id IS NOT NULL
                 GROUP BY cc.id, cc.name
                 ORDER BY artifact_count DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $valsSummary = $this->db->query(
                "SELECT cv.name AS value_name, COUNT(pa.id) AS artifact_count
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 JOIN core_values cv ON cv.id = pa.value_id
                 WHERE p.student_id = :sid AND pa.value_id IS NOT NULL
                 GROUP BY cv.id, cv.name
                 ORDER BY artifact_count DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $fbRows = $this->db->query(
                "SELECT pa.teacher_feedback
                 FROM portfolio_artifacts pa
                 JOIN portfolios p ON p.id = pa.portfolio_id
                 WHERE p.student_id = :sid
                   AND pa.teacher_feedback IS NOT NULL
                   AND pa.teacher_feedback != ''
                 ORDER BY pa.upload_date DESC",
                [':sid' => $studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $teacherFeedback = implode("\n---\n", array_column($fbRows, 'teacher_feedback'));

            $years = array_unique(array_filter(array_column($artifacts, 'academic_year')));
            sort($years);
            $yearRange = count($years) > 1 ? min($years) . ' \u2013 ' . max($years) : (string)(min($years) ?: date('Y'));

            $portfolioData = [
                'student' => $student,
                'portfolio' => $portfolios[0] ?? [],
                'allArtifacts' => $artifacts,
                'competencySummary' => $compSummary,
                'valuesSummary' => $valsSummary,
                'teacherFeedback' => $teacherFeedback,
                'yearRange' => $yearRange,
                'totalArtifacts' => count($artifacts),
            ];

            $pdfPath = $this->prints()->printPortfolio($portfolioData, $data);
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
            ], 'Portfolio PDF generated successfully');

        } catch (\Exception $e) {
            error_log('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate a CBC report card PDF for the given level.
     *
     * POST /api/print/report-card
     *
     * Request body:
     * {
     *   "level": "PP|LowerPrimary|UpperPrimary|JuniorSecondary",
     *   "student": {...}, "term": {...}, "scores": [...],
     *   "competencies": [...], "values": [...],
     *   "attendance": {...}, "comments": {...},
     *   "filename": "optional-filename"
     * }
     *
     * @return array Response with PDF URL
     */
    public function postReportCard($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            $level = (string)($data['level'] ?? '');
            if (!in_array($level, ['PP', 'LowerPrimary', 'UpperPrimary', 'JuniorSecondary'], true)) {
                return formatResponse(false, null, 'Invalid or missing level. Use: PP, LowerPrimary, UpperPrimary, or JuniorSecondary.');
            }

            if (empty($data['student'])) {
                return formatResponse(false, null, 'Student information is required');
            }

            $pdfPath = $this->prints()->printReportCard($data, $data);
            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
            ], 'Report card PDF generated successfully');

        } catch (\Exception $e) {
            error_log('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate PDF from arbitrary HTML content.
     *
     * POST /api/print/html
     *
     * Request body:
     * {
     *   "html": "<div>...</div>",
     *   "isFullDocument": false,
     *   "orientation": "portrait",
     *   "paperSize": "A4",
     *   "filename": "report_cards_bulk",
     *   "title": "Report Cards"
     * }
     *
     * @return array Response with PDF URL
     */
    public function postHtml($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['html'])) {
                return formatResponse(false, null, 'No HTML content provided');
            }

            $pdfPath = $this->prints()->printHtml($data, $data);

            $pdfUrl = $this->getPrintUrl($pdfPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ],
                'files' => [[
                    'filename' => basename($pdfPath),
                    'mime_type' => 'application/pdf',
                    'url' => $pdfUrl,
                    'download_url' => $pdfUrl,
                ]],
                'pdf_url' => $pdfUrl,
                'download_url' => $pdfUrl,
                'filename' => basename($pdfPath),
            ], 'PDF generated successfully');

        } catch (\Exception $e) {
            error_log('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Export data to CSV (server-side)
     * 
     * POST /api/print/export-csv
     * 
     * Request body:
     * {
     *   "data": [{"name": "John", "age": 25}],
     *   "filename": "export"
     * }
     * 
     * @return array Response with CSV URL
     */
    public function postExportCsv($id = null, $data = [])
    {
        if ($guard = $this->guardPrint()) return $guard;
        try {
            $data = $data ?: json_decode(file_get_contents('php://input'), true) ?: [];

            if (empty($data['data'])) {
                return formatResponse(false, null, 'No data provided');
            }
            
            $filename = $data['filename'] ?? 'export';
            $csvPath = $this->prints()->exportCSV($data['data'], $filename);
            
            // Convert to relative URL
            $csvUrl = $this->getGeneratedDownloadUrl($csvPath);

            return formatResponse(true, [
                'file' => [
                    'filename' => basename($csvPath),
                    'mime_type' => 'text/csv',
                    'url' => $csvUrl,
                    'download_url' => $csvUrl,
                ],
                'files' => [[
                    'filename' => basename($csvPath),
                    'mime_type' => 'text/csv',
                    'url' => $csvUrl,
                    'download_url' => $csvUrl,
                ]],
                'csv_url' => $csvUrl,
                'download_url' => $csvUrl,
                'filename' => basename($csvPath),
            ], 'CSV exported successfully');
            
        } catch (\Exception $e) {
            error_log('[PrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }
    
    /**
     * Build a web-accessible URL for a file inside the public temp/print/ dir.
     *
     * Uses BASE_URL (env-aware: http://localhost/Kingsway in dev, the prod
     * domain in production) so the returned URL is valid in ANY environment,
     * instead of stripping the filesystem root (which only works when the project
     * sits directly under the web root).
     *
     * @param string $filename Basename of the generated file
     * @return string Absolute, environment-agnostic URL
     */
    private function getPrintUrl(string $path): string
    {
        return $this->downloads()->printUrlForAbsolutePath(
            $path,
            1800
        );
    }

    private function getGeneratedDownloadUrl(string $path): string
    {
        return $this->downloads()->generatedDownloadUrlForAbsolutePath(
            $path,
            1800
        );
    }
}
