<?php

namespace OSVSuche\Providers;

use OSVSuche\Widgets\SuchergebnisseWidget;
use Plenty\Modules\ShopBuilder\Contracts\ContentWidgetRepositoryContract;
use Plenty\Plugin\ServiceProvider;

class OSVSucheServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->getApplication()->register(OSVSucheRouteServiceProvider::class);
    }

    public function boot()
    {
        // ShopBuilder-Widget fuer die Suchergebnisseite
        /** @var ContentWidgetRepositoryContract $widgetRepository */
        $widgetRepository = pluginApp(ContentWidgetRepositoryContract::class);
        $widgetRepository->registerWidget(SuchergebnisseWidget::class);
    }
}
