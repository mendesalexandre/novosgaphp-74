<?php

namespace Novosga\Twig;

/**
 * Resources Twig filter.
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class Extensions extends \Twig\Extension\AbstractExtension
{
    public function getName()
    {
        return 'novosga';
    }

    public function getFunctions()
    {
        return array(
            new ResourcesFunction(),
        );
    }

    public function getFilters()
    {
        return array(
            new SecFormat(),
        );
    }
}
