<?php
namespace App\API\Modules\communications;

use App\API\Core\FileLifecycleBase;
use PDO;

class CommunicationsManager extends FileLifecycleBase
{
    /**
     * Update delivery status for a recipient (used for SMS/MMS/WhatsApp/Email delivery callbacks)
     * @param int $recipientId
     * @param string $status (delivered, failed, pending, etc)
     * @param string|null $deliveredAt (optional, timestamp)
     * @param string|null $errorMessage (optional)
     * @return bool
     */
    public function updateDeliveryStatus($recipientId, $status, $deliveredAt = null, $errorMessage = null)
    {
        $fields = ["status = :status"];
        $params = [":status" => $status, ":id" => $recipientId];
        if ($deliveredAt) {
            $fields[] = "delivered_at = :delivered_at";
            $params[":delivered_at"] = $deliveredAt;
        }
        if ($errorMessage) {
            $fields[] = "error_message = :error_message";
            $params[":error_message"] = $errorMessage;
        }
        $fields[] = "last_attempt_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE communication_recipients SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Mark a recipient as opted out (used for opt-out callbacks)
     * @param string $recipientIdentifier (phone/email/user id)
     * @param string $channel (sms, email, whatsapp, etc)
     * @return bool
     */
    public function markOptOut($recipientIdentifier, $channel)
    {
        // Mark a recipient as opted out by adding a note to their contact record
        // Try to find and update the contact record by phone number
        $sql = "UPDATE contact_directory SET notes = CONCAT(IFNULL(notes, ''), '\n[Opted out from SMS: ' , NOW(), ']') WHERE phone = :phone";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':phone' => $recipientIdentifier]);

        // Also log the opt-out action for audit trail if there's a communication log table
        // This is optional but good for tracking
        return $result;
    }

    /**
     * Store incoming message (used for subscription/inbound callbacks)
     * @param array $data (should include sender, message, channel, received_at, etc)
     * @return bool
     */
    public function storeIncomingMessage($data)
    {
        $sql = "INSERT INTO external_inbound_messages (source_address, body, source_type, received_at, subject) VALUES (:source_address, :body, :source_type, :received_at, :subject)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':source_address' => $data['sender'] ?? $data['phone'] ?? null,
            ':body' => $data['message'] ?? null,
            ':source_type' => $data['channel'] ?? 'sms',
            ':received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
            ':subject' => $data['subject'] ?? null
        ]);
    }

    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Send SMS to selected recipients with given message.
     * @param array $recipients Array of phone numbers
     * @param string $message
     * @return array [status, message, sent_count, failed]
     */
    /**
     * Send SMS, MMS, or WhatsApp to selected recipients with given message/media.
     * @param array $recipients Array of phone numbers
     * @param string $message
     * @param string $type 'sms', 'mms', or 'whatsapp'
     * @param string|array|null $media (for MMS/WhatsApp)
     * @return array [status, message, sent_count, failed]
     */
    public function sendSMSToRecipients($recipients, $variables, $type = 'sms', $category = '', $media = null)
    {
        // Use TemplateLoader for template selection and rendering
        $templateLoader = new \App\API\Modules\Communications\Templates\TemplateLoader();
        $template = $templateLoader->getTemplate($type, $category);
        if (!$template) {
            return [
                'status' => 'error',
                'message' => 'No template found for type/category',
                'sent_count' => 0,
                'failed' => $recipients
            ];
        }
        $gateway = new \App\API\Services\SMS\SMSGateway();
        $sent = 0;
        $failed = [];
        foreach ($recipients as $phone) {
            try {
                $rendered = $templateLoader->renderTemplate($template, $variables);
                if ($type === 'whatsapp') {
                    $mediaUrls = $templateLoader->getMedia($template);
                    $result = $gateway->sendWhatsApp($phone, $rendered, $mediaUrls);
                } else {
                    $result = $gateway->send($phone, $rendered);
                }
                if ($result) {
                    $sent++;
                } else {
                    $failed[] = $phone;
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
            }
        }
        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'message' => $sent > 0 ? "Sent $sent messages" : 'Failed to send messages',
            'sent_count' => $sent,
            'failed' => $failed
        ];
    }

    /**
     * Send email to selected recipients with given subject, body, and attachments.
     * @param array $recipients Array of [email => name] or just emails
     * @param string $subject
     * @param string $body
     * @param array $attachments
     * @param string $signature
     * @param string $footer
     * @param array $schoolDetails
     * @return array [status, message, sent_count, failed]
     */
    public function sendEmailToRecipients($recipients, $subject, $body, $attachments = [], $signature = '', $footer = '', $schoolDetails = [])
    {
        // Lazy load MessageService
        $service = new \App\API\Services\MessageService($this->db);
        $sent = 0;
        $failed = [];
        foreach ($recipients as $email => $name) {
            // If $recipients is a list, not assoc array
            if (is_int($email)) {
                $email = $name;
                $name = '';
            }

            // Determine if this should use formal layout (when body is array or signature/footer provided)
            $isFormal = is_array($body) || !empty($signature) || !empty($footer);

            if ($isFormal) {
                // Use formal email template with proper placeholder population
                $htmlBody = $service->renderFormalEmail($subject, $body, $signature, $footer, '', $schoolDetails);
            } else {
                // Use standard email rendering
                $htmlBody = $service->renderEmail($subject, $body, $signature, $footer, '', $schoolDetails);
            }

            $result = $service->sendEmail([$email => $name], $subject, $htmlBody, $attachments);
            if ($result) {
                $sent++;
            } else {
                $failed[] = $email;
            }
        }
        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'message' => $sent > 0 ? "Sent $sent emails" : 'Failed to send emails',
            'sent_count' => $sent,
            'failed' => $failed
        ];
    }

    /**
     * Format email body sections into formal letter format
     * Supports structured body with sections: salutation, intro, main_content, closing, sign_off
     */
    public function formatFormalEmailBody($sections)
    {
        if (is_string($sections)) {
            return $sections; // If already string, return as-is
        }

        $formatted = '';

        // Salutation
        if (isset($sections['salutation']) && !empty($sections['salutation'])) {
            $formatted .= '<p style="margin-bottom: 20px;">' . htmlspecialchars($sections['salutation']) . '</p>';
        }

        // Introduction paragraph
        if (isset($sections['intro']) && !empty($sections['intro'])) {
            $formatted .= '<p style="margin-bottom: 16px; line-height: 1.6;">'
                . nl2br(htmlspecialchars($sections['intro'])) . '</p>';
        }

        // Main content section with formatting
        if (isset($sections['main_content'])) {
            if (is_array($sections['main_content'])) {
                $formatted .= '<div style="margin: 24px 0; line-height: 1.8;">';
                foreach ($sections['main_content'] as $line) {
                    if (substr($line, 0, 1) === '-' || substr($line, 0, 1) === '•') {
                        $formatted .= '<div style="margin-left: 20px; margin-bottom: 8px;">' . htmlspecialchars($line) . '</div>';
                    } else if (substr($line, -1) === ':') {
                        $formatted .= '<div style="margin-top: 16px; margin-bottom: 8px; font-weight: bold;">' . htmlspecialchars($line) . '</div>';
                    } else {
                        $formatted .= '<div style="margin-bottom: 8px;">' . htmlspecialchars($line) . '</div>';
                    }
                }
                $formatted .= '</div>';
            } else {
                $formatted .= '<div style="margin: 24px 0; line-height: 1.6;">'
                    . nl2br(htmlspecialchars($sections['main_content'])) . '</div>';
            }
        }

        // Closing paragraph
        if (isset($sections['closing']) && !empty($sections['closing'])) {
            $formatted .= '<p style="margin-bottom: 16px; margin-top: 24px; line-height: 1.6;">'
                . nl2br(htmlspecialchars($sections['closing'])) . '</p>';
        }

        // Sign-off
        if (isset($sections['sign_off'])) {
            $formatted .= '<div style="margin-top: 32px; margin-bottom: 8px;">'
                . htmlspecialchars($sections['sign_off']) . '</div>';
        }

        return $formatted;
    }

    // Communications CRUD
    public function createCommunication($data)
    {
        // Map channel to type
        $type = $data['type'] ?? $data['channel'] ?? 'email';
        $typeMap = [
            'sms' => 'sms',
            'email' => 'email',
            'notification' => 'notification',
            'internal' => 'internal',
            'whatsapp' => 'whatsapp',
            'message' => 'email'  // Default to email if type is 'message'
        ];
        $type = $typeMap[$type] ?? 'email';

        // Get content from message or content field
        $body = $data['body'] ?? $data['content'] ?? $data['message'] ?? 'No content';
        // If body is array, convert to JSON for storage
        if (is_array($body)) {
            $content = json_encode($body);
        } else {
            $content = $body;
        }

        $sql = "INSERT INTO communications (sender_id, subject, body, type, status, priority, template_id, scheduled_at, reminder_at, sender_signature) VALUES (:sender_id, :subject, :body, :type, :status, :priority, :template_id, :scheduled_at, :reminder_at, :sender_signature)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $data['sender_id'] ?? 1,
            ':subject' => $data['subject'] ?? 'No subject',
            ':body' => $content,
            ':type' => $type,
            ':status' => $this->normalizeStatus($data['status'] ?? 'draft'),
            ':priority' => $this->normalizePriority($data['priority'] ?? 'medium'),
            ':template_id' => $data['template_id'] ?? null,
            ':scheduled_at' => $data['scheduled_at'] ?? null,
            ':reminder_at' => $data['reminder_at'] ?? null,
            ':sender_signature' => $data['sender_signature'] ?? $data['signature'] ?? null,
        ]);
        return $this->getCommunication($this->db->lastInsertId());
    }
    public function getCommunication($id)
    {
        $sql = "SELECT * FROM communications WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $this->decorateRow($row);
    }
    public function updateCommunication($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (["sender_id", "subject", "body", "type", "template_id", "scheduled_at", "reminder_at", "sender_signature"] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $this->normalizeStatus($data['status']);
        }
        if (isset($data['priority'])) {
            $fields[] = "priority = :priority";
            $params[':priority'] = $this->normalizePriority($data['priority']);
        }
        if (!$fields) {
            throw new \InvalidArgumentException('No fields to update');
        }
        $sql = "UPDATE communications SET " . implode(",", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getCommunication($id);
    }
    public function deleteCommunication($id)
    {
        $sql = "DELETE FROM communications WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listCommunications($filters = [])
    {
        $sql = "SELECT * FROM communications WHERE 1=1";
        $params = [];
        foreach (["sender_id", "type", "status", "priority", "template_id"] as $col) {
            if (isset($filters[$col])) {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }
        if (isset($filters['from_date'])) {
            $sql .= " AND created_at >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }
        if (isset($filters['to_date'])) {
            $sql .= " AND created_at <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(function ($row) {
            return $this->decorateRow($row);
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Add frontend-friendly aliases to a communications row. The JS page
     * controllers read `content`/`title` while the DB column is `body`/`subject`.
     */
    private function decorateRow(array $row): array
    {
        if (!array_key_exists('content', $row)) {
            $row['content'] = $row['body'] ?? null;
        }
        if (!array_key_exists('title', $row)) {
            $row['title'] = $row['subject'] ?? null;
        }
        return $row;
    }

    /**
     * Normalize a priority value to the communications.priority enum
     * ('low'|'medium'|'high'). Frontends send UI aliases such as 'normal'
     * (→ medium) and 'urgent' (→ high), which would otherwise be rejected
     * by the strict enum column.
     *
     * @param mixed $priority
     * @return string
     */
    private function normalizePriority($priority): string
    {
        $map = [
            'low' => 'low',
            'normal' => 'medium',
            'medium' => 'medium',
            'high' => 'high',
            'urgent' => 'high',
        ];
        $key = strtolower(trim((string)$priority));
        return $map[$key] ?? 'medium';
    }

    /**
     * Normalize a status value to the communications.status enum
     * ('draft'|'sent'|'scheduled'|'failed'). Frontends use 'published'
     * as an alias for 'sent'.
     *
     * @param mixed $status
     * @return string
     */
    private function normalizeStatus($status): string
    {
        $map = [
            'draft' => 'draft',
            'published' => 'sent',
            'sent' => 'sent',
            'scheduled' => 'scheduled',
            'pending' => 'draft',
            'failed' => 'failed',
        ];
        $key = strtolower(trim((string)$status));
        return $map[$key] ?? 'draft';
    }

    // Attachments CRUD
    public function addAttachment($communicationId, $fileData)
    {
        // If no communication_id provided, use default or file-based storage
        if (!$communicationId) {
            $communicationId = 1; // Default to system communication
        }

        // If an uploaded file is provided in $fileData (or in $_FILES), use MediaManager to store it
        $filePath = $fileData['file_path'] ?? null;
        $fileName = $fileData['file_name'] ?? null;
        $mediaId = null;
        try {
            $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
            $uploadFile = $fileData['file'] ?? ($_FILES['file'] ?? null);
            $uploader = $fileData['uploaded_by'] ?? $fileData['uploader_id'] ?? $fileData['user_id'] ?? null;
            if ($uploadFile) {
                $mediaId = $mediaManager->upload($uploadFile, 'communications', $communicationId, null, $uploader, $fileData['description'] ?? 'communication attachment');
                $preview = $mediaManager->getPreviewUrl($mediaId);
                $filePath = $preview ?: $filePath;
                $fileName = $fileName ?: ($uploadFile['name'] ?? basename($filePath));
            }
        } catch (\Exception $e) {
            // fallback to provided file_path/file_name if media upload fails
        }

        $sql = "INSERT INTO communication_attachments (communication_id, file_name, file_path) VALUES (:communication_id, :file_name, :file_path)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $communicationId,
            ':file_name' => $fileName ?? 'unnamed_file',
            ':file_path' => $filePath ?? $this->publicUploadAssetUrl('communications', 'unnamed_file')
        ]);
        return $this->getAttachment($this->db->lastInsertId());
    }
    public function getAttachment($id)
    {
        $sql = "SELECT * FROM communication_attachments WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function deleteAttachment($id)
    {
        $sql = "DELETE FROM communication_attachments WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listAttachments($communicationId)
    {
        $sql = "SELECT * FROM communication_attachments WHERE communication_id = :communication_id ORDER BY uploaded_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':communication_id' => $communicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Groups CRUD
    public function createGroup($data)
    {
        // Map type values to valid enum: 'staff','students','parents','custom'
        $type = $data['type'] ?? 'custom';
        $typeMap = [
            'class' => 'students',
            'department' => 'staff',
            'parent_forum' => 'parents'
        ];
        $type = $typeMap[$type] ?? $type;
        // Validate against enum
        if (!in_array($type, ['staff', 'students', 'parents', 'custom'])) {
            $type = 'custom';
        }

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('name is required');
        }
        $sql = "INSERT INTO communication_groups (name, description, type, created_by) VALUES (:name, :description, :type, :created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':type' => $type,
            ':created_by' => $data['created_by'] ?? 1
        ]);
        return $this->getGroup($this->db->lastInsertId());
    }
    public function getGroup($id)
    {
        $sql = "SELECT * FROM communication_groups WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function updateGroup($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (["name", "description", "type"] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (!$fields) {
            throw new \InvalidArgumentException('No fields to update');
        }
        $sql = "UPDATE communication_groups SET " . implode(", ", $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $sql = preg_replace('/,\s*,/', ',', $sql); // Remove accidental double commas
        $sql = str_replace('SET ,', 'SET ', $sql); // Remove accidental leading comma
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getGroup($id);
    }
    public function deleteGroup($id)
    {
        $sql = "DELETE FROM communication_groups WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listGroups($filters = [])
    {
        $sql = "SELECT * FROM communication_groups WHERE 1=1";
        $params = [];
        foreach (["type", "created_by"] as $col) {
            if (isset($filters[$col])) {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Logs CRUD - stored in the file-based communication log
    public function addLog($data)
    {
        // If communication_id is not provided, create a placeholder or use a system entry
        $communicationId = $data['communication_id'] ?? 0;
        $recipientId = $data['recipient_id'] ?? 0;

        $eventType = $data['event_type'] ?? $data['action'] ?? 'log';
        $details = $data['details'] ?? null;
        $payload = ['event_type' => $eventType];
        if ($details !== null) {
            $payload['details'] = is_array($details) ? $details : json_decode((string) $details, true);
        }

        \App\API\Includes\FileLogger::write('audit', [
            'type' => 'audit',
            'action' => 'communication_log',
            'entity' => 'communication',
            'entity_id' => (int) $communicationId,
            'user_id' => (int) $recipientId,
            'details' => $payload,
            'status' => 'success',
        ]);
        return [
            'id' => null,
            'communication_id' => (int) $communicationId,
            'recipient_id' => (int) $recipientId,
            'event_type' => $eventType,
            'details' => $payload
        ];
    }
    public function getLog($id)
    {
        $entries = \App\API\Includes\FileLogger::recent('audit', 100, ['action' => 'communication_log']);
        foreach ($entries as $row) {
            $row['communication_id'] = $row['entity_id'] ?? null;
            $row['recipient_id'] = $row['user_id'] ?? null;
            $decoded = isset($row['details']) && is_array($row['details']) ? $row['details'] : [];
            $row['event_type'] = $decoded['event_type'] ?? 'log';
            $row['details'] = $decoded['details'] ?? $decoded;
            $row['recipient'] = $row['recipient_id'];
            $row['status'] = 'logged';
            $row['created_at'] = $row['timestamp'] ?? null;
            return $row;
        }
        return null;
    }
    public function listLogs($filters = [])
    {
        $entries = \App\API\Includes\FileLogger::recent('audit', 500, ['action' => 'communication_log']);
        $rows = [];
        foreach ($entries as $row) {
            if (!empty($filters['communication_id']) && (int) ($row['entity_id'] ?? 0) !== (int) $filters['communication_id']) {
                continue;
            }
            if (!empty($filters['recipient_id']) && (int) ($row['user_id'] ?? 0) !== (int) $filters['recipient_id']) {
                continue;
            }
            $decoded = isset($row['details']) && is_array($row['details']) ? $row['details'] : [];
            $rows[] = [
                'id' => null,
                'communication_id' => $row['entity_id'] ?? null,
                'recipient_id' => $row['user_id'] ?? null,
                'event_type' => $decoded['event_type'] ?? 'log',
                'details' => $decoded['details'] ?? $decoded,
                'created_at' => $row['timestamp'] ?? null,
            ];
        }
        return $rows;
    }

    // Recipients CRUD
    public function addRecipient($data)
    {
        // If communication_id or recipient_id is missing, use default placeholders
        $communicationId = $data['communication_id'] ?? 0;
        $recipientId = $data['recipient_id'] ?? 0;

        // Convert various recipient formats
        if (!$recipientId) {
            // Try to extract from recipient data
            if (isset($data['recipient'])) {
                // Could be phone, email, or name
                $recipientId = isset($data['recipient_id']) ? $data['recipient_id'] : 1;
            } else {
                $recipientId = 1; // Default to system
            }
        }

        if (!$communicationId) {
            $communicationId = 1; // Default communication
        }

        $sql = "INSERT INTO communication_recipients (communication_id, recipient_id, status, delivered_at, delivery_attempts, last_attempt_at, error_message, opened_at, clicked_at, device_info) VALUES (:communication_id, :recipient_id, :status, :delivered_at, :delivery_attempts, :last_attempt_at, :error_message, :opened_at, :clicked_at, :device_info)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $communicationId,
            ':recipient_id' => $recipientId,
            ':status' => $data['status'] ?? 'pending',
            ':delivered_at' => $data['delivered_at'] ?? null,
            ':delivery_attempts' => $data['delivery_attempts'] ?? 0,
            ':last_attempt_at' => $data['last_attempt_at'] ?? null,
            ':error_message' => $data['error_message'] ?? null,
            ':opened_at' => $data['opened_at'] ?? null,
            ':clicked_at' => $data['clicked_at'] ?? null,
            ':device_info' => $data['device_info'] ?? null
        ]);
        return $this->getRecipient($this->db->lastInsertId());
    }
    public function getRecipient($id)
    {
        $sql = "SELECT * FROM communication_recipients WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function deleteRecipient($id)
    {
        $sql = "DELETE FROM communication_recipients WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listRecipients($communicationId)
    {
        $sql = "SELECT * FROM communication_recipients WHERE communication_id = :communication_id ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':communication_id' => $communicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Templates CRUD - mapped to live message_templates (legacy communication_templates retired)
    public function createTemplate($data)
    {
        // Map channel to message_templates.type (enum: email, sms, notification)
        $templateType = $data['template_type'] ?? $data['channel'] ?? 'sms';
        $typeMap = [
            'sms' => 'sms',
            'email' => 'email',
            'notification' => 'notification',
            'announcement' => 'notification',
            'internal' => 'notification',
            'internal_message' => 'notification',
            'message' => 'email'
        ];
        $templateType = $typeMap[$templateType] ?? 'sms';

        // Get template body from content or template_body or message field
        $templateBody = $data['template_body'] ?? $data['content'] ?? $data['message'] ?? 'No template body';

        $sql = "INSERT INTO message_templates (name, type, category, subject, body, variables, created_by, status, use_count) VALUES (:name, :type, :category, :subject, :body, :variables, :created_by, :status, :use_count)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'] ?? 'Unnamed Template',
            ':type' => $templateType,
            ':category' => $data['category'] ?? null,
            ':subject' => $data['subject'] ?? null,
            ':body' => $templateBody,
            ':variables' => isset($data['variables_json']) ? json_encode($data['variables_json']) : ($data['variables'] ?? null),
            ':created_by' => $data['created_by'] ?? 1,
            ':status' => $data['status'] ?? 'active',
            ':use_count' => $data['usage_count'] ?? 0
        ]);
        return $this->getTemplate($this->db->lastInsertId());
    }
    public function getTemplate($id)
    {
        $sql = "SELECT * FROM message_templates WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row = $this->decorateTemplate($row);
        }
        return $row;
    }
    public function updateTemplate($id, $data)
    {
        $colMap = [
            'name' => 'name',
            'template_type' => 'type',
            'category' => 'category',
            'subject' => 'subject',
            'template_body' => 'body',
            'variables_json' => 'variables',
            'status' => 'status',
            'usage_count' => 'use_count',
        ];
        $fields = [];
        $params = [':id' => $id];
        foreach ($colMap as $inputKey => $col) {
            if (isset($data[$inputKey])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = ($inputKey === 'variables_json') ? json_encode($data[$inputKey]) : $data[$inputKey];
            }
        }
        if (!$fields) {
            throw new \InvalidArgumentException('No fields to update');
        }
        $sql = "UPDATE message_templates SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getTemplate($id);
    }
    public function deleteTemplate($id)
    {
        $sql = "DELETE FROM message_templates WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listTemplates($filters = [])
    {
        $sql = "SELECT * FROM message_templates WHERE 1=1";
        $params = [];
        if (isset($filters['template_type']) && $filters['template_type'] !== '') {
            $filters['type'] = $filters['template_type'];
        }
        foreach (["type", "category", "status", "created_by"] as $col) {
            if (isset($filters[$col])) {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row = $this->decorateTemplate($row);
        }
        return $rows;
    }

    private function decorateTemplate(array $row): array
    {
        $row['template_type'] = $row['type'] ?? null;
        $row['template_body'] = $row['body'] ?? null;
        $row['usage_count'] = $row['use_count'] ?? 0;
        $row['example_output'] = null;
        $row['variables_json'] = isset($row['variables']) ? json_decode($row['variables'], true) : null;
        return $row;
    }

}
