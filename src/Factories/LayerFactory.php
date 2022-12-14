<?php

declare(strict_types=1);

namespace Pest\Arch\Factories;

use Pest\Arch\Exceptions\LayerNotFound;
use Pest\Arch\Options\LayerOptions;
use Pest\Arch\Repositories\ObjectsRepository;
use Pest\Arch\VendorObjectDescription;
use PHPUnit\Architecture\Asserts\Dependencies\Elements\ObjectUses;
use PHPUnit\Architecture\Elements\Layer\Layer;
use PHPUnit\Architecture\Elements\ObjectDescription;

/**
 * @internal
 */
final class LayerFactory
{
    /**
     * Creates a new Layer Factory instance.
     */
    public function __construct(
        private readonly ObjectsRepository $objectsStorage,
    ) {
        // ...
    }

    /**
     * Make a new Layer using the given name.
     *
     * @throws LayerNotFound
     */
    public function make(LayerOptions $options, string $name): Layer
    {
        $objects = array_map(function (ObjectDescription $object) use ($options): ObjectDescription {
            if ($object instanceof VendorObjectDescription) {
                return $object;
            }

            if ($options->exclude === []) {
                return $object;
            }

            $object = clone $object;

            /** @var \ArrayIterator<int, string> $uses */
            $uses = $object->uses->getIterator();

            $object->uses = new ObjectUses(array_values(
                array_filter(iterator_to_array($uses), function ($use) use ($options): bool {
                    foreach ($options->exclude as $exclude) {
                        if (str_starts_with($use, $exclude)) {
                            return false;
                        }
                    }

                    return true;
                }))
            );

            return $object;
        }, $this->objectsStorage->allByNamespace($name));

        $layer = (new Layer($objects))->leaveByNameStart($name);

        foreach ($options->exclude as $exclude) {
            $layer = $layer->excludeByNameStart($exclude);
        }

        if ($layer->getIterator()->count() === 0) { // @phpstan-ignore-line
            throw new LayerNotFound($name);
        }

        return $layer;
    }
}
