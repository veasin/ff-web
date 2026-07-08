<?php
declare(strict_types=1);
namespace ff\web;

use function ff\container;

/**
 * 统一凭证生命周期管理器。记录/恢复/检测/销毁用户凭证。
 * ```
 * user($user);                                    // 记录用户到默认 storage，返回 token
 * user($user, 'jwt');                             // 记录用户到 jwt storage，返回 token
 * user(null, 'session');                          // 从 session storage 恢复用户
 * user(true);                                     // 检测默认 storage 凭证是否有效
 * user(false);                                    // 登出，销毁默认 storage 中的凭证
 * user(true, 'jwt');                              // 检测 jwt storage 凭证是否有效
 * user(false, 'jwt');                             // 登出，销毁 jwt storage 中的凭证
 * user();                                         // 从默认 storage 取当前用户
 * ```
 * @param null|bool|object|array $user    用户数据/true(检测)/false(登出)/null(读取)
 * @param ?string                $storage storage 配置名，默认 'default'
 * @return null|string|bool|object|array
 */
function user(null|bool|object|array $user = null, ?string $storage = null): null|string|bool|object|array{
	$name = $storage ?? 'default';
	$store = container("#user.$name");
	if(!$store) throw new \RuntimeException("User storage '$name' not configured");
	if(is_array($user) || is_object($user)) return ($store['write'] ?? null)?->__invoke($user);
	$token = ($store['token'] ?? null)?->__invoke();
	if(null === $token) return $user === true ? false : null;
	if(true === $user){
		if(isset($store['check'])) return $store['check']($token);
		return ($store['read'] ?? null)?->__invoke($token) !== null;
	}
	if(false === $user){
		($store['destroy'] ?? null)?->__invoke($token);
		return null;
	}
	return ($store['read'] ?? null)?->__invoke($token);
}
