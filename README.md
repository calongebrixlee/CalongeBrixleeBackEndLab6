# LavaLust Framework

> A lightweight, fast PHP framework built for developers who want clean MVC architecture without unnecessary complexity or performance overhead.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF)](https://www.php.net/)
[![GitHub Stars](https://img.shields.io/github/stars/ronmarasigan/lavalust?style=flat)](https://github.com/ronmarasigan/lavalust/stargazers)

---

## Overview

**LavaLust** is an open-source PHP framework that follows the **MVC (Model–View–Controller)** architectural pattern. It is designed for developers who need a structured, maintainable, and scalable foundation — without the bloat of heavier modern frameworks.

Whether you are building a simple web application, a REST API, or a teaching project, LavaLust provides the right tools with minimal friction.

---

## Features

| Feature | Description |
|---|---|
| **MVC Architecture** | Clean separation of Models, Views, and Controllers for organized, maintainable code |
| **Built-in Routing** | Flexible URL routing that maps requests to controllers with minimal configuration |
| **Libraries & Helpers** | Reusable components for sessions, forms, validation, and database access |
| **Modular Design** | Scalable structure that supports clean organization as your application grows |
| **REST API Support** | First-class support for building RESTful APIs using LavaLust conventions |
| **ORM-like Models** | Simplified, readable database interaction without a heavy abstraction layer |

---

## Requirements

- PHP 7.4 or higher
- A web server with URL rewriting support (Apache `.htaccess` or Nginx config)
- Composer (optional, for dependency management)

---

## Installation

**Clone the repository:**

```bash
git clone https://github.com/ronmarasigan/lavalust.git
cd lavalust
```

**Or download a release directly:**

```bash
wget https://github.com/ronmarasigan/lavalust/archive/refs/heads/main.zip
unzip main.zip
```

Configure your web server to point to the project root and ensure `mod_rewrite` (Apache) or equivalent is enabled.

---

## Quick Start

### 1. Define a Route

**File:** `app/config/routes.php`

```php
$router->get('/', 'Welcome::index');
$router->get('/about', 'Welcome::about');
$router->post('/users/store', 'Users::store');
```

### 2. Create a Controller

**File:** `app/controllers/Welcome.php`

```php
<?php

class Welcome extends Controller
{
    public function index()
    {
        $data['title'] = 'Home';
        $this->call->view('welcome', $data);
    }

    public function about()
    {
        $this->call->view('about');
    }
}
```

### 3. Create a View

**File:** `app/views/welcome.php`

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
</head>
<body>
    <h1>Welcome to LavaLust Framework</h1>
    <p>Lightweight. Fast. MVC.</p>
</body>
</html>
```

### 4. Create a Model

**File:** `app/models/User_model.php`

```php
<?php

class User_model extends Model
{
    protected $table = 'users';

    public function getAll()
    {
        return $this->db->table($this->table)->get()->getResult();
    }

    public function findById(int $id)
    {
        return $this->db->table($this->table)
                        ->where('id', $id)
                        ->get()
    }
}
```

---

## Project Structure

```
lavalust/
├── app/
│   ├── config/          # Application configuration (database, routes, etc.)
│   ├── controllers/     # Controller classes
│   ├── models/          # Model classes
│   ├── views/           # View templates
│   └── libraries/       # Custom libraries and helpers
├── scheme/              # Core framework files (do not modify)
├── public/              # Publicly accessible entry point
│   └── index.php
└── runtime/            # Cache, logs, and uploads (must be writable)
```

---

## Configuration

### Database

**File:** `app/config/database.php`

```php
$database['main'] = array(
    'driver'	=> getenv('DB_DRIVER') ?: '',
    'hostname'	=> getenv('DB_HOST') ?: '',
    'port'		=> getenv('DB_PORT') ?: '',
    'username'	=> getenv('DB_USER') ?: '',
    'password'	=> getenv('DB_PASSWORD') ?: '',
    'database'	=> getenv('DB_NAME') ?: '',
    'charset'	=> getenv('DB_CHARSET') ?: '',
    'dbprefix'	=> getenv('DB_PREFIX') ?: '',
    // Optional for SQLite
    'path'      => ''
);
```

### Base URL

**File:** `app/config/config.php`

```php
$config['base_url'] = 'http://localhost:3000/';
```

---

## Building a REST API

LavaLust supports REST API development out of the box. Controllers can return JSON responses for API endpoints.

### Product API and authentication

This project includes a product CRUD API protected by LavaLust's JWT authentication. Product API calls require an `Authorization: Bearer <access_token>` header. The login, refresh, and logout routes are:

| Method | Endpoint | Description |
| --- | --- | --- |
| POST | `/api/auth/register` | Create a user account and return tokens |
| POST | `/api/auth/login` | Sign in with `email` (or `username`) and `password`; returns access and refresh tokens |
| GET | `/api/auth/me` | Get the current authenticated user |
| POST | `/api/auth/refresh` | Exchange a valid `refresh_token` for a new token pair |
| POST | `/api/auth/logout` | Revoke the supplied refresh token and log out |

Provision the first administrator through environment variables, then apply migrations:

```sh
php lava jwt:generate
INITIAL_ADMIN_EMAIL=admin@example.com INITIAL_ADMIN_PASSWORD='use-a-long-unique-password' php lava migration run
```

Set `INITIAL_ADMIN_USERNAME` optionally. The initial administrator is only added when the email/password variables are provided; use a secure password and keep these secrets out of source control.

The API root (`/`) returns a JSON status document and a list of available endpoints.

The included React app is in `frontend/`. Run it locally with:

```sh
cd frontend
npm ci
npm run dev
```

Set `VITE_API_BASE_URL` to the API base URL (default: `http://localhost:3000/api`) when using a different local API address. The React app keeps bearer tokens in `sessionStorage`, refreshes expired access tokens, and provides login, product listing, add/edit/delete, and logout.

To deploy the API and UI with Render, create a Blueprint from `render.yaml`. Add your Aiven MySQL password to the API service environment and set the initial admin email and password before the first deployment; the container applies pending migrations on startup. The Aiven Project CA certificate is bundled as `aiven-ca.pem` and configured as the MySQL SSL CA. Update this certificate if the Aiven project CA is rotated. The Blueprint sets the UI URL as the CORS origin. Keep generated `JWT_SECRET` and `REFRESH_TOKEN_KEY` values private and do not commit them.

Migration `006_normalize_products_table` aligns an existing `products` table with the API's `id`, `name`, `description`, `price`, and `stock` fields. Recognized legacy column names are renamed in place; missing optional/product fields are added without dropping existing rows.

For a local SQLite database, generate API keys, set the initial administrator credentials in `.env`, then run migrations. Use a unique, secure password:

```sh
php lava jwt:generate
INITIAL_ADMIN_EMAIL=admin@example.com INITIAL_ADMIN_PASSWORD='use-a-long-unique-password' php lava migration run
```

The product endpoints accept JSON request bodies:

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/api/products` | List products |
| GET | `/api/products/{id}` | Get one product |
| POST | `/api/products` | Create a product |
| PUT | `/api/products/{id}` | Replace a product |
| PATCH | `/api/products/{id}` | Partially update a product |
| DELETE | `/api/products/{id}` | Delete a product |

Product fields are `name` (required), `price` (required, non-negative), `description` (optional), and `stock` (optional, non-negative integer). For example:

```json
{
  "name": "Desk Lamp",
  "description": "Adjustable LED lamp",
  "price": 24.99,
  "stock": 12
}
```

The API uses LavaLust's `Api` library for JSON responses and returns standard HTTP status codes, including `201` for creation, `401` for unauthenticated requests, `404` for missing products, and `422` for invalid product data. Keep the generated keys in `.env`; do not commit them.

```php
<?php

class Api extends Controller
{
    $this->call->library('api');

    public function users()
    {
        $this->api->require_method('GET');
        $auth = $this->api->require_jwt(); 

        $this->call->model('User_model');
        $users = $this->User_model->getAll();

        $this->api->respond(['data' => $users]);
    }
}
```

Route definition:

```php
$router->get('/api/users', 'Api::users');
```

---

## Philosophy

LavaLust is built on a single principle: **minimal core, maximum control.**

Modern frameworks often add layers of abstraction that benefit large enterprise teams but get in the way of developers who want to understand exactly what their code is doing. LavaLust provides structure and utilities without hiding the underlying logic — making it an excellent choice for:

- **Rapid prototyping** — Get an application running in minutes
- **Learning MVC** — Understand how each architectural layer works
- **Lightweight production apps** — Deploy without dragging in unused dependencies
- **Teaching PHP development** — Clear conventions, readable source code

---

## Documentation

Full documentation is available at **[https://lavalust.netlify.app](https://lavalust.netlify.app)**

Topics covered include:

- Installation and server configuration
- Routing: static, dynamic, and grouped routes
- Controllers and request handling
- Models and query builder
- Views, layouts, and partials
- Built-in libraries (sessions, form validation, file upload)
- Helper functions
- REST API development
- Security best practices

---

## Contributing

Contributions are welcome. To contribute:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Commit your changes: `git commit -m "Add your feature description"`
4. Push to your branch: `git push origin feature/your-feature-name`
5. Open a pull request against `main`

Please ensure your code follows the existing style conventions and includes relevant documentation or comments where appropriate.

---

## Roadmap

- [ ] CLI tool for generating controllers, models, and migrations
- [ ] Middleware support
- [ ] Improved query builder with relationship support
- [ ] Enhanced error handling and debugging tools

---

## License

LavaLust Framework is open-source software licensed under the **[MIT License](https://opensource.org/licenses/MIT)**.

---

## Links

- **GitHub Repository:** [https://github.com/ronmarasigan/lavalust](https://github.com/ronmarasigan/lavalust)
- **Documentation:** [https://lavalust.netlify.app](https://lavalust.netlify.app)
- **Report an Issue:** [https://github.com/ronmarasigan/lavalust/issues](https://github.com/ronmarasigan/lavalust/issues)