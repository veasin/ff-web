<?php
// basic.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{container, middleware, test};
use function ff\web\middleware\auth\basic;

test('auth_basic: 未认证返回401',
	function(){
		container('#web/auth/validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456' ? 'admin' : null]);
		container('#web/auth/user', null);
		container('#out.op', null);
		container('#in.header', []);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = '';
		middleware(basic(), 'ok');
		return (container('#out.op')[1] ?? [])['code'] ?? 200;
	},
	401);

test('auth_basic: 认证成功返回结果',
	function(){
		container('#web/auth/validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456' ? 'admin' : null]);
		container('#web/auth/user', null);
		container('#out.op', null);
		container('#in.header', ['authorization' => 'Basic ' . base64_encode('admin:123456')]);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:123456');
		return middleware(basic(), 'ok');
	},
	'ok');

test('auth_basic: 密码错误返回403',
	function(){
		container('#web/auth/validators', [fn($user, $pass) => $user === 'admin' && $pass === '123456' ? 'admin' : null]);
		container('#web/auth/user', null);
		container('#out.op', null);
		container('#in.header', ['authorization' => 'Basic ' . base64_encode('admin:wrong')]);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:wrong');
		middleware(basic(), 'ok');
		return (container('#out.op')[1] ?? [])['code'] ?? 200;
	},
	403);

test('auth_basic: 密码含冒号正确解析',
	function(){
		container('#web/auth/validators', [fn($user, $pass) => $user === 'admin' && $pass === 'pass:word:123' ? 'admin' : null]);
		container('#web/auth/user', null);
		container('#out.op', null);
		container('#in.header', ['authorization' => 'Basic YWRtaW46cGFzczp3b3JkOjEyMw==']);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic YWRtaW46cGFzczp3b3JkOjEyMw==';
		return middleware(basic(), 'ok');
	},
	'ok');
test() |> json_encode(?, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) |> printf(...);
