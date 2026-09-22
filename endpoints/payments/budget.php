<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$postData = file_get_contents('php://input');
$data = json_decode($postData, true);

$paymentMethodId = isset($data['payment_method_id']) ? (int) $data['payment_method_id'] : 0;
$budget = isset($data['budget']) ? (float) $data['budget'] : null;

if ($paymentMethodId <= 0 || $budget === null || $budget < 0 || !is_finite($budget)) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

$budget = max(0, $budget);

$check = $db->prepare('SELECT id FROM payment_methods WHERE id = :id AND user_id = :userId');
$check->bindValue(':id', $paymentMethodId, SQLITE3_INTEGER);
$check->bindValue(':userId', $userId, SQLITE3_INTEGER);
$existing = $check->execute()->fetchArray(SQLITE3_ASSOC);

if (!$existing) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

$stmt = $db->prepare('UPDATE payment_methods SET budget = :budget WHERE id = :id AND user_id = :userId');
$stmt->bindValue(':budget', $budget, SQLITE3_FLOAT);
$stmt->bindValue(':id', $paymentMethodId, SQLITE3_INTEGER);
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
