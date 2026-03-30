<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; margin: 0; min-height: 100vh; background: #f0f2f5; }
        .header { background: #fff; padding: 1rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.25rem; color: #1a1a1a; }
        .header a { color: #1877f2; text-decoration: none; font-size: 0.9rem; }
        .header a:hover { text-decoration: underline; }
        .main { padding: 2rem; max-width: 640px; margin: 0 auto; }
        .card { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card h2 { margin: 0 0 0.5rem; font-size: 1.1rem; color: #333; }
        .card p { margin: 0; color: #666; font-size: 0.95rem; }
        .loading { color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard</h1>
        <a href="/login" id="logout">Log out</a>
    </div>
    <div class="main">
        <div class="card">
            <div id="content">
                <h2>Welcome, <?= htmlspecialchars($user['username']); ?></h2>
                <p>You are logged in.</p>
                <a href="/dashboard">View dashboard with JavaScript</a>
            </div>
        </div>
    </div>
</body>
</html>
