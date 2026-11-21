<?php
// json.php 测试
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{middleware, test, container, hook};
use function ff\web\middleware\json;

test('json: null返回null', function() {
    return middleware(json(), null);
}, null);

test('json: 数组直接返回', function() {
    return middleware(json(), ['key' => 'value']);
}, ['key' => 'value']);

test('json: JSON字符串转换为数组', function() {
    return middleware(json(), '{"key":"value"}');
}, ['key' => 'value']);

test('json: 设置type为json', function() {
    middleware(json(), ['test' => 123]);
    return (container('#out.op')[1] ?? [])['type'] ?? null;
}, 'json');
container('#out.render', fn() => null);
hook('after', static fn() => container('#out.op', null), 500);
test() |> json_encode(?, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) |> printf(...);
