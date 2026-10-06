<?php

namespace OSVSuche\Widgets;

use Ceres\Widgets\Helper\BaseWidget;
use Ceres\Widgets\Helper\Factories\WidgetDataFactory;
use Ceres\Widgets\Helper\Factories\WidgetSettingsFactory;
use Ceres\Widgets\Helper\WidgetTypes;

/**
 * Ergebnisraster der eigenen Suche fuer die Artikelsuchergebnis-Seite.
 * Das Raster wird im Browser aus dem Suchindex gefuellt. Solange das klappt, blendet das Skript
 * Plentys Artikel-Raster, Toolbar, Filter und Seitenblaettern aus; sonst bleibt Plentys Raster stehen.
 */
class SuchergebnisseWidget extends BaseWidget
{
    protected $template = 'OSVSuche::Widgets.Suchergebnisse';

    public function getData()
    {
        return WidgetDataFactory::make('OSVSuche::SuchergebnisseWidget')
            ->withLabel('OSV Suchergebnisse')
            ->withPreviewImageUrl('/images/widgets/item-grid.svg')
            ->withType(WidgetTypes::CATEGORY_ITEM)
            ->withCategory(WidgetTypes::CATEGORY_ITEM)
            ->withPosition(100)
            ->toArray();
    }

    public function getSettings()
    {
        /** @var WidgetSettingsFactory $settings */
        $settings = pluginApp(WidgetSettingsFactory::class);
        $settings->createCustomClass();
        $settings->createSpacing();
        return $settings->toArray();
    }
}
