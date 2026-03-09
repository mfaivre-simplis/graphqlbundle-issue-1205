<?php

declare(strict_types=1);

namespace App\GraphQL;

use Overblog\GraphQLBundle\Annotation as GQL;

#[GQL\Provider]
#[GQL\IsPublic('true')]
class QueryProvider
{
    #[GQL\Query(type: 'String!')]
    public function hello(): string
    {
        return 'Hello, world!';
    }
}
