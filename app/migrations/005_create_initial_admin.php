<?php

class Create_initial_admin {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $email = trim((string) getenv('INITIAL_ADMIN_EMAIL'));
        $password = (string) getenv('INITIAL_ADMIN_PASSWORD');

        if ($email === '' && $password === '') {
            throw new RuntimeException(
                'Set INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD before running migrations.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || trim($password) === '') {
            throw new RuntimeException(
                'Set a valid INITIAL_ADMIN_EMAIL and a non-empty INITIAL_ADMIN_PASSWORD, or leave both unset.'
            );
        }

        $existing = $this->_lava->db->raw(
            'SELECT id FROM users WHERE email = ? LIMIT 1',
            [$email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return;
        }

        $username = trim((string) getenv('INITIAL_ADMIN_USERNAME'));
        if ($username === '') {
            $username = strstr($email, '@', true) ?: 'admin';
        }

        if (strlen($username) > 100 || $username === '') {
            throw new RuntimeException('INITIAL_ADMIN_USERNAME must be between 1 and 100 characters.');
        }

        $this->_lava->db->raw(
            'INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), 'admin', 1]
        );
    }

    public function down()
    {
        // Keep the provisioned account when rolling back the migration.
    }
}
