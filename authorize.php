<?php
require_once 'private/config.php';
require_once 'private/Database.php';

use Firebase\JWT\JWT;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable('/var/www/api.todo.smail.de'); // TODO Document root
$dotenv->load();

function get_private_key_path(): string {
    return $_ENV['PRIVATE_KEY_PATH'];
}

function get_private_key_passphrase(): string {
    return $_ENV['PRIVATE_KEY_PASSPHRASE'];
}

function get_token_from_header(): ?string {
    if (($headers = apache_request_headers()) && isset($headers['Authorization'])) {
        return !empty($token = trim(str_replace('Bearer', '', $headers['Authorization']))) ? $token : null;
    }
    return null;
}

function authorize_token(string $token): false|array {
    $public_key =
        openssl_pkey_get_details(get_private_key($_ENV['PRIVATE_KEY_PATH'], $_ENV['PRIVATE_KEY_PASSPHRASE']))['key'];
    try {
        return (array)JWT::decode($token, $public_key, array('RS256'));
    } catch (Exception) {
        return false;
    }
}

function create_token(string $username, string $password, string $private_key_file_path, string $passphrase): ?string {
    if (empty($username) || empty($password)) {
        return null;
    }

    $db = new Database();
    $stmt = $db->create_stmt('SELECT UserId AS id, PasswordHash AS hash FROM User WHERE Username = :username',
        [':username' => $username]);
    if (!($res = $stmt->execute()) || !($row = $res->fetchArray(SQLITE3_ASSOC))) {
        return null;
    }

    $user_id = $row['id'];
    $password_hash = $row['hash'];

    if (password_verify($password, $password_hash)) {
        // Paranoia
        unset($password_hash, $row['hash']);

        $iat = time();
        $exp = $iat + 60 * 60;

        $payload = [
            'iss' => 'http://192.168.2.165:8082',
            'aud' => 'http://192.168.2.165:8082',
            'sub' => $user_id,
            'iat' => $iat,
            'exp' => $exp,
        ];

        return JWT::encode($payload, get_private_key($private_key_file_path, $passphrase), 'RS256');
    } else {
        return null;
    }
}

function get_private_key(string $private_key_file_path, string $passphrase): false|OpenSSLAsymmetricKey {
    return openssl_pkey_get_private(
        file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/' . $private_key_file_path),
        $passphrase
    );
}

function get_public_key(string $private_key_file_path): string {
//    return openssl_pkey_get_details($private_key)['key'];
    return file_get_contents($_SERVER['DOCUMENT_ROOT'] . $private_key_file_path . '.pub');
}

function encode(string $private_key_file_path, string $passphrase) {
    $iat = time();
    $exp = $iat + 60 * 60;

    $payload = array(
        'iss' => 'http://192.168.2.165:8082',
        'aud' => 'http://192.168.2.165:8082',
        'sub' => $user_id,
        'iat' => $iat,
        'exp' => $exp,
    );
    return JWT::encode($payload, get_private_key($private_key_file_path, $passphrase), 'RS256');
}

function decode() {
    echo openssl_pkey_get_details(get_private_key($_ENV['PRIVATE_KEY_PATH'], $_ENV['PRIVATE_KEY_PASSPHRASE']))['key'];

//    $decoded = JWT::decode($jwt, $publicKey, array('RS256'));
//    echo "Decode:\n" . print_r((array) $decoded, true) . "\n";
}