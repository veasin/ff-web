<?php
include __DIR__ . "/../../vendor/autoload.php";

use function ff\{middleware, test, container};
use function ff\web\middleware\error;

test('error: 正常执行返回结果', function() {
    return middleware(error(), 'ok');
}, 'ok');

test('error: 默认异常 message=空', function() {
    container('#out.op', null);
    middleware(error(), function() {
        throw new \RuntimeException('custom msg');
    });
    $meta = (container('#out.op')[1] ?? []);
    container('#out.op', null);
    return $meta['code'] === 500 && ($meta['message'] ?? '') === '';
}, true);

test('error: int 配置 message=空', function() {
    container('#out.op', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \InvalidArgumentException('参数错误');
    });
    $meta = (container('#out.op')[1] ?? []);
    container('#out.op', null);
    return $meta['code'] === 400 && ($meta['message'] ?? '') === '';
}, true);

test('error: int + container 后备', function() {
    container('#web/error/400', '错误的请求');
    container('#out.op', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \InvalidArgumentException('参数错误');
    });
    $meta = (container('#out.op')[1] ?? []);
    container('#out.op', null);
    return $meta['code'] === 400 && ($meta['message'] ?? '') === '错误的请求';
}, true);

test('error: {message} 替换为 $e->getMessage()', function() {
    container('#out.op', null);
    middleware(error([\InvalidArgumentException::class => [400, '{message}']]), function() {
        throw new \InvalidArgumentException('参数值不合法');
    });
    $meta = (container('#out.op')[1] ?? []);
    container('#out.op', null);
    return $meta['code'] === 400 && ($meta['message'] ?? '') === '参数值不合法';
}, true);

test('error: i18n 上下文格式化', function() {
    container('i18n.#ff.error.with_line', '第 {line} 行出错');
    container('#out.op', null);
    middleware(error([\LengthException::class => [400, '#ff.error.with_line']]), function() {
        throw new \LengthException('too long', 0);
    });
    $meta = (container('#out.op')[1] ?? []);
    container('#out.op', null);
    return $meta['code'] === 400 && str_contains($meta['message'] ?? '', '行');
}, true);

test('error: 未匹配走默认 500', function() {
    container('#out.op', null);
    middleware(error([\InvalidArgumentException::class => 400]), function() {
        throw new \RuntimeException('unexpected');
    });
    $v = (container('#out.op')[1] ?? [])['code'] ?? 200;
    container('#out.op', null);
    return $v;
}, 500);

test('error: 多异常类型映射', function() {
    container('i18n.#ff.error.domain', '领域错误');
    container('#out.op', null);
    middleware(error([
        \InvalidArgumentException::class => 400,
        \DomainException::class => [422, '#ff.error.domain'],
    ]), function() {
        throw new \DomainException('domain error');
    });
    $meta = (container('#out.op')[1] ?? []);
    container('#out.op', null);
    return $meta['code'] === 422 && ($meta['message'] ?? '') === '领域错误';
}, true);

test() |> json_encode(?, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) |> printf(...);
