<?php

namespace OSVSuche\Containers;

use Plenty\Plugin\Templates\Twig;

class SucheKopf
{
    public function call(Twig $twig): string
    {
        return $twig->render('OSVSuche::Containers.SucheKopf');
    }
}
