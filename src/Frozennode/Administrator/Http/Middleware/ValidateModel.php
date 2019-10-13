<?php namespace Frozennode\Administrator\Http\Middleware;

use Closure;

class ValidateModel
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $modelName = $request->route()->parameter('model');

        app()->singleton('itemconfig', function ($app) use ($modelName) {
            $configFactory = app('admin_config_factory');

            return $configFactory->make($modelName, true);
        });

        $this->resolveDynamicFormRequestErrors($request);

        return $next($request);
    }

    /**
     * POST method to capture any form request errors
     *
     * @param \Illuminate\Http\Request $request
     */
    protected function resolveDynamicFormRequestErrors($request)
    {
        try {
            $config = app('itemconfig');
            $fieldFactory = app('admin_field_factory');
        } catch (\ReflectionException $e) {
            return null;
        }
        if (array_key_exists('form_request', $config->getOptions())) {
            try {
                $model = $config->getFilledDataModel($request, $fieldFactory->getEditFields(), $request->id);

                $request->merge($model->toArray());
                $formRequestClass = $config->getOption('form_request');
                app($formRequestClass);
            } catch (HttpResponseException $e) {
                //Parses the exceptions thrown by Illuminate\Foundation\Http\FormRequest
                $errorMessages = $e->getResponse()->getContent();
                $errorsArray = json_decode($errorMessages);
                if (!$errorsArray && \is_string($errorMessages)) {
                    return $errorMessages;
                }
                if ($errorsArray) {
                    return implode(".", array_dot($errorsArray));
                }
            }
        }
        return null;
    }

}