<?php
/**
 * @file
 */

namespace AppBundle\Rest;

use Doctrine\Bundle\MongoDBBundle\ManagerRegistry as MongoEM;
use AppBundle\Document\Lists as FSList;

class RestListsRequest extends RestBaseRequest
{
    public function __construct(MongoEM $em)
    {
        parent::__construct($em);

        $this->primaryIdentifier = 'key';
        $this->requiredFields = array(
            $this->primaryIdentifier,
            'agency',
            'nid',
        );
    }

    protected function exists($id, $agency)
    {
        $entity = $this->get($id, $agency);

        return !is_null($entity);
    }

    protected function get($id, $agency)
    {
        $criteria = array(
            $this->primaryIdentifier => $id,
            'agency' => $agency,
        );

        $entity = $this->em
            ->getRepository('AppBundle:Lists')
            ->findOneBy($criteria);

        return $entity;
    }

    protected function insert()
    {
        $entity = $this->prepare(new FSList());

        $dm = $this->em->getManager();
        $dm->persist($entity);
        $dm->flush();

        return $entity;
    }

    protected function update($id, $agency)
    {
        $loadedEntity = $this->get($id, $agency);
        $updatedEntity = $this->prepare($loadedEntity);

        $dm = $this->em->getManager();
        $dm->flush();

        return $updatedEntity;
    }

    protected function delete($id, $agency)
    {
        $entity = $this->get($id, $agency);

        $dm = $this->em->getManager();
        $dm->remove($entity);
        $dm->flush();

        return $entity;
    }

    public function prepare(FSList $list)
    {
        $body = $this->getParsedBody();

        $key = !empty($body[$this->primaryIdentifier]) ? $body[$this->primaryIdentifier] : 0;
        $list->setKey($key);

        $nid = !empty($body['nid']) ? $body['nid'] : '0';
        $list->setAgency($nid);

        $agency = !empty($body['agency']) ? $body['agency'] : '000000';
        $list->setAgency($agency);

        $name = !empty($body['name']) ? $body['name'] : 'Undefined';
        $list->setName($name);

        $nids = !empty($body['nids']) ? $body['nids'] : array();
        $list->setNids($nids);

        $type = !empty($body['type']) ? $body['type'] : array();
        $list->setType($type);

        $promoted = !empty($body['promoted']) ? $body['promoted'] : array();
        $list->setPromoted($promoted);

        $weight = !empty($body['weight']) ? $body['weight'] : 0;
        $list->setWeight($weight);

        return $list;
    }
}
