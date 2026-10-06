<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller {

    private $api;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->call->library('api');
        $this->db = $this->call->database();
    }

    public function health()
    {
        $driver = strtolower(database_config()['main']['driver'] ?? 'mysql');
        if ($driver === 'sqlite') {
            $columns = $this->db->raw('PRAGMA table_info(`products`)')->fetchAll(PDO::FETCH_ASSOC);
            $columns = array_column($columns, 'name');
        } else {
            $columns = $this->db->raw(
                'SELECT COLUMN_NAME FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ?',
                ['products']
            )->fetchAll(PDO::FETCH_COLUMN);
        }

        $missing = array_values(array_diff(['id', 'name', 'description', 'price', 'stock'], $columns));
        if ($missing) {
            $this->api->respond([
                'status' => 'schema_error',
                'missing_columns' => $missing,
            ], 503);
        }

        $this->api->respond([
            'status'   => 'ok',
            'revision' => getenv('RENDER_GIT_COMMIT') ?: 'unknown',
        ]);
    }

    public function register()
    {
        $this->api->rate_limit(
            'register-' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            5,
            60
        );

        $input = $this->request->json();
        if (!is_array($input)) {
            $this->api->respond_error('Request body must be a valid JSON object', 400);
        }

        $username = $input['username'] ?? null;
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;
        $errors = [];

        if (
            !is_string($username)
            || !preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)
        ) {
            $errors['username'] = 'Username must be 3-100 characters and contain only letters, numbers, dots, underscores, or hyphens.';
        }

        if (!is_string($email) || !filter_var(trim($email), FILTER_VALIDATE_EMAIL) || strlen(trim($email)) > 255) {
            $errors['email'] = 'A valid email address of at most 255 characters is required.';
        } else {
            $email = strtolower(trim($email));
        }

        if (!is_string($password) || trim($password) === '' || strlen($password) > 72) {
            $errors['password'] = 'Password is required and must be at most 72 characters.';
        }

        if ($errors) {
            $this->api->respond([
                'error'   => 'Validation failed',
                'details' => $errors,
                'status'  => 422,
            ], 422);
        }

        $existing = $this->db->raw(
            'SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1',
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $this->api->respond_error('Email or username is already registered', 409);
        }

        $this->db->raw(
            'INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), 'user', 1]
        );
        $user_id = $this->db->raw('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])->fetchColumn();

        $tokens = $this->api->issue_tokens([
            'id'     => $user_id,
            'role'   => 'user',
            'scopes' => ['products:read', 'products:write', 'products:delete'],
        ]);

        $this->api->respond([
            'user' => [
                'id'       => (int) $user_id,
                'username' => $username,
                'email'    => $email,
                'role'     => 'user',
            ],
            'tokens' => $tokens,
        ], 201);
    }

    public function login()
    {
        $this->api->rate_limit(
            'login-' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            10,
            60
        );

        $input = $this->request->json();
        if (!is_array($input)) {
            $this->api->respond_error('Request body must be a valid JSON object', 400);
        }

        $identity = $input['email'] ?? $input['username'] ?? null;
        $password = $input['password'] ?? null;
        if (!is_string($identity) || trim($identity) === '' || !is_string($password) || $password === '') {
            $this->api->respond_error('Email or username and password are required', 422);
        }
        $identity = trim($identity);

        $stmt = $this->db->raw(
            'SELECT id, username, email, password, role, is_active FROM users WHERE email = ? OR username = ? LIMIT 1',
            [$identity, $identity]
        );
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$user
            || (int) $user['is_active'] !== 1
            || !password_verify($password, $user['password'])
        ) {
            $this->api->respond_error('Invalid credentials', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => $user['id'],
            'role'   => $user['role'],
            'scopes' => ['products:read', 'products:write', 'products:delete'],
        ]);

        $this->api->respond([
            'user' => [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
            'tokens' => $tokens,
        ]);
    }

    public function me()
    {
        $payload = $this->api->require_jwt();
        $user = $this->db->raw(
            'SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1',
            [$payload['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        $this->api->respond([
            'user' => [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
        ]);
    }

    public function refresh()
    {
        $input = $this->request->json();
        if (!is_array($input) || empty($input['refresh_token']) || !is_string($input['refresh_token'])) {
            $this->api->respond_error('A refresh token is required', 422);
        }

        $this->api->refresh_access_token($input['refresh_token']);
    }

    public function logout()
    {
        $this->api->require_jwt();
        $input = $this->request->json();

        if (is_array($input) && !empty($input['refresh_token']) && is_string($input['refresh_token'])) {
            $this->api->revoke_refresh_token($input['refresh_token']);
        }

        $this->api->respond(['message' => 'Logged out']);
    }
}
