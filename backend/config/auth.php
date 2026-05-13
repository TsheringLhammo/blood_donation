<?php
declare(strict_types=1);

// Global CORS headers for API endpoints. Placed here so every API that includes
// this auth file automatically allows cross-origin requests from the React dev server.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// If this is a preflight request, respond immediately.
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!function_exists('bts_base64url_encode')) {
    function bts_base64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('bts_base64url_decode')) {
    function bts_base64url_decode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}

if (!function_exists('bts_get_secret')) {
    function bts_get_secret(): string
    {
        $fromEnv = getenv('BTS_APP_SECRET');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return $fromEnv;
        }

        return 'bts-local-dev-secret-change-in-production';
    }
}

if (!function_exists('bts_create_token')) {
    function bts_create_token(array $user, int $ttlSeconds = 28800): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();

        $payload = [
            'sub' => (int)($user['id'] ?? 0),
            'email' => (string)($user['email'] ?? ''),
            'role' => (string)($user['role'] ?? ''),
            'donor_id' => isset($user['donor_id']) ? (int)$user['donor_id'] : 0,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        $encodedHeader = bts_base64url_encode((string)json_encode($header));
        $encodedPayload = bts_base64url_encode((string)json_encode($payload));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, bts_get_secret(), true);
        $encodedSignature = bts_base64url_encode($signature);

        return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
    }
}

if (!function_exists('bts_verify_token')) {
    function bts_verify_token(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $expectedSignature = bts_base64url_encode(
            hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, bts_get_secret(), true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return null;
        }

        $payloadRaw = bts_base64url_decode($encodedPayload);
        $payload = json_decode($payloadRaw, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = (int)($payload['exp'] ?? 0);
        if ($exp <= 0 || $exp < time()) {
            return null;
        }

        return $payload;
    }
}

if (!function_exists('bts_get_bearer_token')) {
    function bts_get_bearer_token(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? $headers['AUTHORIZATION'] ?? '');
            }
        }

        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                $header = (string)($headers['Authorization'] ?? $headers['authorization'] ?? $headers['AUTHORIZATION'] ?? '');
            }
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim((string)($matches[1] ?? ''));
        return $token !== '' ? $token : null;
    }
}

if (!function_exists('bts_json_response')) {
    function bts_json_response(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('bts_require_auth')) {
    function bts_require_auth(array $roles = []): array
    {
        $token = bts_get_bearer_token();
        if ($token === null) {
            bts_json_response(401, ['success' => false, 'message' => 'Missing authorization token.']);
        }

        $claims = bts_verify_token($token);
        if ($claims === null) {
            bts_json_response(401, ['success' => false, 'message' => 'Invalid or expired token.']);
        }

        if ($roles !== []) {
            $role = (string)($claims['role'] ?? '');
            if (!in_array($role, $roles, true)) {
                bts_json_response(403, ['success' => false, 'message' => 'Insufficient permissions.']);
            }
        }

        return $claims;
    }
}

if (!function_exists('bts_optional_auth')) {
    function bts_optional_auth(): ?array
    {
        $token = bts_get_bearer_token();
        if ($token === null) {
            return null;
        }

        return bts_verify_token($token);
    }
}
