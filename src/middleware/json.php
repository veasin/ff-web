<?php
namespace ff\web\middleware;
use function ff\{from, output};

function json(bool $pretty = false): callable{
	return function($next) use ($pretty){
		$result = $next();
		if($result === null) return null;
		$result = is_array($result) ? $result : (json_decode($result, true) ?? $result);
		output($result, ['type' => 'json', 'pretty' => $pretty, 'headers' => ['Content-Type' => 'application/json; charset=UTF-8']]);
		return $result;
	};
}
