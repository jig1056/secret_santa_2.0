<?php
// ============================================================
// set_pref.php
// AJAX endpoint — saves a UI preference to SS_USER_PREFS.
// POST: pref_key, pref_value, xmas_year
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

header('Content-Type: application/json');

$userId   = currentUserId();
$prefKey  = trim($_POST['pref_key']   ?? '');
$prefVal  = trim($_POST['pref_value'] ?? '');
$xmasYear = trim($_POST['xmas_year']  ?? '');

$allowedKeys = ['wl_view', 'cl_view', 'gl_view'];
$allowedVals = ['grid', 'list'];

if (!in_array($prefKey, $allowedKeys, true) ||
    !in_array($prefVal, $allowedVals, true)  ||
    !$xmasYear) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

setUserPref($userId, $prefKey, $xmasYear, $prefVal);
echo json_encode(['success' => true]);
