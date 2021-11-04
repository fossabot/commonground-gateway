<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

class NLXService
{
    private ParameterBagInterface $parameterBag;
    private Security $security;

    public function __construct(ParameterBagInterface $parameterBag, Security $security)
    {
        $this->parameterBag = $parameterBag;
        $this->security = $security;
    }

    /**
     * Adds NLX transaction log headers to the header set and determines their values
     *
     * @param string $url The URL that is requested
     * @param array $query The query that is passed to the server
     * @param array $headers The headers passed to the server
     * @return array The headers with added the NLX Headers
     */
    public function createNLXHeaders(string $url, array $query, array $headers): array
    {
        $headers['X-NLX-Request-User-Id'] = $this->security->getUser()->getUsername(); //@TODO: Edit users to 5.3 systems and use UserIdentifier instead of username
        $headers['X-NLX-Request-Application-Id'] = $this->parameterBag->has('app_id') ? $this->parameterBag->get('app_id') : $this->parameterBag->get('app_url');
        $headers['X-NLX-Request-Subject-Identifier'] = $url;
        $headers['X-NLX-Request-Process-Id'] = $this->parameterBag->get('app_url'); //@TODO: find a way to determine what process, form or whatever describing resource is responsible for the request
        $headers['X-NLX-Request-Data-Subject'] = "resource=$url"; //@TODO: find related subjects

        if(isset($query['fields'])){
            $headers['X-NLX-Request-Data-Elements'] = $query['fields'];
        } else {
            $headers['X-NLX-Request-Data-Elements'] = '*';
        }
/*
 * @TODO: Figure out NLX Authorization

        if(isset($this->parameterBag->get('components')['nlx']['auth'])){
            $headers['Proxy-Authorization'] = $this->parameterBag->get('components')['nlx']['auth'];
            $headers['X-NLX-Request-User'] = $this->security->getUser()->getUsername(); //@TODO: Edit users to 5.3 systems and use UserIdentifier instead of username
            $headers['X-NLX-Request-Claims'] = $this->getClaims(); //@TODO: Edit users to 5.3 systems and use UserIdentifier instead of username
        }
*/

        return $headers;
    }

    public function createNLXUrl()

}
