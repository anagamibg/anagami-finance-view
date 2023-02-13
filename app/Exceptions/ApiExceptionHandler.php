<?php

namespace App\Exceptions;

use Froiden\RestAPI\ApiResponse;
use Froiden\RestAPI\Exceptions\ApiException;
use Froiden\RestAPI\Exceptions\Parse\UnknownFieldException;
use Froiden\RestAPI\Exceptions\UnauthenticationException;
use Froiden\RestAPI\Exceptions\UnauthorizedException;
use Froiden\RestAPI\Exceptions\ValidationException;
use Froiden\RestAPI\Handlers\ApiExceptionHandler as FroidenApiExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Overrides Froiden's API handler so production (debug=false) does not
 * reference an undefined $response and swallow the real exception body.
 */
class ApiExceptionHandler extends FroidenApiExceptionHandler
{
    public function render($request, Throwable $e)
    {
        $debug = config('app.debug');
        $prefix = config('api.prefix');

        if ($request->is($prefix . '/*')) {
            if ($e instanceof AuthenticationException) {
                return ApiResponse::exception(new UnauthenticationException());
            }

            if ($e instanceof HttpResponseException || $e instanceof \Illuminate\Validation\ValidationException) {
                if ($e->status == 403) {
                    return ApiResponse::exception(new UnauthorizedException());
                }

                return ApiResponse::exception(new ValidationException($e->errors()));
            }

            if ($e instanceof NotFoundHttpException) {
                return ApiResponse::exception(new ApiException('This api endpoint does not exist', null, 404, 404, 2005, [
                    'url' => request()->url(),
                ]));
            }

            if ($e instanceof ModelNotFoundException) {
                return ApiResponse::exception(new ApiException('Requested resource not found', null, 404, 404, null, [
                    'url' => request()->url(),
                ]));
            }

            if ($e instanceof ApiException) {
                return ApiResponse::exception($e);
            }

            if ($e instanceof QueryException) {
                if ($e->getCode() == '422') {
                    preg_match("/Unknown column \\'([^']+)\\'/", $e->getMessage(), $result);

                    if (!isset($result[1])) {
                        return ApiResponse::exception(new UnknownFieldException(null, $e));
                    }

                    $parts = explode('.', $result[1]);
                    $field = count($parts) > 1 ? $parts[1] : $result;

                    return ApiResponse::exception(new UnknownFieldException("Field '" . $field . "' does not exist", $e));
                }
            }

            $message = null;
            $response = [];

            if ($debug) {
                $response['trace'] = $e->getTrace();
                $response['code'] = $e->getCode();
                $message = $e->getMessage();
            }

            return ApiResponse::exception(new ApiException($message, null, 500, 500, null, $response));
        }

        return parent::render($request, $e);
    }
}
