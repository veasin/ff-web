<?php
namespace ff\web\middleware;
use function ff\{from, container, log as flog};

function log(string $level = 'info'): callable{
	$start = microtime(true);
	$memStart = memory_get_usage();
	return function($next) use ($level, &$start, &$memStart){
		$request = [
			'method' => from('method', 'input'),
			'uri' => from('uri', 'input'),
		];
		$result = $next();
		$duration = round((microtime(true) - $start) * 1000, 2);
		$memory = round((memory_get_usage() - $memStart) / 1024, 2);
		$status = container('#out.response:code') ?? 200;
		flog([
			'method' => $request['method'],
			'uri' => $request['uri'],
			'status' => $status,
			'duration_ms' => $duration,
			'memory_kb' => $memory,
		], $level);
		return $result;
	};
}
