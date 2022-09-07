<?php

namespace App\Subscriber;

use App\ActionHandler\ActionHandlerInterface;
use App\Entity\Action;
use App\Event\ActionEvent;
use App\Service\ObjectEntityService;
use Doctrine\ORM\EntityManagerInterface;
use JWadhams\JsonLogic;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ActionSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;
    private ObjectEntityService $objectEntityService;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'commongateway.handler.pre'     => 'handleEvent',
            'commongateway.handler.post'    => 'handleEvent',
            'commongateway.response.pre'    => 'handleEvent',
            'commongateway.cronjob.trigger' => 'handleEvent',
            'commongateway.object.create'   => 'handleEvent',
            'commongateway.object.read'     => 'handleEvent',
            'commongateway.object.update'   => 'handleEvent',
            'commongateway.object.delete'   => 'handleEvent',
            'commongateway.action.event'    => 'handleEvent',

        ];
    }

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container, ObjectEntityService $objectEntityService)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
        $this->objectEntityService = $objectEntityService;
    }

    public function runFunction(Action $action, array $data): array
    {
        $class = $action->getClass();
        $object = new $class($this->container);
        if ($object instanceof ActionHandlerInterface) {
            $data = $object->__run($data, $action->getConfiguration());
        }

        return $data;
    }

    public function checkConditions(Action $action, array $data): bool
    {
        $conditions = $action->getConditions();

        $result = JsonLogic::apply($conditions, $data);

        return (bool) $result;
    }

    /**
     * Handle a single action on an event
     *
     * @param Action $action
     * @param ActionEvent $event
     * @return ActionEvent
     */
    public function handleAction(Action $action, ActionEvent $event): ActionEvent
    {
        // Lets masure the time this action takes
        $stopwatch = new Stopwatch();
        $stopwatch->start($action->getName(), 'eventActions');

        if ($this->checkConditions($action, $event->getData())) {
            $event->setData($this->runFunction($action, $event->getData()));
            // throw events
            foreach ($action->getThrows() as $throw) {
                $this->objectEntityService->dispatchEvent('commongateway.action.event', $event->getData(), $throw);
            }
        }

        // Stop the clock
        $this->stopwatch->stop($action->getName());

        return $event;
    }

    /**
     * Turning symfony events into gateway events
     *
     * @param ActionEvent $event
     * @return ActionEvent
     */
    public function handleEvent(ActionEvent $event): ActionEvent
    {
        // Lets mesure the time this event takes
        $stopwatch = new Stopwatch();
        $stopwatch->start($event->getType(), 'event');

        // bij normaal gedrag
        if (!$event->getSubType()) {
            $actions = $this->entityManager->getRepository('App:Action')->findByListens($event->getType());
        }
        // Anders als er wel een subtype is
        else {
            $actions = $this->entityManager->getRepository('App:Action')->findByListens($event->getSubType());
        }

        foreach ($actions as $action) {
            $this->handleAction($action, $event);
        }

        // Stop the clock
        $this->stopwatch->stop($event->getType());

        return $event;
    }
}
