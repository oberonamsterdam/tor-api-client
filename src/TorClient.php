<?php
/**
 * Date: 29-04-2020
 * @author Raymond Kiekens
 * @copyright (c) Oberon 2020
 */

namespace TOR\GraphQL;

use GraphQL\Client;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\Variable;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use TOR\GraphQL\Exception\NotFoundException;
use \DateTime;
use TOR\GraphQL\Model\Accommodation;
use TOR\GraphQL\Model\AllotmentCollection;
use TOR\GraphQL\Model\BookingRelay;
use TOR\GraphQL\Model\Partner;
use TOR\GraphQL\Model\RentalUnit;
use TOR\GraphQL\Model\TripPricingCollection;
use TOR\GraphQL\Response\AccommodationCallResponseBody;
use TOR\GraphQL\Response\CreateOrReplaceAllotmentsCallResponseBody;
use TOR\GraphQL\Response\CreateOrReplaceTripPricingsCallResponseBody;
use TOR\GraphQL\Response\DeleteTripsCallResponseBody;
use TOR\GraphQL\Response\GraphQLCallResponseBodyInterface;
use TOR\GraphQL\Response\PartnerCallResponseBody;
use TOR\GraphQL\Response\PartnersCallResponseBody;

class TorClient
{
    /** @var Client */
    private $client;

    /** @var Serializer  */
    private $serializer;

    public function __construct(?string $endPoint = null, ?string $apiKey = null)
    {
        $endPointEnvVariable = getenv('TOR_GRAPHQL_ENDPOINT');
        $apiKeyEnvVariable = getenv('TOR_GRAPHQL_APIKEY');

        $apiKey = $apiKey ?? $apiKeyEnvVariable;
        $endPoint = $endPoint ?? $endPointEnvVariable;

        if (!$endPoint) {
            throw new NotFoundException('Endpoint not defined');
        }

        if (!$apiKey) {
            throw new NotFoundException('Api key not defined');
        }
        $this->client = new Client($endPoint, ['Authorization' => "Bearer $apiKey"]);

        $encoders = [new JsonEncoder()];
        $normalizers = [
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, new ReflectionExtractor())
        ];

        $this->serializer = new Serializer($normalizers, $encoders);
    }

    public function getPartners(): array
    {
        $query = (new Query('partners'))->setSelectionSet([
            'id',
            'enabled',
            'companyName',
            (new Query('accommodations'))->setSelectionSet([
                'id',
                'enabled',
                'name',
                (new Query('rentalUnits'))->setSelectionSet([
                    'id',
                    'name',
                    'code',
                    'enabled',
                    'type',
                    'maxAllotment',
                    'includedOccupancy'
                ])
            ])
        ]);

        $result = $this->runQuery($query);

        return $this->parseResult($result, PartnersCallResponseBody::class)->getData()->getPartners();
    }

    public function getPartner(int $partnerId): Partner
    {
        $query = (new Query('partner'))
            ->setArguments(['id' => $partnerId])
            ->setSelectionSet([
                'id',
                'enabled',
                'companyName',
                (new Query('accommodations'))->setSelectionSet([
                    'id',
                    'enabled',
                    'name',
                    (new Query('rentalUnits'))->setSelectionSet([
                        'id',
                        'name',
                        'code',
                        'enabled',
                        'type',
                        'maxAllotment',
                        'includedOccupancy'
                    ])
                ])
            ])
        ;

        $result = $this->runQuery($query);

        return $this->parseResult($result, PartnerCallResponseBody::class)->getData()->getPartner();
    }

    public function getUpcomingBookings(int $partnerId, int $limit = 10, ?string $cursor = null): BookingRelay
    {
        $arguments = ['first' => $limit];
        if ($cursor) {
            $arguments['after'] = $cursor;
        }

        $query = (new Query('partner'))
            ->setArguments(['id' => $partnerId])
            ->setSelectionSet([
                (new Query('upcomingBookings'))
                    ->setArguments($arguments)
                    ->setSelectionSet($this->getBookingRelaySelectionSet())
            ])
        ;

        $result = $this->runQuery($query);

        return $this->parseResult($result, PartnerCallResponseBody::class)->getData()->getPartner()->getUpcomingBookings();
    }

    public function getRecentlyUpdatedBookings(int $partnerId, int $limit = 10, ?string $cursor = null): BookingRelay
    {
        $arguments = ['first' => $limit];
        if ($cursor) {
            $arguments['after'] = $cursor;
        }

        $query = (new Query('partner'))
            ->setArguments(['id' => $partnerId])
            ->setSelectionSet([
                (new Query('recentlyUpdatedBookings'))
                    ->setArguments($arguments)
                    ->setSelectionSet($this->getBookingRelaySelectionSet())
            ])
        ;

        $result = $this->runQuery($query);

        return $this->parseResult($result, PartnerCallResponseBody::class)->getData()->getPartner()->getRecentlyUpdatedBookings();
    }

    public function getAllBookings(
        int $partnerId,
        int $limit = 10,
        string $cursor = null,
        ?DateTime $startDate = null,
        ?DateTime $endDate = null,
        ?string $searchQuery = null,
        ?array $rentalUnitIds = []
    ): BookingRelay {
        $arguments = ['first' => $limit];
        if ($cursor) {
            $arguments['after'] = $cursor;
        }

        if ($startDate) {
            $arguments['startDate'] = $startDate;
        }
        if ($endDate) {
            $arguments['endDate'] = $endDate;
        }
        if ($searchQuery) {
            $arguments['searchQuery'] = $searchQuery;
        }
        if ($rentalUnitIds) {
            $arguments['rentalUnitIds'] = $rentalUnitIds;
        }

        $query = (new Query('partner'))
            ->setArguments(['id' => $partnerId])
            ->setSelectionSet([
                (new Query('allBookings'))
                    ->setArguments($arguments)
                    ->setSelectionSet($this->getBookingRelaySelectionSet())
            ])
        ;

        $result = $this->runQuery($query);

        return $this->parseResult($result, PartnerCallResponseBody::class)->getData()->getPartner()->getAllBookings();
    }

    private function getBookingRelaySelectionSet(): array
    {
        return [
            'totalCount',
            (new Query('pageInfo'))->setSelectionSet([
                'hasNextPage',
                'hasPreviousPage',
                'startCursor',
                'endCursor',
            ]),
            (new Query('edges'))->setSelectionSet([
                'cursor',
                (new Query('node'))->setSelectionSet([
                    'id',
                    'number',
                    'arrivalDate',
                    'departureDate',
                    'duration',
                    'amountAdults',
                    'amountChildren',
                    'amountBabies',
                    'amountDogs',
                    'status',
                    'customerComment',
                    'rentalSum',
                    'travelSum',
                    'createdAt',
                    'updatedAt',
                    (new Query('rentalUnit'))->setSelectionSet([
                        'id',
                    ]),
                    (new Query('partnerPriceLines'))->setSelectionSet([
                        'category',
                        'label',
                        'unitPrice',
                        'modifier',
                        'totalPrice',
                    ]),
                    (new Query('order'))->setSelectionSet([
                        'id',
                        'locale',
                        'customerFirstName',
                        'customerInfix',
                        'customerLastName',
                        'customerPhoneNumber',
                        'customerEmail',
                        (new Query('customerAddress'))->setSelectionSet([
                            'street',
                            'number',
                            'postalCode',
                            'city',
                            'countryCode',
                            'countryName'
                        ]),
                    ])
                ])
            ])
        ];

    }

    public function getAccommodation(int $accommodationId): Accommodation
    {
        $query = (new Query('accommodation'))
            ->setArguments(['id' => $accommodationId])
            ->setSelectionSet([
                'id',
                'enabled',
                'name',
                (new Query('rentalUnits'))->setSelectionSet([
                    'id',
                    'name',
                    'code',
                    'enabled',
                    'type',
                    'maxAllotment',
                    'includedOccupancy'
                ])
            ])
        ;
        $result = $this->runQuery($query);

        return $this->parseResult($result, AccommodationCallResponseBody::class)->getData()->getAccommodation();
    }

    public function getRentalUnit(int $rentalUnitId): RentalUnit
    {
        $query = (new Query('rentalUnit'))
            ->setArguments(['id' => $rentalUnitId])
            ->setSelectionSet([
                'id',
                'name',
                'code',
                'enabled',
                'type',
                'maxAllotment',
                'includedOccupancy'
            ])
        ;
        $result = $this->runQuery($query);

        return $this->parseResult($result, RentalUnit::class)->getData()->getRentalUnit();
    }

    public function createOrReplaceAllotments(int $rentalUnitId, AllotmentCollection $allotmentCollection): AllotmentCollection
    {
        $mutation = (new Mutation('createOrReplaceAllotments'))
            ->setVariables([new Variable('input', 'CreateOrReplaceAllotmentsInput', true)])
            ->setArguments(['input' => '$input'])
            ->setSelectionSet([
                (new Query('allotments'))->setSelectionSet([
                    'amount',
                    'date'
                ])
            ])
        ;

        $variables = ['input' => array_merge(['rentalUnitId' => $rentalUnitId],  $allotmentCollection->toArray())];
        $result = $this->runQuery($mutation, $variables);

        return $this->parseResult($result, CreateOrReplaceAllotmentsCallResponseBody::class)->getData()->getCreateOrReplaceAllotments();
    }

    public function createOrReplaceTripPricings(int $rentalUnitId, TripPricingCollection $tripPricingCollection): TripPricingCollection
    {
        $mutation = (new Mutation('createOrReplaceTripPricings'))
            ->setVariables([new Variable('input', 'CreateOrReplaceTripPricingsInput', true)])
            ->setArguments(['input' => '$input'])
            ->setSelectionSet([
                (new Query('tripPricings'))->setSelectionSet([
                    'date',
                    'duration',
                    'price',
                ])
            ])
        ;
        $variables = ['input' => array_merge(['rentalUnitId' => $rentalUnitId], $tripPricingCollection->toArray())];
        $result = $this->runQuery($mutation, $variables);

        return $this->parseResult($result, CreateOrReplaceTripPricingsCallResponseBody::class)->getData()->getCreateOrReplaceTripPricings();
    }

    public function deleteTrips(int $rentalUnitId, ?DateTime $date = null, ?int $duration = null): ?string
    {
        $arguments = ['rentalUnitId' => $rentalUnitId];
        if ($date) {
            $arguments['date'] = $date->format('Y-m-d');
        }
        if ($duration !== null) {
            $arguments['duration'] = $duration;
        }

        $mutation = (new Mutation('deleteTrips'))
            ->setVariables([new Variable('input', 'DeleteTripsInput', true)])
            ->setArguments(['input' => '$input'])
            ->setSelectionSet(['message'])
        ;
        $variables = ['input' => $arguments];
        $result = $this->runQuery($mutation, $variables);

        return $this->parseResult($result, DeleteTripsCallResponseBody::class)->getData()->getDeleteTrips()->getMessage();
    }

    private function runQuery(Query $query, array $variables = []): string
    {
        try {
            $result = $this->client->runQuery($query, false, $variables);
            if (!$result->getResponseBody()) {
                throw new \Exception('No response body found');
            }
            return $result->getResponseBody();
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    private function parseResult(string $data, string $class): GraphQLCallResponseBodyInterface
    {
        return $this->serializer->deserialize($data, $class, 'json');
    }
}
