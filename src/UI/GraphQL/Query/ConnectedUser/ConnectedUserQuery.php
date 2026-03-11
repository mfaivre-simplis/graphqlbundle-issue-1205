<?php

declare(strict_types=1);

namespace App\UI\GraphQL\Query\ConnectedUser;

use Overblog\GraphQLBundle\Annotation as GQL;

#[GQL\Provider]
#[GQL\Access('isAnonymous()')]
final readonly class ConnectedUserQuery
{
    #[GQL\Query(name: 'connectedUser', type: 'Boolean!')]
    #[GQL\Access('isAnonymous()')]
    public function __invoke(): bool
    {
        return true;
    }
}
