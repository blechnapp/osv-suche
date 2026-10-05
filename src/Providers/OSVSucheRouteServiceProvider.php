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
            // Liefert den gespeicherten Index aus, baut nie selbst (schnell, oeffentlich)
            $apiRouter->get('osv-suche/index', 'IndexController@index');
            // Baut den Index neu und speichert ihn, nur mit Schluessel aus der Plugin-Konfiguration
            $apiRouter->get('osv-suche/rebuild', 'IndexController@rebuild');
        });
    }
}
