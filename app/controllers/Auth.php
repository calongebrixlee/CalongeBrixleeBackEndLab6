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
        $this->db->raw('SELECT 1');
        $this->api->respond(['status' => 'ok']);
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
