<?php
declare(strict_types=1);
namespace ff\web\user;

use function ff\{from, safe};
/**
 * JWT 凭证 storage。纯 PHP 实现 HS256，零外部依赖。
 * ```
 * container('#user.default', user\jwt('my-secret-key'));
 * container('#user.default', user\jwt('my-secret-key', ttl: 3600, algo: 'HS256'));
 * ```
 * @param string  $secret 签名密钥
 * @param int     $ttl    token 过期秒数
 * @param string  $algo   算法，当前仅支持 'HS256'
 * @return array
 */
function jwt(string $secret, int $ttl = 86400, string $algo = 'HS256'): array
{
	return [
		'token'   => function(): ?string{
			$v = from('authorization', 'header');
			return is_string($v) ? preg_replace('/^Bearer\s+/i', '', $v) : null;
		},
		'read'    => function(string $token) use ($secret): ?array{
			$parts = explode('.', $token);
			if (count($parts) !== 3) return null;
			[$h, $p, $s] = $parts;
			if (hash_hmac('sha256', "{$h}.{$p}", $secret, true)
				!== safe(fn() => base64_decode(strtr($s, '-_', '+/'))))
				return null;
			$payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
			if (!$payload || ($payload['exp'] ?? 0) < time()) return null;
			return $payload;
		},
		'write'   => function(object|array $user) use ($secret, $ttl): string{
			$header = rtrim(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '=');
			$payload = rtrim(base64_encode(json_encode(['user' => $user, 'exp' => time() + $ttl, 'iat' => time()])), '=');
			$signature = rtrim(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true)), '=');
			return "{$header}.{$payload}.{$signature}";
		},
		'destroy' => function(string $token): void{},
		'check'   => function(string $token) use ($secret): bool{
			$parts = explode('.', $token);
			if (count($parts) !== 3) return false;
			[$h, $p, $s] = $parts;
			$sig = safe(fn() => base64_decode(strtr($s, '-_', '+/')));
			if (!$sig || !hash_equals(hash_hmac('sha256', "{$h}.{$p}", $secret, true), $sig)) return false;
			$payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
			return $payload && ($payload['exp'] ?? 0) >= time();
		},
	];
}
