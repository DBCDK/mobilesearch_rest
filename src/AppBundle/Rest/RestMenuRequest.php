<?php
/**
 * @file
 */

namespace AppBundle\Rest;

use Doctrine\Bundle\MongoDBBundle\ManagerRegistry as MongoEM;
use AppBundle\Document\Menu as FSMenu;

class RestMenuRequest extends RestBaseRequest
{
    public function __construct(MongoEM $em)
    {
        parent::__construct($em);

        $this->primaryIdentifier = 'mlid';
        $this->requiredFields = array(
            $this->primaryIdentifier,
            'agency',
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
            $this->primaryIdentifier => (int) $id,
            'agency' => $agency,
        );

        $entity = $this->em
            ->getRepository('AppBundle:Menu')
            ->findOneBy($criteria);

        return $entity;
    }

    protected function insert()
    {
        $entity = $this->prepare(new FSMenu());

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

    public function prepare(FSMenu $menu)
    {
        $body = $this->getParsedBody();

        $mlid = !empty($body[$this->primaryIdentifier]) ? $body[$this->primaryIdentifier] : 0;
        $menu->setMlid($mlid);

        $agency = !empty($body['agency']) ? $body['agency'] : '000000';
        $menu->setAgency($agency);

        $type = !empty($body['type']) ? $body['type'] : 'undefined';
        $menu->setType($type);

        $name = !empty($body['name']) ? $body['name'] : 'Undefined';
        $menu->setName($name);

        $url = !empty($body['url']) ? $body['url'] : '';
        $menu->setUrl($url);

        $order = !empty($body['order']) ? $body['order'] : 0;
        $menu->setOrder($order);

        return $menu;
    }
}
