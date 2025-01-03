<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class Acl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (preg_match('/(introspection)/i', $request->json()->get('query'))) {
            return $next($request);
        }

        $authUrl = sprintf('%s%s', $request->header('Referer'), '/api/me');
        $aclEndpoint = env('MS_ACL_URL');

        //request to get user in auth service
        // $user = Http::withToken($request->bearerToken())->post($authUrl);
        $user = $request->user;

        //until auth service is made
        // if (!isset($user->json()['user'])) {
        //     return Response::create(json_encode(['message' => 'Invalid token']), 200, [
        //         'Access-Control-Allow-Headers' => '*'
        //     ]);
        // }

        $query = explode(' ', $request->json()->get('query'));
        if (array_key_exists(1, $query)) {
            $actionUuid = array_search($query[1], config('permission.action'));
        }
        //dummy action uuid for test
        $actionUuid = '4749e1b0-0e1d-49d0-8e07-a02b129a3e0c';

        if (! $actionUuid) {
            return Response::create(json_encode(
                ['message' => 'Query not formatted properly. Query should start with "query "Query Name" or mutation "Mutation Name"']
            ), 200);
        }

        $permission = Http::post($aclEndpoint, [
            'user_uuid' => '4749e1b0-0e1d-49d0-8e07-a02b129a3e0c',
            'action_uuid' => $actionUuid,
        ])->json();

        if ($permission === null || ! $permission['access']) {
            return Response::create(\json_encode(['message' => 'User cannot carry out this action', 'permission' => $permission]), 403);
        }

        return $next($request);
    }
}
