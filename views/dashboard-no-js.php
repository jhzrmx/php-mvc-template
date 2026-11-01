<?php
// Rendered with: $res->view('dashboard-no-js', ['user' => $user])
$layout = 'layouts/app';
$title = 'Dashboard';
?>
<div class="card">
    <div id="content">
        <h2>Welcome, <?= e($user['username']) ?></h2>
        <p>You are logged in.</p>
        <a href="/dashboard">View dashboard with JavaScript</a>
    </div>
</div>
