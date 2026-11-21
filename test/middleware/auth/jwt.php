<?php
// jwt.php 测试
include __DIR__ . "/../../../vendor/autoload.php";

use function ff\{container, middleware, test};
use function ff\web\middleware\auth\jwt;

function createJwt(string $secret, array $payload, string $algo = 'HS256'): string{
	$header = ['typ' => 'JWT', 'alg' => $algo];
	$headerB64 = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
	$payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
	$sig = hash_hmac($algo === 'HS256' ? 'sha256' : 'sha512', "$headerB64.$payloadB64", $secret, true);
	$sigB64 = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
	return "$headerB64.$payloadB64.$sigB64";
}

test('jwt: 无 token 返回401',
	function(){
		container('#web/auth/secret', 'test-secret');
		container('#web/auth/validators', [fn($payload) => ($payload['sub'] ?? null) === 'user1' ? 'user1' : null]);
		container('#web/auth/user', null);
		container('#out.op', null);
		container('#in.header', []);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		middleware(jwt(), 'ok');
		return (container('#out.op')[1] ?? [])['code'] ?? 200;
	},
	401);

test('jwt: 认证成功返回结果',
	function(){
		$token = createJwt('test-secret', ['sub' => 'user1', 'exp' => time() + 3600]);
		container('#web/auth/secret', 'test-secret');
		container('#web/auth/validators', [fn($payload) => ($payload['sub'] ?? null) === 'user1' ? 'user1' : null]);
		container('#web/auth/user', null);
		container('#out.op', null);
		container('#in.header', ['authorization' => 'Bearer ' . $token]);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		return middleware(jwt(), 'ok');
	},
	'ok');

test('jwt: 签名错误返回403',
	function(){
		$token = createJwt('wrong-secret', ['sub' => 'user1', 'exp' => time() + 3600]);
		container('#web/auth/secret', 'test-secret');
		container('#web/auth/validators', [fn($payload) => ($payload['sub'] ?? null) === 'user1' ? 'user1' : null]);
		container('#web/auth/user', null);
		container('#out.op', null);
		container('#in.header', ['authorization' => 'Bearer ' . $token]);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
		middleware(jwt(), 'ok');
		return (container('#out.op')[1] ?? [])['code'] ?? 200;
	},
	403);
test() |> json_encode(?, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) |> printf(...);
