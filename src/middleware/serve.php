<?php
namespace ff\web\middleware;

use function ff\{container, from, output};

function serve(string $root, null|false|int|string|array $cache = null): callable{
	return function($next) use ($root, $cache){
		$uri = parse_url(from('uri', 'input') ?? '/', PHP_URL_PATH);
		$ext = pathinfo($uri, PATHINFO_EXTENSION);
		$file = $root . $uri;
		if(is_dir($file)) $file .= '/index.html';
		if(!file_exists($file) || !is_file($file)) return $next();
		static $types = [
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
		];
		$contentType = container("#web/static/mimes.$ext") ?? $types[$ext] ?? (mime_content_type($file) || 'application/octet-stream');
		$cfg = $cache ?? container('#web/static/cache');
		[$control, $age] = match (true) {
			false === $cfg => [false, 0],
			is_int($cfg) => [null, $cfg],
			is_string($cfg) => [$cfg, 0],
			is_array($cfg) => [$cfg['control'] ?? '', $cfg['age'] ?? 0],
			default => ['', 0],
		};
		$headers = ['Content-Type' => $contentType];
		if(false === $control) $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
		else{
			if($control){
				$mtime = filemtime($file);
				if(str_contains($control, 'etag')){
					$tag = '"' . $mtime . '-' . filesize($file) . '"';
					if(from('if-none-match', 'header') === $tag) return output(null, ['code' => 304, 'type' => 'http']);
					$headers['ETag'] = $tag;
				}
				if(str_contains($control, 'modified')){
					$lm = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
					if(from('if-modified-since', 'header') === $lm) return output(null, ['code' => 304, 'type' => 'http']);
					$headers['Last-Modified'] = $lm;
				}
			}
			if($age) $headers['Cache-Control'] = "public, max-age=$age";
		}
		$content = file_get_contents($file);
		$headers['Content-Length'] = strlen($content);
		output($content, ['type' => 'http', 'headers' => $headers]);
		return $content;
	};
}
