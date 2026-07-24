<?php

declare(strict_types=1);

namespace LiveTennisApi\Model;

use JsonSerializable;
use ReflectionObject;
use ReflectionProperty;
use Throwable;

/**
 * Base for every response object.
 *
 * Two rules govern everything here, both taken from the API's own contract:
 *
 *  1. Never reject an unknown field. The spec states additive changes ship
 *     within v1, so a client that validates strictly breaks the first time a
 *     field is added. Unknown keys are kept in {@see Model::$raw} and are also
 *     reachable via `__get`, so a new server-side field is usable from an old
 *     client without an upgrade.
 *
 *  2. Never lose the payload. Every model keeps the exact array it was built
 *     from. If a model is wrong, `$obj->raw` is still the truth.
 *
 * Consequently {@see Model::fromArray()} never raises on shape. A field that is
 * absent stays at its default (`null`); a field whose type the server got wrong
 * is preserved in `$raw` rather than fatally coerced.
 */
abstract class Model implements JsonSerializable
{
    /** The exact payload this model was built from. */
    public array $raw = [];

    /**
     * Build a model from a response object. `null` in, `null` out.
     */
    public static function fromArray(?array $data): ?static
    {
        if ($data === null) {
            return null;
        }

        $obj = new static();

        foreach ((new ReflectionObject($obj))->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();
            if ($name === 'raw' || !array_key_exists($name, $data)) {
                continue;
            }

            try {
                $obj->{$name} = $data[$name];
            } catch (Throwable) {
                // The server sent a value whose type does not match the declared
                // property (a documented additive-change risk). Keep it in $raw
                // rather than fatally failing the whole decode.
            }
        }

        $obj->raw = $data;
        $obj->hydrate($data);

        return $obj;
    }

    /**
     * Hook for subclasses to decode nested objects (a score, a list of prices).
     * The default is a no-op.
     */
    protected function hydrate(array $data): void
    {
    }

    /**
     * Expose fields the server sent that this version does not declare.
     *
     * Only consulted when normal property access fails, so declared properties
     * always win and this costs nothing on the common path.
     */
    public function __get(string $name): mixed
    {
        return $this->raw[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->raw[$name]);
    }

    /**
     * The original payload, exactly as received.
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    public function jsonSerialize(): array
    {
        return $this->raw;
    }
}
