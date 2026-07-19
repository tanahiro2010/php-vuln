<?php
/**
 * lib/auth.php -- shared session / access-control helpers.
 *
 * Same access rules in both apps (public /login /register, login-required
 * internals, admin-only /admin/report). The point of the training is that
 * these rules are NOT the security boundary: an attacker who bypasses
 * login via SQLi in app_vulnerable.php ends up with a real admin session
 * that satisfies these checks legitimately.
 */

function start_training_session(string $cookie_name): void
{
    // Distinct cookie names so the vulnerable and secure apps don't share
    // a session when both are open on 127.0.0.1 (browsers scope cookies
    // by host, not port).
    session_name($cookie_name);
    session_start();
}

function flash(string $category, string $message): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['category' => $category, 'message' => $message];
}

function drain_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function current_user(): ?string {
    return $_SESSION['user'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        flash('warn', 'ログインが必要です。');
        header('Location: ' . app_url('/login'));
        exit;
    }
}

function require_admin(): void
{
    if (empty($_SESSION['user'])) {
        flash('warn', 'ログインが必要です。');
        header('Location: ' . app_url('/login'));
        exit;
    }
    if (($_SESSION['role'] ?? '') !== 'admin') {
        flash('warn', 'この画面は管理者専用です。');
        header('Location: ' . app_url('/dashboard'));
        exit;
    }
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}
