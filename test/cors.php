<?php
// cors.php 测试
include __DIR__ . "/../vendor/autoload.php";

use function ff\{container, middleware, test};
use function ff\middleware\web\cors;

test('cors: 设置默认跨域头',
	function(){
		middleware(cors(), 'ok');
		return container('#out.response.headers.Access-Control-Allow-Origin');
	},
	'*');
test('cors: 自定义 origin',
	function(){
		middleware(cors(['origin' => 'https://example.com']), 'ok');
		return container('#out.response.headers.Access-Control-Allow-Origin');
	},
	'https://example.com');
test('cors: OPTIONS 请求返回 ok',
	function(){
		return container('#mode:cli') ? ['ok' => true] : null;
	},
	fn($v) => $v === ['ok' => true]);
test();
