<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use App\Service\DataService;
use Doctrine\ORM\EntityManager;

/**
 * The data service aims at providing an acces layer to request, session and user information tha can be accesed and changed from differend contexts e.g. actionHandels, Events etc
 */
class EndpointService
{
    private ValidationService $validationService;
    private DataService $dataservice;
    private EntityManager $entityManager;

    public function __construct(
        ValidationService $validationService,
        DataService $dataservice,
        EntityManager $entityManager)
    {
        $this->validationService = $validationService;
        $this->dataservice = $dataservice;
        $this->entityManager = $entityManager;
    }

    /**
     * Handles the sending of an email based on an event.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array
     */
    public function endpointHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        // Does this endpoint have a mapping in?
        if($this->configuration['mappingIn']){
            $this->data = $this->dataservice->mapper( $this->data, $this->configuration['mappingIn'], $this->configuration['list']);
        }

        // Does this endpoint represent an EAV object?
        if($this->configuration['entity'] && in_array($this->dataservice->getRequest()->getMethod(),['POST','PUT','PATCH'])){
            // Als object al bestaad dan....
            if($id = $this->getIdFromRequest()){

            }
            // Als object nog niet bestaad dan ....
            else{
                $entity = $this->entityManager->getRepository('App:Entity')->find($this->configuration['entity']);
                $object = New ObjectEntity($entity);
                $object->hydrate($this->data );
            }

            // Lets validate
            $validation = $this->validationService->validateEntity($object);
            if(!$validation){

            }

        }

        // Does this endpoint have a mapping out?
        if($this->configuration['mappingOut']){
            $this->data = $this->dataservice->mapper( $this->data, $this->configuration['mappingOut'], $this->configuration['list']);
        }

        // Genereren van een responce

        return $data;
    }

    /*
     * Getting the ID from a request
     */
    public function getIdFromRequest(){

    }

}
