<?php
/**
 * Date: 01-05-2020
 * @author Raymond Kiekens
 * @copyright (c) Oberon 2020
 */

namespace TOR\GraphQL\Result;

use TOR\GraphQL\Model\TripPricingCollection;

class CreateOrReplaceTripPricingsResult
{
    /**
     * @var TripPricingCollection
     */
    public $createOrReplaceTripPricings;

    /**
     * @return AllotmentCollection
     */
    public function getCreateOrReplaceTripPricings(): TripPricingCollection
    {
        return $this->createOrReplaceTripPricings;
    }

    /**
     * @param TripPricingCollection $createOrReplaceTripPricings
     * @return self
     */
    public function setCreateOrReplaceTripPricings(
        TripPricingCollection $createOrReplaceTripPricings
    ): self {
        $this->createOrReplaceTripPricings = $createOrReplaceTripPricings;

        return $this;
    }
}