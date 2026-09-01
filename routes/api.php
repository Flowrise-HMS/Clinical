<?php

/*
|--------------------------------------------------------------------------
| Clinical API Routes
|--------------------------------------------------------------------------
|
| This file previously registered `apiResource('clinicals', ClinicalController::class)`
| against the untouched nwidart scaffold controller: `index()` and `show()` returned
| Blade views from JSON endpoints, and `store()`, `update()` and `destroy()` had empty
| bodies that answered 200 without writing anything. The group also omitted the
| `api.branch` middleware, so nothing set the branch context.
|
| Clinical resources are being reintroduced through ApiRouteRegistrar, which applies
| `auth:sanctum` + `api.branch` and gates the whole surface on the Api module.
|
*/
