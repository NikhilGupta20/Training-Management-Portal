<?php
header('Content-Type: application/json');

$pdo = new PDO("mysql:host=localhost;dbname=ncrb_training", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$state = $_GET['state'] ?? '';
$stmt = $pdo->prepare("SELECT district_name FROM districts WHERE state_name = :state_name ORDER BY district_name");
$stmt->execute([':state_name' => $state]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
