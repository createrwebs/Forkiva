<?php

namespace Modules\Order\Pricing;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/** Request-local calculation inputs. No persistence, session identity or network locks. */
abstract class PricingState
{
    private array $lines = [];

    private array $conditions = [];

    /**
     * Discard request-local lines; pricing context is cleared by the concrete calculator.
     */
    public function clear(): void
    {
        $this->lines = [];
    }

    /**
     * Discard all request-local pricing context and adjustments.
     */
    public function clearConditions(): void
    {
        $this->conditions = [];
    }

    /**
     * Return the in-memory lines keyed by their stable identities.
     */
    public function getContent(): Collection
    {
        return collect($this->lines);
    }

    /**
     * Find a request-local line without querying persistent storage.
     */
    public function get($id): ?Fluent
    {
        return $this->lines[$id] ?? null;
    }

    /**
     * Check whether a stable line identity is present in the calculation.
     */
    public function has($id): bool
    {
        return isset($this->lines[$id]);
    }

    /**
     * Sum the quantities currently represented by the calculation.
     */
    public function getTotalQuantity(): int
    {
        return (int) $this->getContent()->sum('quantity');
    }

    /**
     * Merge quantity only for the supplied stable identity and normalize its attributes.
     */
    public function add(array $line): void
    {
        $line['quantity'] += $this->lines[$line['id']]->quantity ?? 0;
        $line['attributes'] = new Fluent($line['attributes']);
        $this->lines[$line['id']] = new Fluent($line);
    }

    /**
     * Apply an absolute or relative quantity and optional replacement attributes.
     */
    public function update($id, $data): bool
    {
        if (! isset($this->lines[$id])) {
            return false;
        }
        $line = $this->lines[$id];
        if (isset($data['quantity'])) {
            $quantity = $data['quantity'];
            $line->quantity = is_array($quantity)
                ? (($quantity['relative'] ?? true) ? $line->quantity + $quantity['value'] : $quantity['value'])
                : $line->quantity + $quantity;
        }
        if (isset($data['attributes'])) {
            $line->attributes = new Fluent($data['attributes']);
        }

        return true;
    }

    /**
     * Remove a line from this calculation only.
     */
    public function remove($id): bool
    {
        unset($this->lines[$id]);

        return true;
    }

    /**
     * Register a named request-local pricing context or adjustment.
     */
    public function condition($condition): Pricing
    {
        $this->conditions[$condition->getName()] = $condition;

        return $this;
    }

    /**
     * Return all registered pricing contexts and adjustments.
     */
    public function getConditions(): Collection
    {
        return collect($this->conditions);
    }

    /**
     * Resolve one named pricing context or adjustment.
     */
    public function getCondition($name): ?PricingCondition
    {
        return $this->conditions[$name] ?? null;
    }

    /**
     * Select pricing adjustments by their declared type.
     */
    public function getConditionsByType($type): Collection
    {
        return $this->getConditions()->filter(fn ($condition) => $condition->getType() === $type);
    }

    /**
     * Remove one named request-local pricing adjustment.
     */
    public function removeCondition($name): void
    {
        unset($this->conditions[$name]);
    }

    /**
     * Remove all request-local adjustments of the given type.
     */
    public function removeConditionsByType($type): ?Pricing
    {
        foreach ($this->getConditionsByType($type)->keys() as $key) {
            unset($this->conditions[$key]);
        }

        return $this;
    }
}
