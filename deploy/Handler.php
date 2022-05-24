<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

    	$this->renderable(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e) {
			return response()->view('error.link-invalid', [], 403);
		});

		$this->renderable(function (Throwable $e) {
			if ($e->getPrevious() instanceof \Illuminate\Session\TokenMismatchException) {
				app('redirect')->setIntendedUrl(url()->previous());
				return redirect()->route('login')
					->withInput(request()->except('_token'))
					->withErrors('Security token has expired. Please sign-in again.');
			}
		});
    }
}
