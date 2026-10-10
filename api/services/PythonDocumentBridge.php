<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;
use RuntimeException;

/** Sends bounded, PHP-authorized school documents to the private Python renderer. */
final class PythonDocumentBridge
{
    private InterServiceClient $client;

    public function __construct(?InterServiceClient $client = null)
    {
        $this->client = $client ?? new InterServiceClient();
    }

    public function available(): bool
    {
        return $this->client->isConfigured('python_ai')
            && trim((string) Config::get('AI_PYTHON_SECRET', '')) !== '';
    }

    /** Render one bounded HTML chunk. Raw HTML and PDF bytes are never logged. */
    public function renderStudentIdCardPdf(string $html): string
    {
        $result = $this->renderDocuments(
            [['document_id' => 'student-id-cards', 'html' => $html]],
            'combined',
            'none'
        );
        $encodedPdf = $result['pdf_base64'] ?? null;
        $pdf = is_string($encodedPdf) ? base64_decode($encodedPdf, true) : false;
        if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('The Python renderer returned an invalid PDF document.');
        }

        return $pdf;
    }

    /**
     * Render pre-authorized HTML documents and either combine them or return
     * one PDF per recipient/document. Python receives no database credentials.
     *
     * @param array<int, array{document_id: string, html: string}> $documents
     * @return array<string, mixed>
     */
    public function renderDocuments(
        array $documents,
        string $outputMode = 'combined',
        string $pageNumbering = 'local'
    ): array {
        if (!$this->available()) {
            throw new RuntimeException('The Python document renderer is not configured.');
        }
        if ($documents === [] || count($documents) > 100) {
            throw new RuntimeException('The document batch must contain between 1 and 100 documents.');
        }
        if (!in_array($outputMode, ['combined', 'individual'], true)
            || !in_array($pageNumbering, ['local', 'none', 'auto'], true)) {
            throw new RuntimeException('The document batch output options are invalid.');
        }

        $totalBytes = 0;
        foreach ($documents as $document) {
            if (!is_array($document)
                || !is_string($document['html'] ?? null)
                || trim($document['html']) === '') {
                throw new RuntimeException('A document batch entry is invalid.');
            }
            $size = strlen($document['html']);
            $totalBytes += $size;
            if ($size > 8 * 1024 * 1024 || $totalBytes > 18 * 1024 * 1024) {
                throw new RuntimeException('The document batch exceeds the renderer size limit.');
            }
        }

        $body = json_encode(
            [
                'documents' => array_values($documents),
                'output_mode' => $outputMode,
                'page_numbering' => $pageNumbering,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($body === false) {
            throw new RuntimeException('The document batch could not be encoded.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/documents/render-batch',
            $headers,
            $body,
            90
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_document_batch_render',
            'document_count' => count($documents),
            'output_mode' => $outputMode,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'request_bytes' => strlen($body),
            'transport_error' => $transportError !== '',
            'link' => $this->client->linkInUse('python_ai'),
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python document renderer did not complete the document batch.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data) || ($data['output_mode'] ?? null) !== $outputMode) {
            throw new RuntimeException('The Python renderer returned an invalid document batch.');
        }
        if ($outputMode === 'combined') {
            $encodedPdf = $data['pdf_base64'] ?? null;
            if (!is_string($encodedPdf) || strlen($encodedPdf) > 42 * 1024 * 1024) {
                throw new RuntimeException('The Python renderer returned an invalid or oversized PDF.');
            }
            $pdf = base64_decode($encodedPdf, true);
            if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException('The Python renderer returned an invalid PDF document.');
            }
        }

        return $data;
    }

    /**
     * Merge multiple PDFs returned from the renderer.
     *
     * @param array<int, string> $encodedPdfs Base64-encoded PDF strings.
     * @return string Merged PDF as base64.
     */
    public function mergePdfs(array $encodedPdfs): string
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python document renderer is not configured.');
        }
        if (count($encodedPdfs) < 2) {
            throw new RuntimeException('At least two PDFs are required to merge.');
        }

        $pdfs = [];
        $totalBytes = 0;
        foreach ($encodedPdfs as $index => $encoded) {
            if (!is_string($encoded) || $encoded === '') {
                throw new RuntimeException('An encoded PDF is required for merging.');
            }
            $decoded = base64_decode($encoded, true);
            if (!is_string($decoded) || !str_starts_with($decoded, '%PDF-')) {
                throw new RuntimeException('The Python renderer returned an invalid PDF document.');
            }
            $size = strlen($decoded);
            $totalBytes += $size;
            if ($size > 9 * 1024 * 1024 || $totalBytes > 30 * 1024 * 1024) {
                throw new RuntimeException('The merged PDF input exceeds the size limit.');
            }
            $pdfs[] = ['pdf_base64' => $encoded];
        }

        $body = json_encode(['pdfs' => $pdfs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('The merge request could not be encoded.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/documents/merge-pdfs',
            $headers,
            $body,
            90
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_document_merge',
            'input_count' => count($pdfs),
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python document renderer did not complete the PDF merge.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data) || !is_string($data['pdf_base64'] ?? null)) {
            throw new RuntimeException('The Python renderer returned an invalid merged PDF.');
        }
        $merged = base64_decode($data['pdf_base64'], true);
        if (!is_string($merged) || !str_starts_with($merged, '%PDF-')) {
            throw new RuntimeException('The Python renderer returned an invalid merged PDF.');
        }

        return $data['pdf_base64'];
    }

    /**
     * Render academic report cards in batch via Python service.
     *
     * @param array<int, int> $studentIds
     * @param int $termId
     * @param string $resultMode 'summative'|'formative'|'both'
     * @param string $outputFormat 'pdf'|'zip'
     * @param string $jobId
     * @return array{artifact: string, download_url: string, total_students: int, processed: int}
     */
    public function renderReportCardsBatch(
        array $studentIds,
        int $termId,
        string $resultMode,
        string $outputFormat,
        string $jobId
    ): array {
        if (!$this->available()) {
            throw new RuntimeException('The Python document renderer is not configured.');
        }
        if ($studentIds === []) {
            throw new RuntimeException('No student IDs provided for report card batch.');
        }
        if ($termId <= 0) {
            throw new RuntimeException('Invalid term_id for report card batch.');
        }

        $body = json_encode([
            'job_id' => $jobId,
            'student_ids' => $studentIds,
            'term_id' => $termId,
            'result_mode' => $resultMode,
            'output_format' => $outputFormat,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('The report card batch request could not be encoded.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/documents/report-cards/batch',
            $headers,
            $body,
            300
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_report_card_batch',
            'job_id' => $jobId,
            'student_count' => count($studentIds),
            'term_id' => $termId,
            'result_mode' => $resultMode,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python document renderer did not complete the report card batch.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python renderer returned an invalid report card batch response.');
        }
        return [
            'artifact' => $data['artifact'] ?? '',
            'download_url' => $data['download_url'] ?? '',
            'total_students' => (int) ($data['total_students'] ?? 0),
            'processed' => (int) ($data['processed'] ?? 0),
        ];
    }

}
