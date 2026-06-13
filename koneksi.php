<?php
$pdo = new PDO('mysql:host=localhost;dbname=internet_billing', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
