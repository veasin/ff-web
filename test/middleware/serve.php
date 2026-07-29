<?php
// serve.php 测试
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{middleware, test, container, from};
use function ff\web\middleware\serve;

$testDir = __DIR__ . '/_test_static';
@mkdir($testDir, 0755, true);
file_put_contents($testDir . '/test.txt', 'test content');
file_put_contents($testDir . '/test.js', 'var a = 1;');
file_put_contents($testDir . '/index.html', '<h1>Index</h1>');

container(null);

test('serve: 静态文件存在时返回内容', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir), 'not found');
	return container('#out.op')[0];
}, 'test content');

test('serve: 文件不存在时继续下一个', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/nonexistent.txt']);
	return middleware(serve($testDir), 'fallback');
}, 'fallback');

test('serve: 目录自动追加index.html', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/']);
	middleware(serve($testDir), 'not found');
	return container('#out.op')[0];
}, '<h1>Index</h1>');

test('serve: 设置正确的Content-Type', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir), 'not found');
	return (container('#out.op')[1] ?? [])['headers']['Content-Type'] ?? null;
}, fn($v) => str_starts_with($v, 'text/plain'));

test('serve: 默认无Cache-Control', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir), 'not found');
	$h = (container('#out.op')[1] ?? [])['headers'] ?? [];
	return isset($h['Cache-Control']);
}, false);

test('serve: false输出no-cache', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, false), 'not found');
	return (container('#out.op')[1] ?? [])['headers']['Cache-Control'] ?? null;
}, 'no-cache, no-store, must-revalidate');

test('serve: int作为max-age', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, 3600), 'not found');
	return (container('#out.op')[1] ?? [])['headers']['Cache-Control'] ?? null;
}, 'public, max-age=3600');

test('serve: ETag条件缓存', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, 'etag'), 'not found');
	return (container('#out.op')[1] ?? [])['headers']['ETag'] ?? null;
}, fn($v) => $v !== null && str_starts_with($v, '"'));

test('serve: ETag匹配返回304', function() use ($testDir) {
	$file = $testDir . '/test.txt';
	$mtime = filemtime($file);
	$expectedTag = '"' . $mtime . '-' . filesize($file) . '"';
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#in.headers', ['if-none-match' => $expectedTag]);
	middleware(serve($testDir, 'etag'), 'not found');
	return (container('#out.op')[1] ?? [])['code'] ?? 200;
}, 304);

test('serve: Last-Modified条件缓存', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	middleware(serve($testDir, 'modified'), 'not found');
	return (container('#out.op')[1] ?? [])['headers']['Last-Modified'] ?? null;
}, fn($v) => $v !== null);

test('serve: Modified匹配返回304', function() use ($testDir) {
	$file = $testDir . '/test.txt';
	$expectedLm = gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT';
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#in.headers', ['if-modified-since' => $expectedLm]);
	middleware(serve($testDir, 'modified'), 'not found');
	return (container('#out.op')[1] ?? [])['code'] ?? 200;
}, 304);

test('serve: 数组配置 control + age', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#in.headers', []);
	container('#out.op', null);
	middleware(serve($testDir, ['control' => 'etag,modified', 'age' => 86400]), 'not found');
	$h = (container('#out.op')[1] ?? [])['headers'] ?? [];
	return $h['Cache-Control'] === 'public, max-age=86400' && str_starts_with($h['ETag'] ?? '', '"') && $h['Last-Modified'] !== null;
}, true);

test('serve: 容器配置 #static:cache', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.txt']);
	container('#web/static/cache', 7200);
	middleware(serve($testDir), 'not found');
	container('#web/static/cache', null);
	return (container('#out.op')[1] ?? [])['headers']['Cache-Control'] ?? null;
}, 'public, max-age=7200');

test('serve: js文件Content-Type正确', function() use ($testDir) {
	container('#in.input', ['method' => 'GET', 'uri' => '/test.js']);
	middleware(serve($testDir), 'not found');
	return (container('#out.op')[1] ?? [])['headers']['Content-Type'] ?? null;
}, 'application/javascript');

test();

container('#out.render', fn() => null);

@unlink($testDir . '/test.txt');
@unlink($testDir . '/test.js');
@unlink($testDir . '/index.html');
@rmdir($testDir);
