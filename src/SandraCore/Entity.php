<?php
/**
 * Created by PhpStorm.
 * User: shaban
 * Date: 12.02.19
 * Time: 16:08
 */

namespace SandraCore;


class Entity implements Dumpable
{


    private $entityIsa ;
    private $entityContainedIn ;
    private $factory ;
    public $subjectConcept ;  /** @var $subjectConcept Concept */
    public $verbConcept ; /** @var $verbConcept Concept */
    public $targetConcept ; /** @var $targetConcept Concept */
    public $entityId ; // The is the id of the table link
    public $entityRefs ; /** @var $entityRefs Reference[] */

    public $system ;

    public function __construct($sandraConcept,$sandraReferencesArray,$factory,$entityId,$conceptVerb,$conceptTarget,System $system){

        $this->system = $system ;

        if(is_array($sandraReferencesArray)) {
            foreach ($sandraReferencesArray as $sandraReferenceConceptId => $sandraReferenceValue) {

                //if $sandraReferenceConceptId is not an id then we need to convert it
                $sandraReferenceConcept = $system->conceptFactory->getConceptFromShortnameOrId($sandraReferenceConceptId);
                $sandraReferenceConceptId = $sandraReferenceConcept->idConcept;

                $referenceConcept = $this->system->conceptFactory->getForeignConceptFromId($sandraReferenceConceptId);

                $ref = new Reference($referenceConcept, $this, $sandraReferenceValue, $this->system);
                $this->entityRefs[$sandraReferenceConceptId] = $ref;
                $this->entityId = $entityId;
                $this->factory = $factory;

            }
        }

        /** @var $sandraConcept Concept */

        $this->subjectConcept = $sandraConcept;
        $this->verbConcept = CommonFunctions::somethingToConcept($conceptVerb,$system);
        $this->targetConcept = CommonFunctions::somethingToConcept($conceptTarget,$system);


        /** @var $sandraConcept Concept */

        $sandraConcept->addEntity($this);


    }

    public function get($referenceName){

        $refId = $this->system->systemConcept->get($referenceName);
        //echoln("getting $referenceName is $refId");

        if (!isset($this->entityRefs[$refId]))
            return null ;

        return $this->entityRefs[$refId]->refValue ;

    }

    public function getJoined($joinVerb,$referenceName){

        $verbConceptId = CommonFunctions::somethingToConceptId($joinVerb,$this->system);
        $joindedConceptId = reset($this->subjectConcept->tripletArray[$verbConceptId]);
        $joinedConcept = $this->system->conceptFactory->getConceptFromId($joindedConceptId);

        /** @var $factory EntityFactory */
        $factory = $this->factory ;

        //we find the joined factory
        $joinedFactory = $factory->joinedFactoryArray[$verbConceptId];

        /** @var $joinedFactory EntityFactory */
        //we need to find the correct datapath from the factoryK
        $mainVerb = CommonFunctions::somethingToConceptId($joinedFactory->entityReferenceContainer,$this->system) ;
        $mainTarget = CommonFunctions::somethingToConceptId($joinedFactory->entityContainedIn,$this->system) ;

        $joinedEntity = $joinedConcept->entityArray[$mainVerb][$mainTarget];
        return $joinedEntity->get($referenceName);

    }

    public function getBrotherEntity($brotherVerb,$brotherTarget=null){

        if(!is_null($brotherTarget)) {

            $verbConceptId = CommonFunctions::somethingToConceptId($brotherVerb, $this->system);
            $targetConceptId = CommonFunctions::somethingToConceptId($brotherTarget, $this->system);

            $factory = $this->factory;
            //we find the brother entity
            if (!isset($factory->brotherEntitiesArray[$this->subjectConcept->idConcept][$verbConceptId][$targetConceptId])) return null;

            $entity = $factory->brotherEntitiesArray[$this->subjectConcept->idConcept][$verbConceptId][$targetConceptId];
            return $entity ;
        }
        //target is null then we should have only one target
        $verbConceptId = CommonFunctions::somethingToConceptId($brotherVerb, $this->system);


        $factory = $this->factory;
        //we find the brother entity
        if (!isset($factory->brotherEntitiesArray[$this->subjectConcept->idConcept][$verbConceptId])) return null;
       // if(count($factory->brotherEntitiesArray[$this->subjectConcept->idConcept][$verbConceptId])>1)
         //   $this->system->systemError('400','entityFactory','critical',"multiple targets for verb". $brotherVerb) ;

        $entity = $factory->brotherEntitiesArray[$this->subjectConcept->idConcept][$verbConceptId];


        return $entity ;

    }

    public function getBrotherReference($brotherVerb,$brotherTarget=null,$referenceName=null){


        $entity = $this->getBrotherEntity($brotherVerb,$brotherTarget);

        if(is_null($entity)) {return null ;}
        else if(!is_array($entity)){

            return $entity->get($referenceName);
        }

        $result = null ;

        foreach ($entity as $entityTarget => $singleEntity){
            $result[$entityTarget] = $singleEntity->get($referenceName);

        }
        return $result ;

    }

    public function setBrotherEntity($brotherVerb,$brotherTarget,$referenceArray){

        $verbConceptId = CommonFunctions::somethingToConceptId($brotherVerb,$this->system);
        $targetConceptId = CommonFunctions::somethingToConceptId($brotherTarget,$this->system);

        /** @var $factory EntityFactory */
        $factory = $this->factory ;

        $brotherEntity = CommonFunctions::createEntity($this->subjectConcept,$brotherVerb,$brotherTarget,$referenceArray,$factory,$this->system,true);

        $factory->brotherEntitiesArray[$this->subjectConcept->idConcept][$verbConceptId][$targetConceptId] = $brotherEntity ;


        return $brotherEntity ;

    }


    public function getReference($referenceName){

        $refId = $this->system->systemConcept->get($referenceName);

        if (isset($this->entityRefs[$refId])) {
            return $this->entityRefs[$refId];
        }
        return null ;

    }

    public function createOrUpdateRef($referenceShortname,$value): Reference{

        $referenceConcept = $this->system->conceptFactory->getConceptFromShortnameOrId($referenceShortname);
        DatabaseAdapter::rawCreateReference($this->entityId,$referenceConcept->idConcept,$value,$this->system);
        $ref  = new Reference($referenceConcept,$this,$value,$this->system);
        $this->entityRefs[$referenceConcept->idConcept] = $ref ;

        return $ref ;

        //Todo rebuild factory index


    }

    public function getOrInitReference($referenceShortname,$value): Reference{

      $reference = $this->getReference($referenceShortname);

      if(is_null($reference)){

          $reference = $this->createOrUpdateRef($referenceShortname,$value);
      }

      return $reference ;

    }

    public function dumpMeta(){

        $entity['id']=$this->entityId;

        $meta['entity'] = $entity;

        foreach ($this->entityRefs as $key => $value){
            /** @var $value Reference */

            $references[$value->refConcept->dumpMeta()] = $value->dumpMeta() ;
        }

        if (!$this instanceof ForeignEntity) {
            $conceptLinks = $this->subjectConcept->dumpMeta(true);
            $meta['conceptSubject']  = $conceptLinks ;

        }

        $meta['references'] = $references ;

        return $meta ;

    }

}