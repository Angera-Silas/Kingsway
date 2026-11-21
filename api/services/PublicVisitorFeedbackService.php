<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Database\Database;
use PDO;

/**
 * The public assistant's marketer questions: short, optional, anonymous.
 *
 * Every few answered questions the assistant offers ONE question the visitor
 * has not seen, with tap-to-answer options and a Skip. Answers are stored in
 * public_visitor_feedback against a keyed hash of an anonymous visitor id:
 * no name, phone, email or child detail is ever requested or stored.
 */
final class PublicVisitorFeedbackService
{
    public const NOTICE = 'Optional and anonymous: it helps us serve families better.';

    /** key => [question, options (empty = short free text)] */
    public const QUESTIONS = [
        'heard_about' => ['How did you hear about Kingsway?', [
            'Friend or family', 'Church', 'Social media', 'Google search', 'Our website', 'Drove past the school', 'Other',
        ]],
        'what_matters' => ['What matters most to you in a school?', [
            'Academic results', 'Christian values', 'Safety and care', 'Boarding', 'Affordable fees', 'Sports and clubs', 'Transport',
        ]],
        'considering_level' => ['Which level are you considering?', [
            'Pre-Primary (Playgroup-PP2)', 'Lower Primary (Grades 1-3)', 'Upper Primary (Grades 4-6)', 'Junior School (Grades 7-9)', 'Just browsing',
        ]],
        'impression' => ['What is your impression of Kingsway so far?', [
            'Very positive', 'Positive', 'Neutral', 'Not sure yet', 'Negative',
        ]],
        'likely_to_apply' => ['How likely are you to apply this year?', [
            'Very likely', 'Likely', 'Not sure', 'Not this year',
        ]],
        'improve' => ['Is there anything we could explain better or do better?', []],
    ];

    /** Offer a question after this many answered turns, then every second one. */
    private const FIRST_AFTER_TURNS = 1;
    private const EVERY_TURNS = 2;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getInstance()->getConnection();
    }

    /** @return list<string> question keys this visitor has already answered */
    public function answeredKeys(string $visitorHash): array
    {
        try {
            $stmt = $this->db()->prepare('SELECT question_key FROM public_visitor_feedback WHERE visitor_ref = ?');
            $stmt->execute([$visitorHash]);
            return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (\Throwable $e) {
            return []; // table not migrated yet: no question is lost, only repeated
        }
    }

    /**
     * The next question to offer, or null. $seenKeys = answered or already
     * shown (skipped) in this chat; $answeredTurns = assistant answers so far.
     */
    public function nextPrompt(array $seenKeys, int $answeredTurns): ?array
    {
        if ($answeredTurns < self::FIRST_AFTER_TURNS
            || ($answeredTurns - self::FIRST_AFTER_TURNS) % self::EVERY_TURNS !== 0) {
            return null;
        }
        $seen = array_fill_keys($seenKeys, true);
        foreach (self::QUESTIONS as $key => [$question, $options]) {
            if (!isset($seen[$key])) {
                return [
                    'key' => $key,
                    'question' => $question,
                    'options' => $options,
                    'free_text' => $options === [],
                    'notice' => self::NOTICE,
                ];
            }
        }
        return null;
    }

    /** Store one voluntary answer; validates the key and the answer. */
    public function record(string $visitorHash, string $key, string $answer): array
    {
        if (!isset(self::QUESTIONS[$key])) {
            return ['success' => false, 'message' => 'Unknown question.', 'code' => 422];
        }
        [, $options] = self::QUESTIONS[$key];
        $answer = trim(strip_tags($answer));
        if ($options !== []) {
            if (!in_array($answer, $options, true)) {
                return ['success' => false, 'message' => 'Please choose one of the options.', 'code' => 422];
            }
        } else {
            $answer = mb_substr(preg_replace('/\s+/', ' ', $answer) ?? '', 0, 300);
            if ($answer === '') {
                return ['success' => false, 'message' => 'Please type a short answer or skip.', 'code' => 422];
            }
            // Free text must not carry contact details: the survey is anonymous.
            $answer = preg_replace(['/[\w.+-]+@[\w-]+\.[\w.]+/', '/\+?\d[\d\s-]{7,}\d/'], ['[removed]', '[removed]'], $answer) ?? $answer;
        }
        try {
            $this->db()->prepare(
                'INSERT INTO public_visitor_feedback (visitor_ref, question_key, answer, consent, channel)
                 VALUES (?, ?, ?, 1, ?)
                 ON DUPLICATE KEY UPDATE answer = VALUES(answer), created_at = CURRENT_TIMESTAMP'
            )->execute([$visitorHash, $key, $answer, 'public_assistant']);
        } catch (\Throwable $e) {
            FileLogger::write('ai_generation', ['type' => 'public_feedback_store_failed', 'error' => $e->getMessage()], 'error');
            return ['success' => false, 'message' => 'Thank you. We could not save that right now.', 'code' => 503];
        }
        FileLogger::write('ai_generation', ['type' => 'public_feedback_recorded', 'question_key' => $key]);
        return ['success' => true, 'message' => 'Thank you for sharing.', 'code' => 200];
    }
}
