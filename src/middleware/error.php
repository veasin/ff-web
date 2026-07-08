<?php
namespace ff\web\middleware;

use function ff\{container, i18n, output};

function error(array $statusMap = []): callable{
	return function($next) use ($statusMap){
		try{
			return $next();
		}catch(\Throwable $e){
			$config = $statusMap[$e::class] ?? [500, null, null];
			if(!is_array($config)) $config = [$config, null, null];
			[$code, $message, $lang] = $config + [500, null, null];
			return output(null, [
				'code' => (int)$code,
				'message' => i18n($message ?? container("#web/error/$code") ?? '', [
					'status' => $code,
					'code' => $e->getCode(),
					'message' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				], $lang),
			]);
		}
	};
}
