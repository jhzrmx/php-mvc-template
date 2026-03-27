# PHP MVC Template (with RedBean)

A lightweight **PHP MVC starter** with a simple router, middleware support, JWT authentication, and [RedBeanPHP](https://github.com/gabordemooij/redbean) for database access. Designed to be easy to understand, easy to extend, and friendly for those who started with Laravel.

## Features

- **MVC-ish structure**: `routes/`, `controllers/`, `models/`, `views/`
- **Router with middleware + groups**: supports `GET/POST/PUT/PATCH/DELETE`, route params (`/:id`), and route groups
- **Dependency injection (lightweight)**: controller methods can receive `Request` and `Response`
- **JWT auth** (HS256): login/register endpoints + protected routes via middleware
- **DotEnv loader**: `.env` support with basic type casting and variable expansion
- **RedBeanPHP** ORM: simple model base class and example `User` model
- **Friendly defaults**: ready-to-run pages (`/login`, `/signup`, `/dashboard`) and API routes under `/api/*`

## Requirements

- **PHP 8+** (recommended, some libraries I made supports PHP 5.6+)
- A database supported by PDO (see [RedBeanPHP connection guide](https://www.redbeanphp.com/index.php?p=/connection))

## Quick start

1) **Clone**

```bash
git clone https://github.com/jhzrmx/php-mvc-template
cd php-mvc-template
```

2) **Create your environment file**

Copy `.env.example` to `.env` and fill in values:

```env
JWT_SECRET=your-super-secret-key-at-least-32-chars-long
JWT_EXPIRES_IN=3600

DB_DSN=mysql:host=127.0.0.1;dbname=myapp
DB_USER=root
DB_PASS=secret
```

Notes:
- `JWT_SECRET` is **required** and must be **at least 32 characters** (enforced in `index.php` and `libs/JWT.php`).
- `DB_*` is used by RedBeanPHP via `R::setup(...)` in `index.php`.

3) **Run the app**

### Option A: Built-in PHP server (fastest)

```bash
php -S localhost:8000
```

On Windows, you can also run:

```bat
serve.cmd
```

Then open:
- `http://localhost:8000/login`
- `http://localhost:8000/signup`
- `http://localhost:8000/dashboard`

### Option B: Apache

If using Apache, ensure `mod_rewrite` is enabled and the included `.htaccess` is respected so all requests route through `index.php`.

## Project structure

```text
.
├─ controllers/          # Controllers (e.g., AuthController, UserController)
├─ models/               # Models (RedBean-backed)
├─ routes/               # Route definitions (web + API groups)
├─ middleware/           # Route middleware (auth, authRedirect)
├─ views/                # Static HTML pages
├─ libs/                 # Router, JWT, DotEnv, Auth, RedBeanPHP
├─ index.php             # App bootstrap
├─ .env.example          # Environment template
├─ .htaccess             # Apache rewrite to index.php
└─ serve.cmd             # Convenience runner (Windows)
```

## How routing works

Routes are registered in `routes/web.php` and (via groups) in `routes/*.routes.php`.

- **Static file/page routes**

```php
Route::get('/login', 'views/login.html');
```

- **Controller routes**

```php
Route::post('/login', 'AuthController@login');
```

- **Route parameters**

```php
Route::get('/api/users/:id', 'UserController@show');
```

- **Route groups**

```php
Route::group('/api/users', 'user.routes', ['auth']);
```

## Request / Response

Controller methods can type-hint `Request` and/or `Response` and they’ll be passed automatically by the router.

- **`Request`** provides:
  - `body`: JSON-decoded body (if valid JSON) or raw input string
  - `params`: query string parameters (`$_GET`)
  - `formData`: form body (`$_POST`)
  - `files`: uploaded files (`$_FILES`)

- **`Response`** provides:
  - `status(int $code)`
  - `json(array $data)` (ends request)
  - `send(string $text, string $contentType = 'text/plain')` (ends request)
  - `file(string $file)` (includes a PHP/HTML file and ends request)

## Authentication (JWT)

JWT is initialized at boot and enforced via middleware:

- **API protection**: `middleware/auth.php` returns `401` JSON on failure
- **Page protection**: `middleware/authRedirect.php` redirects to `/login` on failure

### Token sources supported by middleware

- `Authorization: Bearer <token>`
- `X-Auth-Token: <token>`
- Cookie `token=<token>`

### Auth endpoints

Base path: `/api/auth`

- `POST /api/auth/register` → create user + return token
- `POST /api/auth/login` → verify credentials + return token
- `GET /api/auth/me` (**protected**) → return current user

Example login:

```bash
curl -X POST http://localhost:8000/api/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"username\":\"demo\",\"password\":\"secret123\"}"
```

Use the returned token:

```bash
curl http://localhost:8000/api/auth/me ^
  -H "Authorization: Bearer <token>"
```

## Users API

Base path: `/api/users` (protected by `auth` middleware via `routes/web.php`)

- `GET /api/users/` → list users
- `GET /api/users/:id` → show user
- `POST /api/users/` → create user
- `PUT /api/users/:id` → replace/update user fields
- `PATCH /api/users/:id` → partial update
- `DELETE /api/users/:id` → delete user

## Database (RedBeanPHP)

This template uses RedBeanPHP (`libs/RedbeanPHP/`) with a small `Model` base class in `libs/RedbeanPHP/Model.php`.

- Example model: `models/User.php`
  - hashes passwords with `password_hash()`
  - omits `password_hash` from API output

## Contributing

Contributions are welcome. If you’re unsure where to start, check the suggested areas in the “Ideas / Roadmap” section below.

### Development guidelines

- **Don’t commit secrets**: `.env` is ignored; use `.env.example` for documentation.
- **Keep changes focused**: one feature/fix per PR when possible.
- **Prefer consistency**: follow existing naming patterns in `routes/`, `controllers/`, and `models/`.
- **Add/adjust routes responsibly**: keep API routes under `/api/*` and page routes in `routes/web.php`.

### How to contribute

1) Fork the repo
2) Create a branch:

```bash
git checkout -b feat/your-feature
```

3) Make changes and test locally (run `php -S localhost:8000`)
4) Open a pull request describing:
   - what you changed
   - why you changed it
   - how to test

## Security notes

- JWT uses **HS256** and requires a strong `JWT_SECRET`.
- Do not store sensitive data in JWT payloads.
- Use HTTPS in production when sending tokens via headers/cookies.

## Credits

- RedBeanPHP is included under `libs/RedbeanPHP/` with license

