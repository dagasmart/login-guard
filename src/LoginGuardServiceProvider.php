<?php

namespace DagaSmart\LoginGuard;

use DagaSmart\BizAdmin\Renderers\TextControl;
use DagaSmart\BizAdmin\Extend\ServiceProvider;
use DagaSmart\BizAdmin\Renderers\NumberControl;
use DagaSmart\LoginGuard\Http\Middleware\LoginMiddleware;

class LoginGuardServiceProvider extends ServiceProvider
{
    protected $middleware = [
        LoginMiddleware::class,
    ];

    public function settingForm()
    {
        return $this->baseSettingForm()->data([
            'extension'     => $this->getCode(),
            'max_try_count' => 10,
            'lock_time'     => 5,
        ])->body([
            TextControl::make()
                ->name('max_try_count')
                ->label(static::trans('login.max_try_count'))
                ->required(true)
                ->description(static::trans('login.max_try_count_description')),
            NumberControl::make()
                ->name('lock_time')
                ->label(static::trans('login.lock_time'))
                ->required(true)
                ->suffix(static::trans('login.minute'))
                ->min(1)
                ->displayMode('enhance'),
        ]);
    }

    public static function loginRestrictionCacheKey($username)
    {
        return 'login-restriction-' . $username;
    }
}
