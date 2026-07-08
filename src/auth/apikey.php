<?php
namespace ff\middleware\web\auth;

use function ff\{container, from, output, i18n};

function apikey(string $prefix = '#web/auth', string $headerName = 'X-API-Key', string $queryName = 'api_key'): callable{
	$headerName = strtolower(str_replace('_', '-', $headerName));
	return function($next) use ($prefix, $headerName, $queryName){
		if(container("$prefix/user")) return $next();
		$apiKey = from($headerName, 'header') ?? from($queryName, 'query');
		if(!$apiKey) return output(null, ['code' => 401, 'headers' => ['X-API-Key' => i18n('#ff.auth.apikey_required')]]);
		foreach(container("$prefix/validators") ?? [] as $validator){
			$result = $validator($apiKey);
			if($result){
				container("$prefix/user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
