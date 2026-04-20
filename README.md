# PHP MVC Template (with RedBean)

A lightweight **PHP MVC starter** with a simple router, middleware support, JWT authentication, and [RedBeanPHP](https://github.com/gabordemooij/redbean) for database access. Designed to be easy to understand, easy to extend, and friendly for those who started with Laravel.

## Features

- **Model-View-Controller structure**: `routes/`, `controllers/`, `models/`, `views/`
- **Router with middleware + groups**: supports `GET/POST/PUT/PATCH/DELETE`, route params (`/:id`), and route groups
- **Dependency injection (lightweight)**: controller methods can receive `Request` and `Response`
- **JWT auth** (HS256): login/register endpoints + protected routes via middleware
- **DotEnv loader**: `.env` support with basic type casting and variable expansion
- **RedBeanPHP** ORM: simple model base class and example `User` model
- **Friendly defaults**: ready-to-run pages (`/login`, `/signup`, `/dashboard`) and API routes under `/api/*`

## Requirements

- **PHP 8+** (recommended, some libraries I made still support PHP 5.6+)
- A database supported by PDO (see [RedBeanPHP connection guide](https://www.redbeanphp.com/index.php?p=/connection))

## Quick start

### Step 1: Clone

```bash
git clone https://github.com/jhzrmx/php-mvc-template
cd php-mvc-template
```

### Step 2: Setup (Choose one approach)

#### Option A: Interactive Setup (Recommended)

Run the interactive setup command to create your `.env` file and optionally set up a database:

```bash
php mvc init
```

This will guide you through:

- Creating a `.env` file from `.env.example`
- Choosing between SQLite or MySQL
- Optionally generating database tables

#### Option B: Manual Setup

1. **Create your environment file**

```bash
cp .env.example .env
```

2. **Adjust the environment variables**

Edit `.env` and configure:

```env
JWT_SECRET=your-super-secret-key-at-least-32-chars-long
JWT_EXPIRES_IN=3600

DB_DSN=mysql:host=127.0.0.1;dbname=myapp
DB_USER=root
DB_PASS=secret
```

Notes:

- `JWT_SECRET` is **required** and must be **at least 32 characters** (enforced in `libs/JWT.php`).
- `DB_*` is used by RedBeanPHP via `R::setup(...)` in `index.php`.

3. **Set up database** (if needed)

For SQLite:

```bash
php mvc setup:sqlite
```

Or with custom database name:

```bash
php mvc setup:sqlite --dbname myapp.db
```

### Step 3: Run the app

### Option A: Built-in PHP server (fastest)

```bash
php mvc serve
```

Or with custom port and host:

```bash
php mvc serve --port 8080 --host 0.0.0.0
```

On Windows, you can also run:

```bat
serve.cmd
```

Or use the native PHP server directly:

```bash
php -S localhost:8000
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
├─ views/                # Static HTML pages or dynamic PHP pages
├─ libs/                 # Router, JWT, DotEnv, Auth, RedBeanPHP
├─ mvc                   # CLI Runner
├─ index.php             # App bootstrap
├─ .env.example          # Environment template
├─ .htaccess             # Apache rewrite to index.php
└─ serve.cmd             # Convenience runner (Windows)
```

## How routing works

Routes are registered in `routes/web.php` and (via groups) in `routes/*.routes.php`.

- **Loading routes (bootstrap)**

Routes are loaded during app bootstrap from `index.php`:

```php
Route::init();
Route::loadModels();
Route::loadMiddlewares();
Route::loadRoutes(); // loads routes/web.php + routes/api.php (configurable)
```

- **Static file/page routes**

```php
Route::get('/login', 'views/login.html');
```

- **Controller routes**

```php
Route::post('/login', 'AuthController@login');
// Or use the new format:
Route::post('/login', [AuthController::class, 'login']);
```

- **Route parameters**

```php
Route::get('/api/users/:id', 'UserController@show');
// Or use the new format:
Route::post('/api/users/:id', [UserController::class, 'show']);
```

- **Route groups**

```php
Route::group('/api/users', 'user.routes', ['auth']);
```

## Middleware in Routing

Middleware is loaded from `middleware/*.php` at boot (via `Route::loadMiddlewares()`), and referenced by the filename without the `.php` extension (for example, `middleware/auth.php` is middleware name `auth`).

You can attach middleware in two places:

- On a single route: pass an array of middleware names as the last argument, e.g. `['authRedirect']`.
- On a group: pass middleware as the 3rd argument to `Route::group(...)`; all routes registered inside the group inherit the group middleware.

Middleware runs in this order: group middleware first, then the route’s own middleware.

Middleware parameters (optional): if you use `name:param1,param2`, the middleware receives an array of parameters as its `$params` argument (Router splits on `:` and `,`), very useful for **role-based access** routing.

## Request / Response

Controller methods can type-hint `Request` and/or `Response` and they’ll be passed automatically by the router.

- **Closures / callable routes** can do the same:

```php
Route::get('/api/dashboard', function(Request $req, Response $res) {
    $data = $req->body;
    $res->status(200)->send($data);
}, ['authRedirect']);

Route::get('/api/ping', function(Response $res) {
    $res->status(200)->send('Success');
}, ['authRedirect']);
```

- **`Request`** provides:
  - `body`: JSON-decoded body (if valid JSON) or raw input string
  - `params`: query string parameters (`$_GET`)
  - `formData`: form body (`$_POST`)
  - `files`: uploaded files (`$_FILES`)

- **`Response`** provides:
  - `status(int $code)`
  - `json(array $data)` (ends request)
  - `send(string $text, string $contentType = 'text/plain')` (ends request)
  - `file(string $file, array $data = [])` (includes a PHP file and ends request)
  - `pass(array $data)` (stores variables for the next `file()` call)

- **Passing variables to dynamic PHP views**

If your view is a PHP file (example: `views/dashboard-no-js.php`), you can pass variables into it:

```php
$res->status(200)->pass(['user' => $user])->file('views/dashboard-no-js.php');
// or:
$res->status(200)->file('views/dashboard-no-js.php', ['user' => $user]);
```

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

1. Fork the repo
2. Create a branch:

```bash
git checkout -b feat/your-feature
```

3. Make changes and test locally (run `php -S localhost:8000`)
4. Open a pull request describing:
   - what you changed
   - why you changed it
   - how to test

## Security notes

- JWT uses **HS256** and requires a strong `JWT_SECRET`.
- Do not store sensitive data in JWT payloads.
- Use HTTPS in production when sending tokens via headers/cookies.

## Credits

- RedBeanPHP is included under `libs/RedbeanPHP/` with license
