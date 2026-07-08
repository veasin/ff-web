<?php
namespace ff\middleware\web;
use function ff\{from, container, output};

function gzip(int $level = 6): callable{
	return function($next) use ($level){
		$result = $next();
		if(!function_exists('gzencode')) return $result;
		if($result === null || is_array($result) || from('method', 'input') === 'OPTIONS') return $result;
		$accept = from('accept-encoding', 'header') ?? '';
		if(!str_contains($accept, 'gzip')) return $result;
		$body = is_string($result) ? $result : json_encode($result);
		$compressed = gzencode($body, $level);
		if(strlen($body) <= strlen($compressed)) return $result;
		output(null, ['code' => 200, 'headers' => ['Content-Encoding' => 'gzip']]);
		return $compressed;
	};
}
