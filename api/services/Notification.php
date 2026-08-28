<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Notification - Laravel-style in-app notification facade.
 *
 *   Notification::to($recipients)->title('Fee structure released')
 *       ->message('The 2026 fee structure is now out.').type('announcement')
 *       ->priority('high')->send();
 *
 *   // or the compact static form
 *   Notification::send(42, 'leave_request', 'Leave approved',
 *       'Your leave request was approved by Jennifer', 'medium');
 *
 * Delegates to NotificationService::push() so every notification is addressed
 * to a real audience and recorded once (see NotificationService for the
 * recipient string grammar: int, int[], 'all_staff', 'all_users', 'role:{id}',
 * 'role:{name}', 'staff:{id}').
 */
class Notification
{
    /** @var mixed Recipients (int|int[]|string). */
    private $recipients;
    private $type = 'general';
    private $title = '';
    private $message = '';
    private $priority = 'medium';
    private $options = [];

    private function __construct()
    {
    }

    /**
     * Begin a fluent chain with the target recipients.
     *
     * @param int|int[]|string $recipients
     */
    public static function to($recipients): self
    {
        $n = new self();
        $n->recipients = $recipients;
        return $n;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function priority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @param array<string,mixed> $options e.g. ['dedup_minutes'=>60,'created_at'=>...]
     */
    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Send the in-app notification.
     *
     * @return int Number of notification rows inserted.
     */
    public function send(): int
    {
        if ($this->recipients === null) {
            throw new RuntimeException('Notification: no recipients set (call ->to()).');
        }
        $service = new NotificationService($this->pdo());
        return $service->push(
            $this->recipients,
            $this->type,
            $this->title,
            $this->message,
            $this->priority,
            $this->options
        );
    }

    /**
     * Compact one-shot send.
     *
     * @param int|int[]|string $recipients
     */
    public static function sendNow($recipients, string $type, string $title, string $message, string $priority = 'medium', array $options = []): int
    {
        return (new NotificationService(self::pdo()))->push($recipients, $type, $title, $message, $priority, $options);
    }

    private static function pdo(): PDO
    {
        return Database::getInstance()->getConnection();
    }
}
