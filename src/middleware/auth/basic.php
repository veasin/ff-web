<?php
namespace ff\web\middleware\auth;

use function ff\{container, from, i18n, output};

function basic(string $prefix = '#web/auth', ?string $realm = null): callable{
	return function($next) use ($prefix, $realm){
		if(container("$prefix/user")) return $next();
		$header = from('authorization', 'header') ?? '';
		if(!str_starts_with($header, 'Basic ')) return output(null, ['code' => 401, 'headers' => ['WWW-Authenticate' => "Basic realm=\"" . ($realm ?? i18n('#ff.auth.realm_basic')) . "\""]]);
		$credentials = base64_decode(substr($header, 6));
		[$user, $pass] = array_pad(explode(':', $credentials, 2), 2, '');
		foreach(container("$prefix/validators") ?? [] as $validator){
			$result = $validator($user, $pass);
			if($result){
				container("$prefix/user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
