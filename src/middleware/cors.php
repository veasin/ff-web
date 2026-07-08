<?php
namespace ff\web\middleware;

use function ff\{from, output};

function cors(array $options = []): callable{
	$opts = [
		'origin' => '*',
		'methods' => 'GET,POST,PUT,DELETE,OPTIONS',
		'headers' => 'Content-Type,Authorization,X-CSRF-Token',
		'credentials' => false,
		'max-age' => 86400,
		...$options,
	];
	return function($next) use ($opts){
		$origin = is_array($opts['origin']) ? ($opts['origin'][array_rand($opts['origin'])] ?? '*') : $opts['origin'];
		output(null, [
			'code' => 200,
			'headers' => [
				'Access-Control-Allow-Origin' => $origin,
				'Access-Control-Allow-Methods' => $opts['methods'],
				'Access-Control-Allow-Headers' => $opts['headers'],
				'Access-Control-Allow-Credentials' => $opts['credentials'] ? 'true' : 'false',
				'Access-Control-Max-Age' => $opts['max-age'],
			],
		]);
		return from('method', 'input') === 'OPTIONS' ? ['ok' => true] : $next();
	};
}
