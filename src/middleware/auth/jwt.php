<?php
namespace ff\web\middleware\auth;

use function ff\{container, from, i18n, output};

function jwt(string $prefix = '#web/auth', string $algo = 'HS256'): callable{
	return function($next) use ($prefix, $algo){
		if(container("$prefix/user")) return $next();
		$header = from('authorization', 'header') ?? '';
		if(!str_starts_with($header, 'Bearer ')) return output(null, ['code' => 401, 'headers' => ['WWW-Authenticate' => 'Bearer realm="' . i18n('#ff.auth.realm_jwt') . '"']]);
		$token = substr($header, 7);
		$parts = explode('.', $token);
		if(count($parts) !== 3) return output(null, 401);
		[$headerB64, $payloadB64, $sigB64] = $parts;
		$payload = strtr($payloadB64, '-_', '+/')|> base64_decode(...)|> json_decode(?, true);
		$sig = base64_decode(strtr($sigB64, '-_', '+/'));
		$secret = container("$prefix/secret") ?? '';
		$expectedSig = hash_hmac($algo === 'HS256' ? 'sha256' : 'sha512', "$headerB64.$payloadB64", $secret, true);
		if(!hash_equals($expectedSig, $sig)) return output(null, 403);
		foreach(container("$prefix/validators") ?? [] as $validator){
			$result = $validator($payload);
			if($result){
				container("$prefix/user", $result);
				return $next();
			}
		}
		return output(null, 403);
	};
}
