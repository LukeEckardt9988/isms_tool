<?php
// Verwenden Sie ein starkes, eindeutiges Passwort
$newPassword = 'interact'; // Ändern Sie dies!
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
echo "Benutzername: tester<br>";
echo "Passwort (zum Testen merken): " . htmlspecialchars($newPassword) . "<br>";
echo "Hash (für die Datenbank): " . htmlspecialchars($hashedPassword);
?>