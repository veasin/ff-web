<?php
// bootstrap.php — ff-web 中间件自注册与默认配置
use function ff\container;

container([
	'^#ext.out.type.raw' => fn(mixed $d, array $m): array => [$d, $m],
	'^#web/cors' => [
		'origin' => '*',
		'methods' => 'GET,POST,PUT,DELETE,OPTIONS',
		'headers' => 'Content-Type,Authorization,X-CSRF-Token',
		'credentials' => false,
		'max-age' => 86400,
	],
	'^#web/static/mimes' => [
		'html' => 'text/html',
		'htm' => 'text/html',
		'txt' => 'text/plain',
		'css' => 'text/css',
		'js' => 'application/javascript',
		'json' => 'application/json',
		'png' => 'image/png',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'svg' => 'image/svg+xml',
		'ico' => 'image/x-icon',
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
		'ttf' => 'font/ttf',
		'zip' => 'application/zip',
		'xml' => 'application/xml',
	],
]);
