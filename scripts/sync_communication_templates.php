<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Config;
use App\Database\Database;

Config::init();
$db = Database::getInstance()->getConnection();
$base = dirname(__DIR__) . '/api/modules/communications/templates/';
$sources = [
    'sms' => $base . 'communication_templates_sms.json',
    'whatsapp' => $base . 'communication_templates_whatsapp.json',
];

$db->beginTransaction();
try {
    foreach ($sources as $channel => $file) {
        $templates = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        foreach ($templates as $template) {
            $category = (string) ($template['category'] ?? 'notification');
            $code = $channel . '.' . $category;
            $name = (string) ($template['name'] ?? $code);
            $db->prepare(
                "INSERT INTO communication_template_catalog (code, name, purpose, status)
                 VALUES (?, ?, ?, 'active')
                 ON DUPLICATE KEY UPDATE name = VALUES(name), purpose = VALUES(purpose), status = 'active'"
            )->execute([$code, $name, $category]);
            $stmt = $db->prepare("SELECT id FROM communication_template_catalog WHERE code = ?");
            $stmt->execute([$code]);
            $catalogId = (int) $stmt->fetchColumn();

            $db->prepare(
                "INSERT INTO communication_template_versions (template_id, version_no, status)
                 VALUES (?, 1, 'active')
                 ON DUPLICATE KEY UPDATE status = 'active'"
            )->execute([$catalogId]);
            $stmt = $db->prepare("SELECT id FROM communication_template_versions WHERE template_id = ? AND version_no = 1");
            $stmt->execute([$catalogId]);
            $versionId = (int) $stmt->fetchColumn();

            $db->prepare(
                "INSERT INTO communication_template_channels
                    (template_version_id, channel, subject, body)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body)"
            )->execute([$versionId, $channel, $template['subject'] ?? null, $template['template_body'] ?? '']);
            $stmt = $db->prepare("SELECT id FROM communication_template_channels WHERE template_version_id = ? AND channel = ? AND language_code = 'en'");
            $stmt->execute([$versionId, $channel]);
            $channelId = (int) $stmt->fetchColumn();

            $variables = json_decode((string) ($template['variables_json'] ?? '{}'), true) ?: [];
            foreach ($variables as $variable => $type) {
                $db->prepare(
                    "INSERT INTO communication_template_variables
                        (template_channel_id, variable_name, data_type, is_required)
                     VALUES (?, ?, ?, 0)
                     ON DUPLICATE KEY UPDATE data_type = VALUES(data_type)"
                )->execute([$channelId, $variable, in_array($type, ['integer','decimal','date','datetime','url','boolean'], true) ? $type : 'string']);
            }
        }
    }
    $db->commit();
    echo json_encode(['status' => 'success', 'message' => 'Communication templates synchronized']) . PHP_EOL;
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
