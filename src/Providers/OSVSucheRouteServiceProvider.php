<?php

namespace OSVSuche\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\Routing\Router;

class OSVSucheRouteServiceProvider extends RouteServiceProvider
{
    public function map(Router $router, ApiRouter $apiRouter)
    {
        $apiRouter->version(['v1'], ['namespace' => 'OSVSuche\Controllers'], function ($apiRouter) {
            // Oeffentlich lesbar, liefert nur, was der Shop ohnehin anzeigt
            $apiRouter->get('osv-suche/index', 'IndexController@index');
        });
    }
}
