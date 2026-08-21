<?php

/**
 * Non-destructive communications provider health check.
 *
 * This validates configuration, renders an email, instantiates the SMS and
 * WhatsApp gateways, and checks TCP reachability. It deliberately never sends
 * an email, SMS, or WhatsApp message.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\API\Services\MessageService;
use App\API\Services\sms\SMSGateway;
use App\API\Services\whatsapp\WhatsAppGateway;
use App\Config\Config;

Config::init();

$failures = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$failures): void {
    echo sprintf("%-12s %s%s\n", $label, $ok ? 'OK' : 'FAIL', $detail !== '' ? " - {$detail}" : '');
    if (!$ok) $failures++;
};

$check('Email config', Config::get('SMTP_HOST', '') !== ''
    && (int) Config::get('SMTP_PORT', 0) > 0
    && Config::get('SMTP_USERNAME', '') !== ''
    && Config::get('SMTP_PASSWORD', '') !== ''
    && Config::get('SMTP_FROM_EMAIL', '') !== '', 'SMTP settings present');

try {
    $html = (new MessageService(null))->renderEmail('Provider check', 'No message was sent.', '', '');
    $check('Email render', strpos($html, 'No message was sent.') !== false, 'template rendered');
} catch (Throwable $e) {
    $check('Email render', false, 'template error');
}

$provider = strtolower((string) Config::get('SMS_PROVIDER', ''));
$check('SMS config', $provider !== ''
    && Config::get('SMS_API_KEY', '') !== ''
    && Config::get('SMS_USERNAME', '') !== '', "provider={$provider}");

try {
    new SMSGateway();
    $check('SMS gateway', true, 'provider initialized');
} catch (Throwable $e) {
    $check('SMS gateway', false, 'provider could not initialize');
}

$whatsappUrl = rtrim((string) Config::get('SMS_WHATSAPP_API_URL', 'https://chat.africastalking.com'), '/');
$check('WhatsApp config', Config::get('SMS_WHATSAPP_NUMBER', '') !== '' && filter_var($whatsappUrl, FILTER_VALIDATE_URL) !== false, 'endpoint and sender present');

try {
    new WhatsAppGateway();
    $check('WhatsApp gateway', true, 'provider initialized');
} catch (Throwable $e) {
    $check('WhatsApp gateway', false, 'provider could not initialize');
}

$targets = [
    'SMTP' => [(string) Config::get('SMTP_HOST', ''), (int) Config::get('SMTP_PORT', 587)],
    'SMS API' => [$provider === 'twilio' ? 'api.twilio.com' : 'api.' . ($provider === 'africastalking' && Config::get('SMS_USERNAME', '') === 'sandbox' ? 'sandbox.' : '') . 'africastalking.com', 443],
    'WhatsApp API' => [parse_url($whatsappUrl, PHP_URL_HOST) ?: '', (int) (parse_url($whatsappUrl, PHP_URL_PORT) ?: 443)],
];
foreach ($targets as $label => [$host, $port]) {
    $errno = 0;
    $error = '';
    $socket = $host !== '' ? @fsockopen($host, $port, $errno, $error, 5) : false;
    if (is_resource($socket)) fclose($socket);
    $check($label . ' net', $socket !== false, $socket !== false ? "{$host}:{$port}" : "{$host}:{$port}");
}

exit($failures === 0 ? 0 : 1);
