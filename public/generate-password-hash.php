<?php
/**
 * Generate password hash for admin user
 * Run this once to get the hash for Ujjwal@2026
 */

$password = 'Ujjwal@2026';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password: {$password}\n";
echo "Hash: {$hash}\n";
echo "\nCopy this hash to migrations.sql\n";
