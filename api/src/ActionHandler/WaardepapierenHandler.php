<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\WaardepapierenService;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Respect\Validation\Exceptions\ComponentException;

class WaardepapierenHandler implements ActionHandlerInterface
{
    private waardepapierenService $waardepapierenService;

    public function __construct(ContainerInterface $container)
    {
        $waardepapierenService = $container->get('waardepapierenService');
        if ($waardepapierenService instanceof WaardepapierenService) {
            $this->waardepapierenService = $waardepapierenService;
        } else {
            throw new GatewayException('The service container does not contain the required services for this handler');
        }
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://example.com/person.schema.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'Waardepapieren Action',
            'description' => 'This handler customly validates a certificate ',
            'required'    => ['waardepapierenEntityId'],
            'properties'  => [
                'zaakTypeEntityId' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the case entitEntity on the gateway',
                    'example'     => '',
                ],
            ],
        ];
    }

    /**
     * This function runs the service for validating cases.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->waardepapierenService->waardepapierenHandler($data, $configuration);
    }
}
