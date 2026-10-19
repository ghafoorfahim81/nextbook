<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait HasDependencyCheck
{
    /**
     * Check if the model has dependencies that prevent deletion
     *
     * @return array Array of dependency information
     */
    public function getDependencies(): array
    {
        $dependencies = [];

        // Get all relationships defined in the model
        $relationships = $this->getRelationships();

        foreach ($relationships as $relationName => $relationConfig) {
            $count = $this->getDependencyCount($relationName, $relationConfig);

            if ($count > 0) {
                $dependencies[] = [
                    'relation' => $relationName,
                    'count' => $count,
                    'model' => $relationConfig['model'] ?? $relationName,
                    'message' => $relationConfig['message'] ?? "This record is used in {$relationName}"
                ];
            }
        }

        return $dependencies;
    }

    /**
     * Check if the model can be deleted (no dependencies)
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return empty($this->getDependencies());
    }

    /**
     * Get dependency count for a specific relationship
     *
     * @param string $relationName
     * @param array $relationConfig
     * @return int
     */
    protected function getDependencyCount(string $relationName, array $relationConfig): int
    {
        try {
            // Check if the relationship method exists
            if (!method_exists($this, $relationName)) {
                return 0;
            }

            $relation = $this->$relationName();

            // Only count non-soft-deleted records for dependency checks
            // This ensures that soft-deleted related records don't prevent operations
            $relation = $relation->whereNull($relation->getModel()->getQualifiedDeletedAtColumn());

            if (isset($relationConfig['conditions'])) {
                return $relation->where($relationConfig['conditions'])->count();
            }

            return $relation->count();
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error("Dependency check failed for {$relationName}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get relationships configuration for dependency checking
     * Override this method in your model to define which relationships to check
     *
     * @return array
     */
    protected function getRelationships(): array
    {
        return [];
    }

    /**
     * Get a formatted message about dependencies
     *
     * @return string|null
     */
    public function getDependencyMessage(): ?string
    {
        $dependencies = $this->getDependencies();

        if (empty($dependencies)) {
            return null;
        }

        $messages = array_map(function ($dep) {
            return "{$dep['count']} " . $this->translateDependencyModel($dep['model']);
        }, $dependencies);

        $lastMessage = array_pop($messages);
        $message = empty($messages)
            ? $lastMessage
            : implode(__('messages.dependency.list_separator'), $messages)
                . __('messages.dependency.list_last_separator') . $lastMessage;

        return __('messages.dependency.cannot_delete_used_in', ['items' => $message]);
    }

    /**
     * Models name their dependents with an English label ("items",
     * "unit measures"); show it in the user's language when we have one.
     */
    protected function translateDependencyModel(string $model): string
    {
        $key = 'messages.dependency.models.' . str_replace(' ', '_', $model);
        $translated = __($key);

        return $translated === $key ? str_replace('_', ' ', $model) : $translated;
    }
}
