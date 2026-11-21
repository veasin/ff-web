<?php
declare(strict_types=1);
namespace ff\web\user;
/**
 * Session 凭证 storage。依赖 PHP 原生 session，零外部依赖。
 * ```
 * container('#user.default', user\session());
 * ```
 * @return array
 */
function session(): array{
	return [
		'token' => fn(): ?string => session_id() ?: null,
		'read' => fn(string $token): ?array => $_SESSION['#user'] ?? null,
		'check' => fn(string $token): bool => isset($_SESSION['#user']),
		'write' => function(object|array $user): string{
			$_SESSION['#user'] = $user;
			return session_id();
		},
		'destroy' => function(string $token): void{ $_SESSION['#user'] = null; },
	];
}
