<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{container, test};
use function ff\web\user\jwt;

$secret = 'test-secret-key';
$ttl = 3600;
$storage = jwt($secret, $ttl);

test('jwt: write returns 3-part token', function() use ($storage) {
    $t = $storage['write'](['id' => 1, 'name' => 'admin']);
    return count(explode('.', $t));
}, 3);

test('jwt: read decodes valid token', function() use ($storage, $secret) {
    $t = $storage['write'](['id' => 2, 'name' => 'user2']);
    $payload = $storage['read']($t);
    return $payload['user']['name'];
}, 'user2');

test('jwt: read returns null for tampered token', function() use ($storage) {
    $t = $storage['write'](['id' => 3]);
    return $storage['read']($t . 'x');
}, null);

test('jwt: read returns null for malformed token', function() use ($storage) {
    return $storage['read']('bad.token');
}, null);

test('jwt: read returns null for wrong secret', function() use ($storage, $secret, $ttl) {
    $wrong = jwt('wrong-secret', $ttl);
    $t = $wrong['write'](['id' => 4]);
    return $storage['read']($t);
}, null);

test('jwt: check returns true for valid token', function() use ($storage) {
    $t = $storage['write'](['id' => 5]);
    return $storage['check']($t);
}, true);

test('jwt: check returns false for tampered token', function() use ($storage) {
    $t = $storage['write'](['id' => 6]);
    return $storage['check']($t . 'x');
}, false);

test('jwt: check returns false for malformed token', function() use ($storage) {
    return $storage['check']('bad');
}, false);

test('jwt: check returns false for expired token', function() use ($storage, $secret) {
    $expired = jwt($secret, -1);
    $t = $expired['write'](['id' => 7]);
    return $storage['check']($t);
}, false);

test('jwt: token reads from Authorization header', function() use ($storage) {
    container('#in.headers', ['authorization' => 'Bearer jwt-token-123']);
    $result = $storage['token']();
    container('#in.headers', null);
    return $result;
}, 'jwt-token-123');

test('jwt: token returns null without header', function() use ($storage) {
    container('#in.headers', null);
    return $storage['token']();
}, null);

test('jwt: destroy is no-op', function() use ($storage) {
    $storage['destroy']('any');
}, fn() => true);

test();
