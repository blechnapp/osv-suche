<?php

namespace OSVSuche\Containers;

use Plenty\Plugin\Templates\Twig;

class SucheScript
{
    public function call(Twig $twig): string
    {
        return $twig->render('OSVSuche::Containers.SucheScript');
    }
}
