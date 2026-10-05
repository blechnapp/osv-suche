<?php

namespace OSVSuche\Providers;

use Plenty\Plugin\ServiceProvider;

class OSVSucheServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->getApplication()->register(OSVSucheRouteServiceProvider::class);
    }
}
