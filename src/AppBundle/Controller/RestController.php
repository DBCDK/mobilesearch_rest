<?php
/**
 * @file
 */

namespace AppBundle\Controller;

use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use AppBundle\Exception\RestException;
use AppBundle\Rest\RestBaseRequest;
use AppBundle\Rest\RestContentRequest;
use AppBundle\Rest\RestListsRequest;
use AppBundle\Rest\RestMenuRequest;
use AppBundle\Rest\RestTaxonomyRequest;

final class RestController extends Controller
{
    private $lastStatus;
    private $lastMessage;
    private $lastMethod;
    private $lastItems;
    private $rawContent;

    public function __construct()
    {
        $this->lastMessage = '';
        $this->lastStatus = FALSE;
        $this->lastItems = array();
    }

    /**
     * @Route("/content")
     */
    public function contentAction(Request $request)
    {
        $this->lastMethod = $request->getMethod();
        $this->rawContent = $request->getContent();

        $em = $this->get('doctrine_mongodb');
        $rcr = new RestContentRequest($em);

        return $this->relay($rcr);
    }

    /**
     * @ApiDoc(
     *  description="Returns a list of content.",
     *  section="Content (node) related",
     *  requirements={
     *    {
     *       "name"="agency",
     *       "dataType"="integer",
     *       "requirement"="\d+",
     *       "description"="Agency number, owner of the content."
     *    }
     *  },
     *  filters={
     *    {
     *      "name"="node",
     *      "dataType"="string",
     *      "required"=false,
     *      "description"="A single node id or a set of id's separated by comma. Using this parameter ignores any parameters below."
     *    }
     *  },
     *  parameters={
     *     {
     *      "name"="amount",
     *      "dataType"="integer",
     *      "required"=false,
     *      "description"="'Hard' limit of nodes returned. Default: 10.",
     *    },
     *    {
     *      "name"="skip",
     *      "dataType"="integer",
     *      "required"=false,
     *      "description"="Fetch the result set starting from this record. Default: 0."
     *    },
     *    {
     *      "name"="sort",
     *      "dataType"="string",
     *      "required"=false,
     *      "description"="Sort the resulting set based on a certain field. The value should match the hierarchy that mongo object uses. For example, to sort by node title, use 'fields.title.value'. Default: ''."
     *    },
     *    {
     *      "name"="order",
     *      "dataType"="string",
     *      "required"=false,
     *      "format"="ASC|DESC",
     *      "description"="Order of sorting. Either ascending - 'ASC', or descending - 'DESC'. Defaults to descending."
     *    },
     *    {
     *      "name"="type",
     *      "dataType"="string",
     *      "required"=false,
     *      "description"="Only fetch specific node types."
     *    },
     *    {
     *       "name"="vocabulary[]",
     *       "dataType"="string",
     *       "description"="Vocabulary name. Can be multiple, e.g.: vocabulary[]='a'&vocabulary[]='b'.",
     *       "required"=false
     *     },
     *     {
     *       "name"="terms[]",
     *       "dataType"="string",
     *       "description"="Term name. Can be multiple, e.g.: terms[]='a'&terms[]='b'. The count of 'terms' key in the query string MUST match the count of 'vocabulary' keys.",
     *       "required"=false
     *     },
     *     {
     *       "name"="upcoming",
     *       "dataType"="boolean",
     *       "description"="Fetch only upcoming events. Viable when 'type=ding_event'.",
     *       "required"=false
     *     }
     *  }
     * )
     * @Route("/content/fetch")
     * @Method({"GET"})
     */
    public function contentFetchAction(Request $request)
    {
        $this->lastMethod = $request->getMethod();

        // Defaults.
        $fields = array(
          'agency' => null,
          'node' => null,
          'amount' => 10,
          'skip' => 0,
          'sort' => 'fields.created.value',
          'order' => 'DESC',
          'type' => null,
          'vocabulary' => array(),
          'terms' => array(),
          'upcoming' => 0,
        );

        foreach (array_keys($fields) as $field) {
            $fields[$field] = !empty($request->query->get($field)) ? $request->query->get($field) : $fields[$field];
        }

        $em = $this->get('doctrine_mongodb');
        $rcr = new RestContentRequest($em);

        if (empty($fields['agency'])) {
            $this->lastMessage = 'Failed validating request. Check if agency is set.';
        } else {
            $items = call_user_func_array(array($rcr, 'fetchFiltered'), $fields);

            $restHelper = $this->container->get('rest.helper');
            if (!empty($items)) {
                foreach ($items as $item) {
                    $itemFields = $item->getFields();
                    // Attempt to parse fields that contain dates.
                    // Since the field value can be pushed without any
                    // validation, the values might be way different from
                    // what is expected.
                    // Therefore make sure that this part doesn't fail on
                    // weird input.
                    try {
                        if (!empty($itemFields['field_ding_event_date']['value']['from'])) {
                            $itemFields['field_ding_event_date']['value']['from'] = $restHelper->adjustDate($itemFields['field_ding_event_date']['value']['from']);
                        }

                        if (!empty($itemFields['field_ding_event_date']['value']['to'])) {
                            $itemFields['field_ding_event_date']['value']['to'] = $restHelper->adjustDate($itemFields['field_ding_event_date']['value']['to']);
                        }

                        if (!empty($itemFields['created']['value'])) {
                            $itemFields['created']['value'] = $restHelper->adjustDate($itemFields['created']['value']);
                        }

                        if (!empty($itemFields['changed']['value'])) {
                            $itemFields['changed']['value'] = $restHelper->adjustDate($itemFields['changed']['value']);
                        }
                    }
                    catch (RestException $e) {
                        // Do nothing.
                    }

                    $this->lastItems[] = [
                      'id'       => $item->getId(),
                      'nid'      => $item->getNid(),
                      'agency'   => $item->getAgency(),
                      'type'     => $item->getType(),
                      'fields'   => $itemFields,
                      'taxonomy' => $item->getTaxonomy(),
                      'list'     => $item->getList(),
                    ];
                }

                $this->lastStatus = true;
            }
        }

        return $this->setResponse($this->lastStatus, $this->lastMessage, $this->lastItems);
    }

    /**
     * @Route("/content/search")
     * @Method({"GET"})
     * @ApiDoc(
     *   description="Search for content by querying certain field.",
     *   section="Content (node) related",
     *   requirements={
     *     {
     *       "name"="agency",
     *       "dataType"="integer",
     *       "requirement"="\d+",
     *       "description"="Agency number, owner of the content."
     *     },
     *     {
     *       "name"="field",
     *       "dataType"="string",
     *       "description"="Specific field where to search for. The value should match the hierarchy that mongo object uses. For example, to search within node title, use 'fields.title.value'."
     *     },
     *     {
     *       "name"="query",
     *       "dataType"="string",
     *       "description"="The search query."
     *     }
     *   },
     * )
     */
    function searchAction(Request $request)
    {
        $this->lastMethod = $request->getMethod();

        $fields = array(
          'agency' => null,
          'field' => null,
          'query' => null,
        );

        foreach (array_keys($fields) as $field) {
            $fields[$field] = $request->query->get($field);
        }

        $em = $this->get('doctrine_mongodb');
        $rcr = new RestContentRequest($em);

        if (empty($fields['agency'])) {
            $this->lastMessage = 'Failed validating request. Check if agency is set.';
        } elseif (!empty($fields['query'])) {
            $this->lastItems = array();

            $suggestions = $rcr->fetchSuggestions($fields['agency'], $fields['query'], $fields['field']);
            foreach ($suggestions as $suggestion) {
                $fields = $suggestion->getFields();
                $this->lastItems[] = array(
                  'id' => $suggestion->getId(),
                  'nid' => $suggestion->getNid(),
                  'title' => isset($fields['title']['value']) ? $fields['title']['value'] : '',
                  'changed' => isset($fields['changed']['value']) ? $fields['changed']['value'] : '',
                );
            }

            $this->lastStatus = true;
        }

        return $this->setResponse(
          $this->lastStatus,
          $this->lastMessage,
          $this->lastItems
        );
    }

    /**
     * @Route("/menu")
     */
    public function menuAction(Request $request)
    {
        $this->lastMethod = $request->getMethod();
        $this->rawContent = $request->getContent();

        $em = $this->get('doctrine_mongodb');
        $rmr = new RestMenuRequest($em);

        return $this->relay($rmr);
    }

    /**
     * @Route("/list")
     */
    public function listsAction(Request $request)
    {
        $this->lastMethod = $request->getMethod();
        $this->rawContent = $request->getContent();

        $em = $this->get('doctrine_mongodb');
        $rlr = new RestListsRequest($em);

        return $this->relay($rlr);
    }

    /**
     * @Route("/taxonomy/vocabularies/{contentType}")
     * @Method({"GET"})
     * @ApiDoc(
     *   description="Fetches vocabularies for a specific node type.",
     *   section="Vocabulary (taxonomy) related",
     *   requirements={
     *     {
     *       "name"="agency",
     *       "dataType"="integer",
     *       "requirement"="\d+",
     *       "description"="Agency number, owner of the content."
     *     }
     *   }
     * )
     */
    public function taxonomyAction(Request $request, $contentType)
    {
        $this->lastMethod = $request->getMethod();

        $fields = array(
            'agency' => null
        );

        foreach (array_keys($fields) as $field) {
            $fields[$field] = $request->query->get($field);
        }

        $em = $this->get('doctrine_mongodb');
        $rtr = new RestTaxonomyRequest($em);

        if (empty($fields['agency'])) {
            $this->lastMessage = 'Failed validating request. Check if agency is set.';
        }
        else {
            $vocabularies = $rtr->fetchVocabularies($fields['agency'], $contentType);

            $this->lastItems = $vocabularies;
            $this->lastStatus = true;
        }

        return $this->setResponse(
            $this->lastStatus,
            $this->lastMessage,
            $this->lastItems
        );
    }

    /**
     * @Route("/taxonomy/terms/{vocabulary}/{contentType}/{query}")
     * @Method({"GET"})
     * @ApiDoc(
     *   description="Fetches term suggestions from a certain vocabulary that is related to a specific content (node) type.",
     *   section="Vocabulary (taxonomy) related",
     *   requirements={
     *     {
     *       "name"="agency",
     *       "dataType"="integer",
     *       "requirement"="\d+",
     *       "description"="Agency number, owner of the content."
     *     }
     *   }
     * )
     */
    public function taxonomySearchAction(Request $request, $vocabulary, $contentType, $query)
    {
        $this->lastMethod = $request->getMethod();

        $fields = array(
            'agency' => null
        );

        foreach (array_keys($fields) as $field) {
            $fields[$field] = $request->query->get($field);
        }

        $em = $this->get('doctrine_mongodb');
        $rtr = new RestTaxonomyRequest($em);

        if (empty($fields['agency'])) {
            $this->lastMessage = 'Failed validating request. Check if agency is set.';
        }
        else {
            $suggestions = $rtr->fetchTermSuggestions($fields['agency'], $vocabulary, $contentType, $query);

            $this->lastItems = $suggestions;
            $this->lastStatus = true;
        }

        return $this->setResponse(
            $this->lastStatus,
            $this->lastMessage,
            $this->lastItems
        );
    }

    private function relay(RestBaseRequest $rbr)
    {
        try
        {
            $rbr->setRequestBody($this->rawContent);
            $result = $rbr->handleRequest($this->lastMethod);
            $this->lastMessage = $result;
            $this->lastStatus = true;
        }
        catch (RestException $exc)
        {
            $this->lastMessage = 'Request fault: ' . $exc->getMessage();
        }
        catch (\Exception $exc)
        {
            $this->lastMessage = 'Generic fault: ' . $exc->getMessage();
        }

        $response = $this->setResponse($this->lastStatus, $this->lastMessage);

        return $response;
    }

    private function setResponse($status = true, $message = '', $items = array())
    {
        $responseContent = array(
            'status' => $status,
            'message' => $message,
            'items' => $items,
        );

        $response = new Response(json_encode($responseContent));
        $response->headers->set('Content-Type', 'application/json');
        $response->setSharedMaxAge(600);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }
}
