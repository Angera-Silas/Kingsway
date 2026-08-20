<?php
namespace App\API\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MessageService
{
    private $db;
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Render formal business letter email with proper block format
     * Supports structured body sections for professional formatting
     */
    public function renderFormalEmail($subject, $body, $signature, $footer, $media = '', $schoolDetails = [])
    {
        // If body is array, format as formal letter sections
        $formattedBody = $body;
        if (is_array($body)) {
            $formattedBody = $this->formatFormalLetterBody($body);
        }

        // Merge with default school details from config
        $defaultDetails = [
            'address' => defined('SCHOOL_ADDRESS') ? SCHOOL_ADDRESS : '',
            'phone' => defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '',
            'email' => defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'info@kingsway.ac.ke',
            'principal_name' => defined('SCHOOL_PRINCIPAL_NAME') ? SCHOOL_PRINCIPAL_NAME : 'Mr, Bett Junior',
            'principal_title' => defined('SCHOOL_PRINCIPAL_TITLE') ? SCHOOL_PRINCIPAL_TITLE : 'Headteacher',
            'logo' => defined('SCHOOL_LOGO_URL') ? SCHOOL_LOGO_URL : '',
            'name' => defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School',
            'motto' => defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'Learning, character and service',
            'portal_url' => defined('APP_URL') ? APP_URL : '#'
        ];

        $schoolDetails = array_merge($defaultDetails, $schoolDetails);

        // Load formal template if exists, fallback to bootstrap template
        $templatePath = __DIR__ . '/../modules/communications/templates/branded_email_template.html';
        if (!file_exists($templatePath)) {
            $templatePath = __DIR__ . '/../modules/communications/templates/email_bootstrap_template.html';
        }

        $template = file_get_contents($templatePath);

        // Build logo section — use CID reference so sendEmail() can embed it as a
        // MIME inline attachment.  Gmail strips data: URIs which broke the old approach.
        $logoSection = '<img src="cid:kingsway-logo" width="70" alt="Kingsway Preparatory School" style="display:block;max-width:70px;height:auto;border:0;border-radius:8px;" />';

        $formattedBody = $this->ensureHtmlBody($formattedBody);

        $profile = $this->emailProfile($subject);
        $replacements = [
            '{{subject}}' => $subject,
            '{{body}}' => $formattedBody,
            '{{signature}}' => $signature,
            '{{footer}}' => $footer,
            '{{media}}' => $media,
            '{{date}}' => date('j F Y'),
            '{{school_address}}' => htmlspecialchars($schoolDetails['address']),
            '{{school_phone}}' => htmlspecialchars($schoolDetails['phone']),
            '{{school_email}}' => htmlspecialchars($schoolDetails['email']),
            '{{sender_name}}' => htmlspecialchars($schoolDetails['principal_name']),
            '{{sender_title}}' => htmlspecialchars($schoolDetails['principal_title']),
            '{{school_logo}}' => $logoSection
            ,'{{school_name}}' => htmlspecialchars($schoolDetails['name'])
            ,'{{school_motto}}' => htmlspecialchars($schoolDetails['motto'])
            ,'{{school_link}}' => htmlspecialchars($schoolDetails['portal_url'])
            ,'{{message_type}}' => $profile['label']
            ,'{{accent}}' => $profile['accent']
            ,'{{accent_soft}}' => $profile['soft']
        ];
        return strtr($template, $replacements);
    }    /**
         * Format email body sections into formal letter format
         * Input array structure:
         * [
         *   'recipient_name' => 'John Doe',
         *   'salutation' => 'Dear Mr. Doe,',
         *   'intro' => 'Thank you for...',
         *   'main_content' => ['Payment Details:', '- Amount: KES 50,000', '- Date: 3-Dec-2025'],
         *   'closing' => 'Should you have questions...',
         *   'sign_off' => 'Sincerely,'
         * ]
         */
    private function formatFormalLetterBody($sections)
    {
        $formatted = '';

        // Salutation
        if (isset($sections['salutation'])) {
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
                        // Bullet point - indent
                        $formatted .= '<div style="margin-left: 20px; margin-bottom: 8px;">'
                            . htmlspecialchars($line) . '</div>';
                    } else if (substr($line, -1) === ':') {
                        // Header line - bold with spacing
                        $formatted .= '<div style="margin-top: 16px; margin-bottom: 8px; font-weight: bold;">'
                            . htmlspecialchars($line) . '</div>';
                    } else {
                        // Regular content line
                        $formatted .= '<div style="margin-bottom: 8px;">'
                            . htmlspecialchars($line) . '</div>';
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

    /**
     * Convert a plain-text body to safe HTML paragraphs.
     * If the body already contains HTML tags it is returned unchanged.
     */
    private function ensureHtmlBody(string $body): string
    {
        if (preg_match('/<[^>]+>/', $body)) {
            return $body;
        }

        $escaped = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

        $paragraphs = preg_split('/\n\s*\n/', $escaped);
        if (count($paragraphs) > 1) {
            $html = '';
            foreach ($paragraphs as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $html .= '<p style="margin:0 0 14px 0;line-height:1.75;">' . nl2br($p) . '</p>';
                }
            }
            return $html;
        }

        $sentences = preg_split('/(?<=\.)\s+(?=[A-Z])/', $escaped);
        if (count($sentences) > 1) {
            $html = '';
            foreach ($sentences as $s) {
                $s = trim($s);
                if ($s !== '') {
                    $html .= '<p style="margin:0 0 12px 0;line-height:1.75;">' . $s . '</p>';
                }
            }
            return $html;
        }

        return '<p style="margin:0 0 12px 0;line-height:1.75;">' . $escaped . '</p>';
    }

    /**
     * Embed the school logo as a CID inline image on a PHPMailer instance.
     * Must be called before $mail->send() when the HTML body references cid:kingsway-logo.
     */
    public function embedLogo($mail): void
    {
        $logoPath = __DIR__ . '/../../uploads/school_assets/official_school_logo.png';
        if (!file_exists($logoPath)) {
            $logoPath = __DIR__ . '/../../images/official_school_logo.png';
        }
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'kingsway-logo', 'official_school_logo.png', PHPMailer::ENCODING_BASE64, 'image/png');
        }
    }

    // Render email using Bootstrap template
    public function renderEmail($subject, $body, $signature, $footer, $media = '', $schoolDetails = [])
    {
        // Default to formal rendering if body is array
        if (is_array($body)) {
            return $this->renderFormalEmail($subject, $body, $signature, $footer, $media, $schoolDetails);
        }

        return $this->renderFormalEmail($subject, $body, $signature, $footer, $media, $schoolDetails);
        /* legacy renderer retained below for reference */
        $template = file_get_contents(__DIR__ . '/../modules/communications/templates/email_bootstrap_template.html');
        $replacements = [
            '{{subject}}' => $subject,
            '{{body}}' => $body,
            '{{signature}}' => $signature,
            '{{footer}}' => $footer,
            '{{media}}' => $media,
            '{{school_address}}' => $schoolDetails['address'] ?? '',
            '{{school_phone}}' => $schoolDetails['phone'] ?? '',
            '{{school_email}}' => $schoolDetails['email'] ?? ''
        ];
        return strtr($template, $replacements);
    }

    // Send email (single or mass)
    public function sendEmail($recipients, $subject, $htmlBody, $attachments = [])
    {
        // Assumes config.php is loaded at application entry point and constants are available
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = 'tls';
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            if (is_array($recipients)) {
                foreach ($recipients as $email => $name) {
                    $mail->addAddress($email, $name);
                }
            } else {
                $mail->addAddress($recipients);
            }
            $mail->isHTML(true);
            // Explicitly set content type to HTML with UTF-8 encoding
            $mail->ContentType = 'text/html; charset=UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            // Embed the school logo as a CID inline image (referenced as cid:kingsway-logo in the template)
            $this->embedLogo($mail);

            // Attachments
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath);
                }
            }

            // Add plain text alternative
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log error
            error_log("Email send error: " . $e->getMessage());
            return false;
        }
    }

    // Mass sending logic (e.g. for announcements)
    public function sendMassEmail($recipientList, $subject, $htmlBody, $attachments = [])
    {
        foreach ($recipientList as $recipients) {
            $this->sendEmail($recipients, $subject, $htmlBody, $attachments);
        }
    }

    private function emailProfile($subject)
    {
        $text = strtolower((string) $subject);
        if (strpos($text, 'invoice') !== false || strpos($text, 'payment') !== false || strpos($text, 'fee') !== false) return ['label' => 'Account notice', 'accent' => '#087f5b', 'soft' => '#e5f7ef'];
        if (strpos($text, 'reminder') !== false || strpos($text, 'due') !== false) return ['label' => 'Reminder', 'accent' => '#a15c00', 'soft' => '#fff4db'];
        if (strpos($text, 'reply') !== false || strpos($text, 'response') !== false) return ['label' => 'Reply', 'accent' => '#6f42c1', 'soft' => '#f1eafd'];
        if (strpos($text, 'notification') !== false || strpos($text, 'interview') !== false || strpos($text, 'schedule') !== false) return ['label' => 'Notification', 'accent' => '#0067a5', 'soft' => '#e5f2fa'];
        return ['label' => 'Information', 'accent' => '#0067a5', 'soft' => '#e5f2fa'];
    }
}
