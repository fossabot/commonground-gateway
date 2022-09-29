<?php

namespace App\Service;

use App\Entity\Gateway as Source ;
use GuzzleHttp\Client;

class CallService
{

    public function __construct(
    ) {
    }


    /*
     * Calls a pre-configures commonground service
     *
     * $source array Eather an endpoint in the form of an url or and component as array to wisch to post
     * $requestOptions string the content of the call
     * $async boolean determens whether to peform the api cal asynchronus
     *
     * return Gu A guzzle responce object
     */
    public function callService(
        Source $source,
        ?array $requestOptions= [],
        ?bool $async = false
    ) {
        /* Let overwrite request options, avalaibalne request options are
         *
         * 'body'        => $content,
            'method'      => $type,
            'url'         => $url,
            'query'       => $query,
            'headers'     => $headers,
            'http_errors' => true,
         */
        $requestOptions = array_merge($source->getRequestOptions(), $requestOptions);

        // Lets set authentication
        $requestOptions = array_merge($requestOptions, $source->getAuthorization($source));

        // Lets make sure the start, limit and page are always integer @rli why?
        if (array_key_exists('query',$requestOptions)) {
            if (array_key_exists('start',$requestOptions['query'])) {
                $requestOptions['query']['start'] = (int) $requestOptions['query']['start'];
            }
            if (array_key_exists('limit',$requestOptions['query'])) {
                $requestOptions['query']['limit'] = (int)  $requestOptions['query']['limit'];
            }
            if (array_key_exists('page',$requestOptions['query'])) {
                $requestOptions['query']['page'] = (int)  $requestOptions['query']['page'];
            }
        }

        // Content mee sturen
        if (!$async) {
            try {
                $response = $this->client->request($requestOptions['method'], $requestOptions['url'], $requestOptions);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return ['error' => $e->getResponse()->getBody()->getContents()];
            }
        } else {
            try {
                $response = $this->client->requestAsync($requestOptions['method'], $requestOptions['url'], $requestOptions);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return ['error' => $e->getResponse()->getBody()->getContents()];
            }
        }

        return $response;
    }

    public function getAuthorization(Source $source, ?array $requestOptions = []): array
    {
        switch ($source->getAuth()) {
            case 'jwt-HS256':
            case 'jwt-RS512':
            case 'jwt':
                $requestOptions['headers']['Authorization'] = 'Bearer '.$this->getJwtToken($component);
                break;
            case 'username-password':
                $requestOptions['auth'] = [$source->getUsername(), $source->getPassword()];
                break;
            case 'vrijbrp-jwt':
                $requestOptions['headers']['Authorization'] = "Bearer {$this->getTokenFromUrl($component)}";
                break;
            case 'hmac':
                $requestOptions['headers']['Authorization'] = $this->getHmacToken($requestOptions, $component);
                break;
            case 'apikey':
                if (array_key_exists('authorizationHeader', $component) && array_key_exists('passthroughMethod', $component)) {
                    switch ($component['passthroughMethod']) {
                        case 'query':
                            $requestOptions['query'][$component['authorizationHeader']] = $component['apikey'];
                            break;
                        default:
                            $requestOptions['headers'][$component['authorizationHeader']] = $component['apikey'];
                            break;
                    }
                } else {
                    $requestOptions['headers']['Authorization'] = $component['apikey'];
                }
                break;
            default:
                break;
        }

        return $requestOptions;
    }

}
