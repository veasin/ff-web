<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\web\user\token;

$store = [];
$provider = function(string $op, string $key, ...$a) use (&$store) {
    return match($op) {
        'get' => $store[$key] ?? null,
        'set' => $store[$key] = $a[0],
        'del' => $store[$key] = null,
    };
};

$ttl = 3600;
$prefix = 'ff_ut:';
$storage = token($provider, $ttl, $prefix);

test('token: write returns 64-char hex token', function() use ($storage) {
    $t = $storage['write'](['id' => 1, 'name' => 'admin']);
    return preg_match('/^[a-f0-9]{64}$/', $t) ? 1 : 0;
}, 1);

test('token: write stores json in provider', function() use ($storage, &$store, $prefix) {
    $t = $storage['write'](['id' => 2, 'name' => 'user2']);
    return json_decode($store["{$prefix}{$t}"], true)['name'];
}, 'user2');

test('token: read returns decoded user', function() use ($storage, &$store, $prefix) {
    $t = $storage['write'](['id' => 3, 'name' => 'user3']);
    return $storage['read']($t)['name'];
}, 'user3');

test('token: read returns null for unknown token', function() use ($storage) {
    return $storage['read']('nonexistent');
}, null);

test('token: check returns true for valid token', function() use ($storage) {
    $t = $storage['write'](['id' => 4]);
    return $storage['check']($t);
}, true);

test('token: check returns false for invalid token', function() use ($storage) {
    return $storage['check']('bad');
}, false);

test('token: destroy removes data from provider', function() use ($storage, &$store, $prefix) {
    $t = $storage['write'](['id' => 5]);
    $storage['destroy']($t);
    return $store["{$prefix}{$t}"];
}, null);

test('token: token reads from Authorization header', function() use ($storage) {
    container('#in.headers', ['authorization' => 'Bearer test-token-123']);
    $result = $storage['token']();
    container('#in.headers', null);
    return $result;
}, 'test-token-123');

test('token: token returns null without header', function() use ($storage) {
    container('#in.headers', null);
    return $storage['token']();
}, null);

test() |> json_encode(?, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) |> printf(...);
