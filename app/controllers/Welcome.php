<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Welcome extends Controller {
    public function index()
    {
        $this->call->library('api');

        $this->api->respond([
            'name'    => 'Product Management API',
            'status'  => 'running',
            'version' => '1.0.0',
            'docs'    => [
                'POST /api/auth/register',
                'POST /api/auth/login',
                'POST /api/auth/refresh',
                'POST /api/auth/logout',
                'GET /api/auth/me',
                'GET /api/products',
                'GET|PUT|PATCH|DELETE /api/products/{id}',
            ],
        ]);
    }
}