<?php
/**
 * Date: 30-04-2020
 * @author Raymond Kiekens
 * @copyright (c) Oberon 2020
 */

namespace Oberon\TravelbaseClient\Response;

use Oberon\TravelbaseClient\Result\CreateOrReplaceAllotmentsResult;

class CreateOrReplaceAllotmentsCallResponseBody implements GraphQLCallResponseBodyInterface
{
    /**
     * @var CreateOrReplaceAllotmentsResult
     */
    private $data;

    public function __construct(CreateOrReplaceAllotmentsResult $data)
    {
        $this->data = $data;
    }

    /**
     * @return CreateOrReplaceAllotmentsResult
     */
    public function getData(): CreateOrReplaceAllotmentsResult
    {
        return $this->data;
    }
}
