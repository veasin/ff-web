<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{test};
use function ff\web\user\session;

session_start();
$_SESSION['#user'] = null;
$storage = session();

test('session: write sets SESSION and returns session_id', function() use ($storage) {
    $token = $storage['write'](['id' => 1, 'name' => 'admin']);
    return $token === session_id() && $_SESSION['#user']['name'] === 'admin';
}, true);

test('session: read returns user from SESSION', function() use ($storage) {
    return $storage['read']('any')['name'];
}, 'admin');

test('session: check returns true when user exists', function() use ($storage) {
    return $storage['check']('any');
}, true);

test('session: destroy clears SESSION', function() use ($storage) {
    $storage['destroy']('any');
    return $_SESSION['#user'];
}, null);

test('session: check returns false after destroy', function() use ($storage) {
    return $storage['check']('any');
}, false);

test('session: token returns session_id', function() use ($storage) {
    return $storage['token']();
}, session_id());

test('session: read returns null when no user', function() use ($storage) {
    return $storage['read']('any');
}, null);

test() |> json_encode(?, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) |> printf(...);
