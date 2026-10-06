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
            // Aktuelle Preise und Verfuegbarkeit fuer wenige Varianten (?ids=1,2,3), schlank
            $apiRouter->get('osv-suche/preise', 'IndexController@preise');
            // Verkaufszahlen je Variantennummer speichern (JSON im Body), nur mit Schluessel
            $apiRouter->post('osv-suche/verkauf', 'IndexController@verkauf');
        });
    }
}
