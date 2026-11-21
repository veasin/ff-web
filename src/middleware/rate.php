<?php
namespace ff\web\middleware;

use function ff\{container, from, i18n, output};
use function ff\cache\apcu;

function rate(int $maxRequests = 60, int $windowSeconds = 60, string $key = 'rate'): callable{
	return function($next) use ($maxRequests, $windowSeconds, $key){
		$ip = $_SERVER['REMOTE_ADDR'] ?? from('remote_addr', 'input') ?? 'unknown';
		$route = from('uri', 'input') ?? '';
		$cacheKey = "$key:$ip:$route";
		$storage = container('#web/rate/storage');
		$timestamps = $storage ? $storage($cacheKey) : null;
		if($timestamps === null) $timestamps = apcu($cacheKey) ?? [];
		$now = time();
		$timestamps = array_filter($timestamps, fn($t) => $t > $now - $windowSeconds);
		if(count($timestamps) >= $maxRequests) return output(null, ['code' => 429, 'message' => i18n('#error:rate_limit')]);
		$timestamps[] = $now;
		if($storage) $storage($cacheKey, $timestamps, $windowSeconds);
		else apcu($cacheKey, $timestamps, $windowSeconds);
		return $next();
	};
}
