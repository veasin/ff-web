<?php
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, test};
use function ff\web\{user};

// 共享状态 — 模拟单次请求中的多次 user() 调用
$u    = null;
$tok  = 'mock-token';
$userDefault = [
    'token'   => function() use (&$tok) { return $tok; },
    'read'    => function(string $token) use (&$u) { return $u; },
    'check'   => function(string $token) use (&$u) { return $u !== null; },
    'write'   => function(object|array $user) use (&$u, &$tok) {
        $u   = $user;
        $tok = 'written-token';
        return $tok;
    },
    'destroy' => function(string $token) use (&$u) { $u = null; },
];
container('#user.default', $userDefault);

// ───── Empty storage config (null handling) ─────
container('#user.null', []);
test('user: throws on empty storage config (write)', fn() => user(['x' => 1], 'null'), \RuntimeException::class);
test('user: throws on empty storage config (check)', fn() => user(true, 'null'), \RuntimeException::class);
test('user: throws on empty storage config (read)', fn() => user(null, 'null'), \RuntimeException::class);
test('user: throws on empty storage config (destroy)', fn() => user(false, 'null'), \RuntimeException::class);

// ───── 一次请求内：write → read → check → destroy → check ─────
test('user: read returns null before write', fn() => user(), null);
test('user: write returns token', fn() => user(['id' => 1, 'name' => 'test']), 'written-token');
test('user: read after write', fn() => user()['name'], 'test');
test('user: check returns true when user exists', fn() => user(true), true);
test('user: destroy returns null', fn() => user(false), null);
test('user: check returns false after destroy', fn() => user(true), false);
test('user: read returns null after destroy', fn() => user(), null);

// ───── Named storage — 独立变量，互不干扰 ─────
$u2   = ['role' => 'admin'];
$tok2 = 'second-token';
container('#user.second', [
    'token'   => fn() => $tok2,
    'read'    => fn(string $t) => $t === 'second-token' ? $u2 : null,
    'check'   => fn(string $t) => $t === 'second-token',
    'write'   => fn(object|array $user) => 'second-written',
    'destroy' => function(string $t): void {},
]);

test('user: named write', fn() => user(['x' => 1], 'second'), 'second-written');
test('user: named read', fn() => user(null, 'second')['role'], 'admin');
test('user: named check', fn() => user(true, 'second'), true);
test('user: named destroy', fn() => user(false, 'second'), null);

// ───── 异常：不存在的 storage 名 ─────
test('user: throws on unknown storage', fn() => user(['x' => 1], '__unknown__'), \RuntimeException::class);
test('user: check throws on unknown storage', fn() => user(true, '__unknown__'), \RuntimeException::class);
test('user: read throws on unknown storage', fn() => user(null, '__unknown__'), \RuntimeException::class);
test('user: destroy throws on unknown storage', fn() => user(false, '__unknown__'), \RuntimeException::class);

test();
