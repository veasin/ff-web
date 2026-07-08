<?php
namespace ff\web\middleware\auth;

use function ff\{container, from, output, i18n};

function token(string $prefix = '#web/auth', string $headerName = 'Authorization'): callable{
	$headerName = strtolower(str_replace('-', '_', $headerName));
	return function($next) use ($prefix, $headerName){
		if(container("$prefix/user")) return $next();
		$rawToken = from($headerName, 'header') ?? from('token', 'query');
		$token = str_starts_with($rawToken, 'Bearer ') ? substr($rawToken, 7) : $rawToken;
		if(!$token) return output(null, ['code' => 401, 'headers' => ['WWW-Authenticate' => 'Bearer realm="' . i18n('#ff.auth.realm_token') . '"']]);
		foreach(container("$prefix/validators") ?? [] as $validator){
			$result = $validator($token);
			if($result){
				container("$prefix/user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
