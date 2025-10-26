<?php

namespace DagaSmart\LoginGuard\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use DagaSmart\BizAdmin\Admin;
use DagaSmart\BizAdmin\Traits\ErrorTrait;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use DagaSmart\LoginGuard\LoginGuardServiceProvider;

class LoginMiddleware
{
    use ErrorTrait;

    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return \Illuminate\Http\JsonResponse|mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->is(config('admin.route.prefix') . '/login')) {
            $this->check($request->input('username'));

            if ($this->hasError()) {
                return Admin::response()->fail($this->getError());
            }
        }

        $response = $next($request);

        if ($request->is(config('admin.route.prefix') . '/login') && $request->has(['username', 'password'])) {
            if ($response instanceof \Illuminate\Http\JsonResponse && $response->getData()->msg == __('admin.login_failed')) {
                $this->record($request->input('username'), $response->getData()->status == 0);
            }
        }

        return $response;
    }

    /**
     * @param $username
     * @param bool $forget
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function record($username, bool $forget = false)
    {
        if ($forget) {
            app('cache')->forget($this->getCacheKey($username));
            return;
        }

        $record = app('cache')->get($this->getCacheKey($username));

        $value = [
            'tryCount'    => 1,
            'lastTryTime' => time(),
        ];

        if ($record) {
            $value['tryCount'] = Arr::get($record, 'tryCount', 0) + 1;
        }

        app('cache')->put($this->getCacheKey($username), $value);
    }

    /**
     * @param $username
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function check($username)
    {
        $record = app('cache')->get($this->getCacheKey($username));

        if ($record) {
            $maxTryCount = $this->config('max_try_count', 10);
            $lockTime    = $this->config('lock_time', 5);
            $tryCount    = Arr::get($record, 'tryCount', 0);

            if ($tryCount >= $maxTryCount) {
                $lastTryTime = Arr::get($record, 'lastTryTime', 0);
                $releaseTime = Carbon::createFromTimestamp($lastTryTime)->addMinutes($lockTime);

                if ($releaseTime->gt(now())) {
                    $this->setError($this->trans('login.error_message', ['time' => $releaseTime->diffForHumans()]));
                }
            }
        }
    }

    private function getCacheKey($username)
    {
        return LoginGuardServiceProvider::loginRestrictionCacheKey($username);
    }

    private function config($key, $default = null)
    {
        return LoginGuardServiceProvider::setting($key, $default);
    }

    private function trans($key, $replace = [])
    {
        return LoginGuardServiceProvider::trans($key, $replace);
    }
}
