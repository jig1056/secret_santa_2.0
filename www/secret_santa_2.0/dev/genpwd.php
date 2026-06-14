<?php
// ============================================================
// generate_hash.php
// Run this ONCE on your server to get the correct bcrypt hash
// for your test password, then delete it.
//
// Access it in your browser:
// https://web-ace.nelsonone.com/secret_santa_2.0/dev/generate_hash.php
// ============================================================

$password = 'test1234';
$hash     = password_hash($password, PASSWORD_BCRYPT);

echo '<pre>';
echo 'Password : ' . $password . "\n";
echo 'Hash     : ' . $hash     . "\n\n";
echo '-- Verify test --' . "\n";
echo 'Matches  : ' . (password_verify($password, $hash) ? 'YES ✓' : 'NO ✗') . "\n";
echo '</pre>';
echo '<hr>';
echo '<p>Copy the hash above, then run this SQL to update all test users:</p>';
echo '<pre>';
echo "UPDATE SS_USERS SET PASSWORD_HASH = '" . $hash . "' WHERE STATUS = 'ACTIVE';";
echo '</pre>';
echo '<p><strong>Delete this file from your server when done!</strong></p>';