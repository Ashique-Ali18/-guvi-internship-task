<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["success" => false, "message" => "Method not allowed"]);
  exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Invalid email"]);
  exit;
}

if (strlen($password) < 6) {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Password must be at least 6 characters"]);
  exit;
}

$conn = new mysqli("127.0.0.1", "root", "", "auth_db", 3307);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "MySQL connection failed"]);
  exit;
}

$check = $conn->prepare("SELECT id FROM users WHERE email=?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
  http_response_code(409);
  echo json_encode(["success" => false, "message" => "Email already registered"]);
  exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
$stmt->bind_param("ss", $email, $hash);

if (!$stmt->execute()) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "Registration failed"]);
  exit;
}

echo json_encode(["success" => true, "message" => "Registered successfully"]);