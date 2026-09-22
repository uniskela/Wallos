<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/widgets.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);

$widgets = $data['widgets'] ?? null;
$normalized = wallos_normalize_dashboard_widget_layout_input($widgets);

if ($normalized === null) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

$json = json_encode($normalized, JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare('UPDATE settings SET dashboard_widget_layout = :layout WHERE user_id = :userId');
$stmt->bindValue(':layout', $json, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    // Keep legacy boolean columns in sync so older readers stay consistent.
    foreach ($normalized as $entry) {
        $column = wallos_widget_setting_column($entry['widget_id']);
        $enabled = $entry['enabled'] ? 1 : 0;
        $flagStmt = $db->prepare("UPDATE settings SET {$column} = :enabled WHERE user_id = :userId");
        $flagStmt->bindValue(':enabled', $enabled, SQLITE3_INTEGER);
        $flagStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $flagStmt->execute();
    }

    die(json_encode([
        'success' => true,
        'message' => translate('success', $i18n),
        'widgets' => $normalized,
    ]));
}

die(json_encode([
    'success' => false,
    'message' => translate('error', $i18n),
]));
