<?php

declare(strict_types=1);

namespace Mtarld\JsonEncoderBundle\DataModel\Decode;

use Symfony\Component\TypeInfo\Type\CollectionType;

/**
 * Represents a collection in the data model graph representation.
 */
final readonly class CollectionNode implements DataModelNodeInterface
{
    public function __construct(
        public CollectionType $type,
        public DataModelNodeInterface $item,
    ) {
    }

    public function getIdentifier(): string
    {
        return (string) $this->type;
    }

    public function getType(): CollectionType
    {
        return $this->type;
    }
}
