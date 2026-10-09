<?php

declare(strict_types=1);

namespace Msstc4Symfony\LogicBundle\Application\Pipeline;

use Msstc4Symfony\LogicBundle\Application\Action\ActionInterface;
use Msstc4Symfony\LogicBundle\Application\Action\OutputInterface;
use ReflectionClass;

final readonly class ActionContext
{
    /**
     * @param class-string<ActionInterface<OutputInterface>> $actionClass
     * @param array<class-string, object> $attributes first instance of each class attribute, keyed by attribute class
     */
    public function __construct(
        public string $actionClass,
        private array $attributes = [],
    ) {
    }

    /**
     * Attributes whose class is not installed are skipped, so an optional integration's attribute
     * never breaks an Action.
     *
     * @param class-string<ActionInterface<OutputInterface>> $actionClass
     */
    public static function of(string $actionClass): self
    {
        $attributes = [];
        foreach (new ReflectionClass($actionClass)->getAttributes() as $attribute) {
            $name = $attribute->getName();
            if (!class_exists($name) || isset($attributes[$name])) {
                continue;
            }

            $attributes[$name] = $attribute->newInstance();
        }

        return new self($actionClass, $attributes);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function attribute(string $class): ?object
    {
        $attribute = $this->attributes[$class] ?? null;

        return $attribute instanceof $class ? $attribute : null;
    }
}
