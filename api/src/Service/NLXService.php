<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class NLXService
{
    private ParameterBagInterface $parameterBag;
    private SessionInterface $session;

    public function __construct(ParameterBagInterface $parameterBag, SessionInterface $session)
    {
        $this->session = $session;
        $this->parameterBag = $parameterBag;
    }


}
