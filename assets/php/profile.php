<?php
header('Content-Type: application/json');

function getBearerToken(): string {
  $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!$auth && function_exists('getallheaders')) {
    $h = getallheaders();
    $auth = $h['Authorization'] ?? '';
  }
  if (stripos($auth, 'Bearer ') === 0) return trim(substr($auth, 7));
  return '';
}

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

/* --------- AUTH (Token -> Redis -> userId) --------- */
$token = getBearerToken();
if ($token === '') {
  http_response_code(401);
  echo json_encode(["success" => false, "message" => "Missing token"]);
  exit;
}

$tokenHash = hash('sha256', $token);
$redisKey = "session:" . $tokenHash;

/* Redis host: keep same as login.php */
$redisHost = "127.0.0.1";
$redisPort = 6379;

try {
  $userId = redisCommand($redisHost, $redisPort, ["GET", $redisKey]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => $e->getMessage()]);
  exit;
}

if (!$userId) {
  http_response_code(401);
  echo json_encode(["success" => false, "message" => "Invalid/expired session"]);
  exit;
}
$userId = (int)$userId;

/* --------- MongoDB connect --------- */
try {
  $mongo = new MongoDB\Driver\Manager("mongodb://127.0.0.1:27017");
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["success" => false, "message" => "MongoDB connection failed"]);
  exit;
}

$action = $_POST['action'] ?? 'whoami';

/* --------- Actions --------- */
if ($action === 'whoami') {
  echo json_encode(["success" => true, "message" => "OK", "data" => ["user_id" => $userId]]);
  exit;
}

if ($action === 'logout') {
  try {
    redisCommand($redisHost, $redisPort, ["DEL", $redisKey]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
  }
  echo json_encode(["success" => true, "message" => "Logged out"]);
  exit;
}

if ($action === 'fetch') {
  $query = new MongoDB\Driver\Query(['user_id' => $userId], ['limit' => 1]);
  $cursor = $mongo->executeQuery('profile_db.profiles', $query);
  $doc = current($cursor->toArray());

  echo json_encode([
    "success" => true,
    "message" => "Profile fetched",
    "data" => [
      "age" => isset($doc->age) ? $doc->age : "",
      "dob" => isset($doc->dob) ? $doc->dob : "",
      "contact" => isset($doc->contact) ? $doc->contact : ""
    ]
  ]);
  exit;
}

if ($action === 'update') {
  $age = trim($_POST['age'] ?? '');
  $dob = trim($_POST['dob'] ?? '');
  $contact = trim($_POST['contact'] ?? '');

  // validation (simple)
  if ($age !== '' && (!ctype_digit($age) || (int)$age < 1 || (int)$age > 120)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid age"]);
    exit;
  }
  if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "DOB must be YYYY-MM-DD"]);
    exit;
  }

  $bulk = new MongoDB\Driver\BulkWrite();
  $bulk->update(
    ['user_id' => $userId],
    ['$set' => [
      'user_id' => $userId,
      'age' => ($age === '' ? "" : (int)$age),
      'dob' => ($dob === '' ? "" : $dob),
      'contact' => $contact,
      'updated_at' => new MongoDB\BSON\UTCDateTime()
    ]],
    ['upsert' => true]
  );

  try {
    $mongo->executeBulkWrite('profile_db.profiles', $bulk);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "MongoDB write failed"]);
    exit;
  }

  echo json_encode(["success" => true, "message" => "Profile updated"]);
  exit;
}

http_response_code(400);
echo json_encode(["success" => false, "message" => "Invalid action"]);