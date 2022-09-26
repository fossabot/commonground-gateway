<?php

namespace App\ActionHandler;

use App\Exception\GatewayException;
use App\Service\EmailService;
use Psr\Container\ContainerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class EndpointHandler implements ActionHandlerInterface
{
    private EndpointService $emailService;

    public function __construct(ContainerInterface $container)
    {
        $endpointService = $container->get('endpointService');
        if ($endpointService instanceof EndpointService) {
            $this->endpointService = $endpointService;
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
            '$id'        => 'https://example.com/person.schema.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'Notification Action',
            'required'   => ['ServiceDNS', 'template', 'sender', 'reciever', 'subject'],
            'properties' => [
                'types' => [
                    'type'        => 'array',
                    'description' => 'The request types suported by this endpoint',
                    'example'     => ['POST','GET','PUT','DELETE'],
                ],
                'mappingIn' => [
                    'type'        => 'array',
                    'description' => 'The mapping aplied to posts `before` they are handled',
                    'example'     => 'native://default',
                ],
                'mappingOut' => [
                    'type'        => 'array',
                    'description' => 'The mapping aplied to posts `afther` they are handled',
                    'example'     => 'native://default',
                ],
                'entity' => [
                    'type'        => 'string',
                    'description' => 'The EAV entity to use for this endpoint',
                    'example'     => 'native://default',
                ],
                'list' => [
                    'type'        => 'boolean',
                    'description' => 'Whether the endpoint should be a list of values instead of a single entity',
                    'example'     => false,
                ],
                'action' => [
                    'type'        => 'array',
                    'description' => 'The allowed actions on the entity trough this endpoint',
                    'example'     => ['CREATE','READ','UPDATE','DELETE'],
                ],
                'idLocations' => [
                    'type'        => 'array',
                    'description' => 'Places where we might find the id for our entity',
                    'example'     => ['CREATE','READ','UPDATE','DELETE'],
                ],
            ],
        ];
    }

    /**
     * This function runs the endpoint service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws TransportExceptionInterface|LoaderError|RuntimeError|SyntaxError
     *
     * @return array
     */
    public function __run(array $data, array $configuration): array
    {
        return $this->endpointService->endpointHandler($data, $configuration);
    }
}
