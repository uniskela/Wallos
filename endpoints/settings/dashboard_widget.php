<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/widgets.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);

$widgetId = $data['widget_id'] ?? $data['value']['widget_id'] ?? null;
$enabled = $data['enabled'] ?? $data['value']['enabled'] ?? $data['value'] ?? null;

if (!is_string($widgetId) || !in_array($widgetId, wallos_widget_ids(), true)) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

if (!is_bool($enabled) && $enabled !== 0 && $enabled !== 1 && $enabled !== '0' && $enabled !== '1') {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

$enabledInt = ($enabled === true || $enabled === 1 || $enabled === '1') ? 1 : 0;
$column = wallos_widget_setting_column($widgetId);

// Column names come from a fixed allow-list in wallos_widget_ids().
$stmt = $db->prepare("UPDATE settings SET {$column} = :enabled WHERE user_id = :userId");
$stmt->bindValue(':enabled', $enabledInt, SQLITE3_INTEGER);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        'success' => true,
        'message' => translate('success', $i18n),
    ]));
}

die(json_encode([
    'success' => false,
    'message' => translate('error', $i18n),
]));
