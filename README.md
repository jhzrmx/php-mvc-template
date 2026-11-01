# PHP MVC Template (with RedBean)

A lightweight **PHP MVC starter** with a simple router, middleware support, JWT authentication, and [RedBeanPHP](https://github.com/gabordemooij/redbean) for database access. Designed to be easy to understand, easy to extend, and friendly for those who started with Laravel.

## Features

- **Model-View-Controller structure**: `routes/`, `controllers/`, `models/`, `views/`
- **Router with middleware + groups**: `GET/POST/PUT/PATCH/DELETE`, route params with optional constraints (`/:id(\d+)`), named routes, `405 Method Not Allowed`, JSON 404s for the API, and route groups
- **Dependency injection (lightweight)**: controllers, closures and middleware can receive `Request` and `Response`; handlers can just `return` an array to send JSON
- **Error handling**: `HttpException` / `abort()`, one central `ErrorHandler` (JSON or HTML error pages, debug mode, logging), and views that can't leave a half-rendered page
- **Validator**, **Logger**, **view layouts** with an `e()` escape helper, and `Model::paginate()`
- **JWT auth** (HS256): login/register endpoints + protected routes via middleware
- **DotEnv loader**: `.env` support with quoting, escapes and `${VAR}` expansion; values stay strings, read them with `env('KEY', $default)`
- **RedBeanPHP** ORM: simple model base class and example `User` model
- **Migrations**: `php mvc migrate` creates and updates tables, so it works with a frozen schema in production
- **CLI generators**: `php mvc make:controller|model|middleware|migration`, `php mvc routes`
- **Tests**: PHPUnit suite (`composer test`) and a GitHub Actions workflow
- **Friendly defaults**: ready-to-run pages (`/login`, `/signup`, `/dashboard`) and API routes under `/api/*`

## Requirements

- **PHP 7.0+** (also runs on PHP 8.x). The code avoids anything newer than 7.0: no typed properties, nullable/`void` types, `fn()` arrow functions, `str_contains()` and so on, so keep that in mind when you add code.
  - On PHP 7.0 - 7.2 the auth cookie is sent as a raw `Set-Cookie` header (the `setcookie()` options array and `SameSite` need PHP 7.3); behaviour is the same.
  - Before PHP 7.2, `Response::json()` answers `500` for a payload containing invalid UTF-8 (7.2+ substitutes the bad bytes instead).
  - Each file in `libs/` starts with a header that states the PHP version it needs and whether it is standalone or depends on other files.
  - The **test suite** needs **PHP 7.3+** (PHPUnit 9.6). Running the app on PHP 7.0 - 7.2 is fine, but running the tests there requires modifying them, see [Testing](#testing).
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
- Running the database migrations (creates the tables)

#### Option B: Manual Setup

1. **Create your environment file**

```bash
cp .env.example .env
```

2. **Adjust the environment variables**

Edit `.env` and configure:

```env
APP_ENV=local
JWT_SECRET=<run: php mvc key:generate>
JWT_EXPIRES_IN=3600

DB_DSN=mysql:host=127.0.0.1;dbname=myapp
DB_USER=root
DB_PASS=secret
```

Notes:

- `JWT_SECRET` is **required**, must be **at least 32 characters**, and the old example value is rejected (enforced in `libs/JWT.php`). Generate one with `php mvc key:generate` (`php mvc init` does this for you).
- `APP_ENV=production` freezes the RedBean schema and hides error details.
- `DB_*` is used by RedBeanPHP via `R::setup(...)` in `bootstrap.php`.

3. **Set up database** (if needed)

For SQLite (creates the database file and runs the migrations):

```bash
php mvc setup:sqlite
```

Or with custom SQLite database name:

```bash
php mvc setup:sqlite --dbname myapp.db
```

For MySQL or any other database, create the database, set `DB_DSN`, `DB_USER` and `DB_PASS` in `.env`, then create the tables:

```bash
php mvc migrate
```

Run `php mvc migrate` again after pulling updates; it only runs migrations that haven't run yet. Migrations live in `database/migrations/` and each file returns a function.

If your `user` table already has duplicate usernames, the unique-username migration will stop with an error: rename or remove the duplicates and run `php mvc migrate` again.

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
php -S localhost:8000 -t public
```

Always pass `-t public`: the built-in server serves every file under its document root, so without it `.env` and your database would be downloadable.

Then open:

- `http://localhost:8000/login`
- `http://localhost:8000/signup`
- `http://localhost:8000/dashboard`

Note: Use the built-in PHP server during development only. **DO NOT USE IT FOR PRODUCTION!**

### Option B: Apache (recommended for production)

Point the web server's **document root at the `public/` directory**. Only `public/index.php` is web-accessible; `.env`, `data/`, `storage/`, `libs/` and the rest of the app live outside the web root.

Apache: enable `mod_rewrite` and `AllowOverride All` so `public/.htaccess` is respected. If your host won't let you change the document root, the root `.htaccess` forwards everything into `public/` as a fallback.

nginx:

```nginx
root /var/www/app/public;
index index.php;

location / {
    try_files $uri /index.php?$query_string;
}

location ~ \.php$ {
    # only the front controller may be executed
    try_files /index.php =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

## Project structure

```text
.
├─ controllers/          # Controllers (e.g., AuthController, UserController)
├─ models/               # Models (RedBean-backed)
├─ routes/               # Route definitions (web + API groups)
├─ middleware/           # Route middleware (auth, authRedirect, role)
├─ views/                # Static HTML pages or PHP views (layouts/ for layouts, errors/ for error pages)
├─ libs/                 # Router, JWT, DotEnv, Auth, Validator, Logger, RateLimiter, Database, RedBeanPHP
├─ database/migrations/  # Schema migrations (php mvc migrate)
├─ public/               # Web root: index.php (front controller) + .htaccess
├─ data/                 # SQLite database (outside the web root)
├─ storage/              # Runtime files: logs/, ratelimit/ (outside the web root)
├─ tests/                # PHPUnit tests (composer test)
├─ bootstrap.php         # App bootstrap
├─ mvc                   # CLI Runner
├─ composer.json         # Dev tooling only (PHPUnit); the app itself has no dependencies
├─ .env.example          # Environment template
├─ .htaccess             # Fallback: forwards to public/ if the docroot can't be changed
└─ serve.cmd             # Convenience runner (Windows)
```

## How routing works

Routes are registered in `routes/web.php` and (via groups) in `routes/*.routes.php`.

- **Loading routes (bootstrap)**

Routes are loaded during app bootstrap from `bootstrap.php`:

```php
Route::init();
Route::loadMiddlewares();
Route::loadRoutes();             // registers routes/web.php + routes/api.php (configurable)
Route::add404('views/404.html'); // runs the matching route; if none matches: 404 (or 405)
```

`Route::get()` & co. only **register** routes. The matching route runs when `Route::dispatch()` is called, which `add404()` and `setSPA()` do for you. Models and controllers are autoloaded by class name from `models/` and `controllers/`.

- **CORS**

Off by default. Set `CORS_ALLOWED_ORIGINS` in `.env` (comma-separated, or `*`), and `CORS_ALLOW_CREDENTIALS=true` if browsers must send cookies (needs explicit origins, not `*`). Preflight `OPTIONS` requests are answered with the right headers. Or configure it in code, before `Route::init()`:

```php
Route::cors(['https://app.example.com'], null, null, true);
```

- **Static file/page routes**

```php
Route::get('/login', 'views/login.html');
```

`.php` files are executed; other files (html, css, js, json, txt) are sent as-is.

- **Controller routes**

```php
Route::post('/login', 'AuthController@login');
// Or use the new format:
Route::post('/login', [AuthController::class, 'login']);
```

- **Route parameters and constraints**

```php
Route::get('/api/users/:id', 'UserController@show');
Route::get('/api/users/:id(\d+)', [UserController::class, 'show']);  // digits only, otherwise 404
Route::get('/tags/:slug', [TagController::class, 'show'])->where('slug', '[a-z-]+');
```

Parameters are passed to the handler in order and URL-decoded. Constraint regexes can't contain `/`.

- **Named routes**

```php
Route::get('/users/:id(\d+)', [UserController::class, 'show'])->name('users.show');

route('users.show', ['id' => 5]);                 // "/users/5"
route('users.show', ['id' => 5], ['tab' => 'x']); // "/users/5?tab=x"
```

Names must be unique. `->middleware('name')` adds middleware to a single route.

- **Returning a response**

Handlers can still call `$res->json(...)`, or simply return a value: an array/object is sent as JSON (status 200) and a string as HTML. Returning nothing does nothing, so existing handlers are unaffected.

```php
Route::get('/api/ping', function () {
    return ['pong' => true];
});
```

- **405, HEAD and 404**

A path that exists under a different method answers `405` with an `Allow` header. `HEAD` is served by the matching `GET` route. Unmatched URLs answer `404`. Both are `HttpException`s rendered by the [error handler](#error-handling): JSON for `/api/*` and JSON clients, otherwise the page you pass to `add404()` (or `views/errors/404.php`, or a built-in page).

- **Route groups**

```php
Route::group('/api/users', 'user.routes', ['auth']);
Route::group('/admin', function () {
    Route::get('/stats', [StatsController::class, 'index']);
}, ['auth', 'role:admin']);
```

- **Resource routes**

```php
Route::apiResource('posts', PostController::class, ['auth']); // index, store, show, update (PUT+PATCH), destroy
Route::resource('posts', PostController::class);              // adds create/edit pages
```

- **See every route**: `php mvc routes`

## Middleware in Routing

Middleware is loaded from `middleware/*.php` at boot (via `Route::loadMiddlewares()`), and referenced by the filename without the `.php` extension (for example, `middleware/auth.php` is middleware name `auth`).

You can attach middleware in two places:

- On a single route: pass an array of middleware names as the last argument, e.g. `['authRedirect']`.
- On a group: pass middleware as the 3rd argument to `Route::group(...)`; all routes registered inside the group inherit the group middleware.

Middleware runs in this order: group middleware first, then the route’s own middleware.

Built-in middleware: `auth` (API, 401 JSON), `authRedirect` (pages, redirects to `/login`) and `role:<name>[,<name>]` (403 unless the user has one of the roles; run it after `auth`).

Middleware parameters (optional): if you use `name:param1,param2`, the middleware receives an array of parameters as its `$params` argument (Router splits on `:` and `,`), very useful for **role-based access** routing.

A middleware may also type-hint `Request` and/or `Response` (injected, like controllers); its remaining parameter is the arguments array. Send a response (`$res->error(403, 'Forbidden')`) or return an array/string to stop the request:

```php
// middleware/admin.php
return function (Request $req, Response $res, array $params = []) {
    if (!User::isAdmin(Auth::currentUser())) {
        $res->error(403, 'Forbidden', 'Admins only');
    }
};
```

Generate one with `php mvc make:middleware admin`.

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
  - `body`: JSON-decoded body (if valid JSON) or raw input string; `json($key = null, $default = null)` always gives an array
  - `params` (`$_GET`), `formData` (`$_POST`), `files` (`$_FILES`)
  - `input($key, $default)`, `has($key)`, `only([...])`, `all()`: query + form + JSON merged (JSON wins)
  - `method()`, `path()`, `ip()`, `header($name)`, `bearerToken()`, `isJson()`, `wantsJson()`
  - `validate($rules, $messages = [], $data = null)`: throws a `ValidationException` on failure, see [Validation](#validation)

- **`Response`** provides:
  - `status(int $code)` and `header($name, $value)` (both chainable)
  - `json($data)`, `error($status, $error, $message = '')`, `noContent()`, `send($text, $contentType)`, `redirect($url)` (all end the request)
  - `view($name, $data = [], $layout = null)` sends a view; `render(...)` returns it as a string; see [Views and layouts](#views-and-layouts)
  - `file(string $file, array $data = [])` (includes a PHP file, buffered, and ends request) and `pass(array $data)` (variables for the next `file()`/`view()`)

- **Passing variables to dynamic PHP views**

If your view is a PHP file (example: `views/dashboard-no-js.php`), you can pass variables into it:

```php
$res->status(200)->pass(['user' => $user])->file('views/dashboard-no-js.php');
// or:
$res->status(200)->file('views/dashboard-no-js.php', ['user' => $user]);
```

## Error handling

Every failure ends in one clean response, whether it's thrown in a controller, middleware, model, **or inside a PHP view**.

```php
abort(404);                                  // 404 Not Found
abort(403, 'Admins only');                   // message is shown to the client
abort(429, 'Slow down', ['Retry-After' => 30]);
abort_if($post === null, 404, 'Post not found');
abort_unless(Auth::check(), 401);

throw new HttpException(409, 'Email already taken');   // same thing
```

`HttpException` messages are written for the client and are always shown. Anything else (a database error, a bug) is a `500` that shows only *"An unexpected error occurred"* in production and is logged with its stack trace.

**What the central `ErrorHandler` does** (registered in `bootstrap.php`):

- Answers **JSON** (`{"error": "Not Found", "message": "..."}`) for `/api/*`, `Accept: application/json`, JSON request bodies and AJAX; otherwise an **HTML error page**: `views/errors/<status>.php`, then `views/errors/error.php`, then a built-in page. Copy `views/errors/error.php` to `views/errors/404.php` (or `500.php`, ...) to customise one status. If your error page itself fails, the built-in page is used.
- **Throws away partial output first.** Output buffers are discarded before the error response is sent, so a view that fails halfway (an exception, a PHP warning like an undefined variable, an `abort()`, even a fatal error) never leaves a broken page behind. This also covers layouts and views that forget to close an output buffer.
- Turns **PHP warnings and notices into exceptions** (`ErrorException`), so they can't silently corrupt a page. Deprecations are only logged at `debug` level. Set `ERRORS_AS_EXCEPTIONS=false` to turn this off. `@`-silenced errors are ignored.
- Catches **uncaught exceptions and fatal errors** (including out-of-memory and parse errors in included files).
- **Logs** server errors (5xx) with `Log::exception()`; client errors (4xx) only at `debug` level.
- **Never prints raw PHP errors** to visitors (`display_errors` is turned off).

**Debug mode** shows the exception class, file, line and stack trace (paths are relative to the project) instead of the generic message, for server errors only. `APP_DEBUG=true|false` decides; if it isn't set, debug is on only for `APP_ENV=local|development`. With no `APP_ENV` at all (for example a missing `.env`), details are shown only to requests from the same machine. **Never set `APP_DEBUG=true` in production.**

Handling errors yourself is still fine:

```php
try {
    $user = User::create($name, $password);
} catch (InvalidArgumentException $e) {      // domain/validation errors
    $res->error(400, 'Sign up failed', $e->getMessage());
}
```

Exceptions you don't catch go to the error handler. Don't catch `Throwable` just to format a 500; let it through so it's logged and rendered consistently.

## Validation

```php
$data = $req->validate([
    'username' => 'required|string|alpha_dash|min:3|max:32',
    'email'    => 'nullable|email',
    'age'      => 'integer|between:18,120',
    'role'     => 'in:user,admin',
    'password' => ['required', 'min:8', 'confirmed'],
], [
    'username.required' => 'Please choose a username',   // optional custom messages
], $req->json());                                          // what to validate (default: all())
```

On failure it throws a `ValidationException` (HTTP 422), which the error handler answers with `{"error": "Validation failed", "message": "<first error>", "errors": {"field": ["..."]}}` (or an HTML error page that lists the errors). On success you get the validated fields back. To handle failures yourself, catch it: `catch (ValidationException $e) { $e->errors(); }`.

Rules: `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `array`, `email`, `url`, `date`, `alpha`, `alpha_num`, `alpha_dash`, `min:n`, `max:n`, `max_bytes:n`, `between:a,b`, `in:a,b`, `not_in:a,b`, `regex:/pattern/`, `same:field`, `confirmed`. Empty optional fields skip their other rules; only the first failing rule per field is reported. `min`/`max`/`between` measure the number for `integer`/`numeric` fields, the characters for strings and the items for arrays. `max_bytes` counts bytes instead (the auth endpoints use it for passwords, because bcrypt ignores everything past 72 bytes).

You can also use it directly: `$v = Validator::make($data, $rules); $v->fails(); $v->errors(); $v->validated();`

## Views and layouts

```php
// routes
$res->view('dashboard-no-js', ['user' => Auth::user()]);   // views/dashboard-no-js.php
```

```php
<?php // views/dashboard-no-js.php
$layout = 'layouts/app';   // optional: wrap this page in views/layouts/app.php
$title = 'Dashboard';
?>
<h2>Welcome, <?= e($user['username']) ?></h2>
```

The layout receives the rendered page as `$content` (already HTML, so echo it raw: `<?= $content ?>`) and `$title`. **Always escape user-controlled values with `e()`.** View names may contain letters, digits, `_`, `-` and `/`.

A page is always rendered completely in memory before anything is sent, so a view that fails can't leave half a page in the browser (see [Error handling](#error-handling)). Inside a view you can call `abort(404)`; `$res->render('name', $data)` returns the HTML as a string (e.g. for emails).

## Logging

```php
Log::info('User signed up', ['id' => 5]);
Log::warning('Slow query');
Log::error('Something broke');
Log::exception($e);
```

Entries go to `storage/logs/app-YYYY-MM-DD.log`, one line each (newlines in messages are stripped so input can't forge log lines). Set `LOG_LEVEL` in `.env` (`debug`, `info`, `warning`, `error`); the default is `info`, or `warning` when `APP_ENV=production`.

## Authentication (JWT)

JWT is initialized at boot and enforced via middleware:

- **API protection**: `middleware/auth.php` returns `401` JSON on failure
- Tokens carry the **user ID** (`sub`) and a **token version** (`tv`). On every request the user is loaded from the database, so deleted users and revoked tokens are rejected immediately.
- **Logout revokes all of that user's tokens** (every device). Changing a password does the same.
- **Roles**: users are `user` by default. Promote one with `php mvc user:promote <username>`. The role is read from the database, never from the token.
- **Rate limiting**: login allows 5 failed attempts per IP+username and 20 per IP per 15 minutes; registration allows 10 per IP per hour. Exceeding either returns `429` with `Retry-After`.
- Existing tokens from older versions of this template (username as `sub`) are rejected, so users must log in again.
- **Page protection**: `middleware/authRedirect.php` redirects to `/login` on failure

### Token sources supported by middleware

- `Authorization: Bearer <token>`
- `X-Auth-Token: <token>`
- Cookie `token=<token>`

### Auth endpoints

Base path: `/api/auth`

- `POST /api/auth/register` → create user, sets the auth cookie
- `POST /api/auth/login` → verify credentials, sets the auth cookie

Browsers use the HttpOnly cookie. API clients that can't use cookies send the header `X-Return-Token: true` to get `"token"` in the JSON response instead (no cookie is set), then send it as `Authorization: Bearer <token>`. The body token is readable by JavaScript, so don't use that header from a browser app.
- `GET /api/auth/me` (**protected**) → return current user

Example login:

```bash
curl -X POST http://localhost:8000/api/auth/login ^
  -H "Content-Type: application/json" ^
  -H "X-Return-Token: true" ^
  -d "{\"username\":\"demo\",\"password\":\"secret123\"}"
```

Use the returned `token`:

```bash
curl http://localhost:8000/api/auth/me ^
  -H "Authorization: Bearer <token>"
```

## Users API

Base path: `/api/users` (protected by `auth` middleware via `routes/api.php`)

| Endpoint | Who |
| --- | --- |
| `GET /api/users` | admin |
| `POST /api/users` | admin |
| `GET /api/users/:id` | the user themself, or admin |
| `PUT` / `PATCH /api/users/:id` | the user themself, or admin |
| `DELETE /api/users/:id` | the user themself, or admin |

`GET /api/users` is paginated: `?page=1&per_page=20` (max 100) returns `{"data": [...], "meta": {"page", "per_page", "total", "last_page"}}`.

Updates accept `username`, `email` and `password` only; `role` cannot be changed over the API. Changing your own password requires `current_password`.

## Database (RedBeanPHP)

This template uses RedBeanPHP (`libs/RedbeanPHP/`) with a small `Model` base class in `libs/RedbeanPHP/Model.php`.

- Example model: `models/User.php`
  - hashes passwords with `password_hash()`
  - omits `password_hash` and `token_version` from API output (`$hidden`)
- Base class helpers (arrays, safe for JSON, `$hidden` fields removed): `all()`, `find($id)`, `where($sql, $bindings, $orderBy)`, `count($sql, $bindings)`, `paginate($page, $perPage, $sql, $bindings, $orderBy)`. `findBean($id)` returns the RedBean bean for code that needs to modify and store it.

```php
Post::paginate(2, 20, 'author_id = ?', [5], 'created_at DESC');
// ['data' => [...], 'meta' => ['page' => 2, 'per_page' => 20, 'total' => 53, 'last_page' => 3]]
```

Bean types (table names) must be lower-case letters and digits. Put user input in `?` placeholders (`$bindings`), never inside `$sql`.

## CLI

```bash
php mvc serve                         # dev server (serves only public/)
php mvc migrate                       # run pending migrations
php mvc routes                        # list routes
php mvc make:controller Post --api    # controllers/PostController.php with REST methods
php mvc make:model Post               # models/Post.php
php mvc make:middleware admin         # middleware/admin.php
php mvc make:migration create_post_table
php mvc key:generate                  # new JWT_SECRET
php mvc user:promote alice            # make a user an admin
```

## Testing

```bash
composer install     # installs PHPUnit 9.6 (dev only)
composer test
```

The suite covers the validator, JWT, DotEnv parser, router matching, request helpers, rate limiter, logger, the error handler (including views that throw, warn or `abort()` halfway) and the `User` model (against in-memory SQLite, so `pdo_sqlite` is needed).

The tests use **PHPUnit 9.6**, which supports **PHP 7.3 and all PHP 8.x versions**. The same command runs on GitHub Actions for PHP 7.3 to 8.5.

### Running the tests on PHP 7.0 - 7.2

The application itself still runs on PHP 7.0+, but PHPUnit 9.6 does not install on PHP 7.2 or lower, so the test files have to be modified to use an older PHPUnit (6.5 is the newest one that supports PHP 7.0):

1. In `composer.json`, change `"phpunit/phpunit"` to `"^6.5"` (and delete `composer.lock` if you have one), then run `composer update`.
2. In every file in `tests/`, drop the `: void` return type from `setUp()` and `tearDown()` (`protected function setUp() {`). PHP 7.0 doesn't have `void`.
3. Replace `assertStringContainsString()` / `assertStringNotContainsString()` with `assertContains()` / `assertNotContains()`, and `assertIsArray($x)` with `assertInternalType('array', $x)`.
4. Remove the `xsi:noNamespaceSchemaLocation` attribute from `phpunit.xml` (optional; PHPUnit 6.5 ignores it).

## Upgrading from an earlier version of this template

- Run `php mvc migrate` (adds `updated_at` to `user`).
- `Route::get()` and friends now only register; the route runs at `Route::add404()` / `Route::setSPA()` / `Route::dispatch()`. If you declare routes after `loadRoutes()` that's still fine, as long as you call `add404()` last.
- `Route::loadModels()` is optional now (autoloading); `bootstrap.php` no longer calls it.
- `User::all()` / `GET /api/users` now return a list (`[...]`) inside `data`, not an object keyed by id, and the users endpoint is paginated.
- Validation failures on auth and user endpoints now answer `422` with an `errors` map (they used to answer `400`).
- Errors are rendered by `ErrorHandler`: unexpected failures in controllers are no longer caught one by one, `Request::validate()` throws `ValidationException` instead of responding, and 404/405 are `HttpException`s. Copy the new `.env.example` lines (`APP_DEBUG`, optionally `ERRORS_AS_EXCEPTIONS`) and set `APP_DEBUG=false` (or leave it unset) in production.
- PHP warnings and notices are now exceptions. If third-party code of yours depends on a warning being ignored, silence that call with `@` or set `ERRORS_AS_EXCEPTIONS=false`.

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

3. Make changes and test locally (run `php mvc serve`)
4. Open a pull request describing:
   - what you changed
   - why you changed it
   - how to test

## Ideas / Roadmap

Good places to start contributing:

- Tests for `Auth`, `AuthController`, `UserController` and the middleware
- Split `libs/Router.php` into one file per class (`Request`, `Response`, `Route`)
- Transactional migrations and a `migrate:rollback` command
- Optional CSRF / `Origin` check for cookie-authenticated requests
- A `Content-Security-Policy` header (needs the inline scripts in `views/` moved out)

## Security notes

- JWT uses **HS256**, requires a strong `JWT_SECRET`, and requires an `exp` claim.
- Do not store sensitive data in JWT payloads.
- Use HTTPS in production when sending tokens via headers/cookies.
- Serve only `public/`. Never point the web server at the project root.
- Keep `APP_DEBUG` off in production: debug pages reveal file paths and stack traces.
- If you deploy behind a reverse proxy, make your web server restore the real client IP (e.g. `mod_remoteip`, nginx `real_ip`); rate limiting uses `REMOTE_ADDR`.
- With `APP_ENV=production` the schema is frozen: add the `role` (text) and `token_version` (integer, default 0) columns to an existing `user` table yourself.

## Credits

- RedBeanPHP is included under `libs/RedbeanPHP/` with license
