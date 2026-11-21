<?php

declare(strict_types=1);

namespace App\API\Includes;

use App\API\Services\AiPromptGuardService;

/**
 * Prompt-attack gate shared by every controller that relays a question to an
 * assistant (BaseController for staff/parents, PublicController for visitors).
 * No SQL here: AiPromptGuardService owns the reads and writes.
 */
trait AiPromptGate
{
    /**
     * Prompt-attack gate for every assistant endpoint. Returns null to
     * proceed, or the refusal decision (blocked session or newly caught
     * malicious prompt). Audience: staff | parent | public.
     *
     * @return array{blocked:bool,newly_blocked:bool,message:string}|null
     */
    protected function aiPromptGate(string $question, string $audience = 'staff', array $conversation = [], string $chatSessionId = '', string $visitorId = ''): ?array
    {
        $guard = $this->contract(AiPromptGuardService::class);
        if ($audience === 'public') {
            $subjects = $guard->visitorSubjects($visitorId, AiPromptGuardService::clientIp());
        } else {
            $userType = $audience === 'parent' ? 'parent' : 'staff';
            $userId = $audience === 'parent'
                ? (int) ($_SERVER['auth_user']['parent_id'] ?? 0)
                : (int) (method_exists($this, 'getUserId') ? ($this->getUserId() ?? 0) : 0);
            $subjects = $guard->memberSubjects($userType, $userId, (int) ($_SERVER['auth_session_id'] ?? 0), $chatSessionId);
        }
        return $guard->check($subjects, $question, $audience, $conversation);
    }

    /** The assistant answer shape carrying the refusal, so every UI renders it as a reply. */
    protected function aiBlockedAnswer(array $decision): array
    {
        return [
            'status' => 'blocked',
            'blocked' => true,
            'routing' => 'prompt_injection_guard',
            'answer' => [
                'title' => 'Malicious prompt blocked',
                'body' => (string) $decision['message'],
                'next_steps' => [],
                'suggested_questions' => [],
            ],
            'title' => 'Malicious prompt blocked',
            'body' => (string) $decision['message'],
            'tools_used' => [],
            'provider_calls' => 0,
        ];
    }
}
