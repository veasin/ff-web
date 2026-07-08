<?php
namespace ff\middleware\web;

use function ff\{container, from, output, i18n};

function csrf(bool $verify = false): callable{
	return function($next) use ($verify){
		$token = from('_token', 'body') ?? from('X-CSRF-Token', 'header');
		$sessionToken = container('#web/csrf/token');
		if($verify && ($token !== $sessionToken)) return output(null, ['code' => 419, 'message' => i18n('#error:csrf_mismatch')]);
		$newToken = $sessionToken ?? bin2hex(random_bytes(32));
		container('#web/csrf/token', $newToken);
		$result = $next();
		if(is_array($result)) $result['_token'] = $newToken;
		elseif(is_object($result)) $result->token = $newToken;
		return $result;
	};
}
