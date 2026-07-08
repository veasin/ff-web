<?php
declare(strict_types=1);
namespace ff\web\user;

use function ff\from;
/**
 * Token 凭证 storage。通过 $provider 回调接入 redis/apcu/db 等存储后端。
 * ```
 * // Redis
 * container('#user.default', user\token(
 *     fn(string $op, string $key, ...$a) => match($op){
 *         'get' => redis($key),
 *         'set' => redis($key, $a[0], $a[1] ?? 86400),
 *         'del' => redis($key, null),
 *     },
 * ));
 *
 * // 多级缓存
 * container('#user.default', user\token(
 *     fn(string $op, string $key, ...$a) => match($op){
 *         'get' => cache(apcu($key, middleware: 60), fn($n) => redis($key)),
 *         'set' => redis($key, $a[0], $a[1] ?? 86400),
 *         'del' => do { redis($key, null); apcu($key, null); },
 *     },
 * ));
 * ```
 * @param callable $provider 回调签名:
 *     → 'get'  : fn(string $key): ?string — 读
 *     → 'set'  : fn(string $key, string $data, ?int $ttl): void — 写
 *     → 'del'  : fn(string $key): void — 删
 * @param int      $ttl     默认过期秒数
 * @param string   $prefix  token 存储 key 前缀
 * @return array
 */
function token(callable $provider, int $ttl = 86400, string $prefix = 'ff_ut:'): array
{
	return [
		'token'   => function(): ?string{
			$v = from('authorization', 'header');
			return is_string($v) ? preg_replace('/^Bearer\s+/i', '', $v) : null;
		},
		'read'    => function(string $token) use ($provider, $prefix): ?array{
			$v = $provider('get', "{$prefix}{$token}");
			return is_string($v) ? json_decode($v, true) : null;
		},
		'check'   => fn(string $token): bool
			=> $provider('get', "{$prefix}{$token}") !== null,
		'write'   => function(object|array $user) use ($provider, $prefix, $ttl): string{
			$token = bin2hex(random_bytes(32));
			$provider('set', "{$prefix}{$token}", json_encode($user), $ttl);
			return $token;
		},
		'destroy' => function(string $token) use ($provider, $prefix): void{ $provider('del', "{$prefix}{$token}"); },
	];
}
