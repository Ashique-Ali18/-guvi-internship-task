<?php
header('Content-Type: application/json');

function redisCommand(string $host, int $port, array $parts) {
  $fp = fsockopen($host, $port, $errno, $errstr, 2);
  if (!$fp) throw new Exception("Redis connect failed: $errstr ($errno)");
  stream_set_timeout($fp, 2);

  $cmd = "*" . count($parts) . "\r\n";
  foreach ($parts as $p) {
    $p = (string)$p;
    $cmd .= "$" . strlen($p) . "\r\n" . $p . "\r\n";
  }
  fwrite($fp, $cmd);

  $line = fgets($fp);
  if ($line === false) { fclose($fp); throw new Exception("Redis no response"); }

  $type = $line[0];
  $payload = rtrim(substr($line, 1), "\r\n");

  if ($type === '+') { fclose($fp); return $payload; }
  if ($type === '-') { fclose($fp); throw new Exception("Redis error: " . $payload); }
  if ($type === ':') { fclose($fp); return (int)$payload; }

  if ($type === '$') {
    $len = (int)$payload;
    if ($len === -1) { fclose($fp); return null; }
    $data = '';
    while (strlen($data) < $len) {
      $chunk = fread($fp, $len - strlen($data));
      if ($chunk === false || $chunk === '') break;
      $data .= $chunk;
    }
    fread($fp, 2);
    fclose($fp);
    return $data;
  }

  fclose($fp);
  throw new Exception("Redis unexpected reply: " . $line);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["success" => false, "message" => "Method not allowed"]);
  exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
  http_response_code(400);
  echo json_encode(["success" => false, "message" => "Email and password required"]);
  exit;
}

/* MySQL (prepared statement) */
$conn = new mysqli("127.0.0.1", "root", "", "auth_db", 3307);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "MySQL connection failed"]);
  exit;
}

$stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE email=?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
  http_response_code(401);
  echo json_encode(["success" => false, "message" => "Invalid credentials"]);
  exit;
}

/* Redis session (NO PHP session) */
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$key = "session:" . $tokenHash;
$ttl = 3600;

/* If Redis is in WSL and 127.0.0.1 fails, replace with WSL IP */
$redisHost = "127.0.0.1";
$redisPort = 6379;

try {
  redisCommand($redisHost, $redisPort, ["SETEX", $key, $ttl, (string)$user['id']]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => $e->getMessage()]);
  exit;
}

echo json_encode([
  "success" => true,
  "message" => "Login successful",
  "data" => ["token" => $token]
]);