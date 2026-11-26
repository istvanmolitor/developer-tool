<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Common data-access contract shared by every repository.
 *
 * Concrete per-model contracts extend this and add the domain-specific query
 * methods their callers actually need. The generic operations below cover the
 * plain CRUD every entity supports.
 *
 * @template TModel of Model
 */
interface Repository
{
    /**
     * Every row, unordered unless the concrete repository says otherwise.
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection;

    /**
     * Resolve a single model by primary key, or null when it does not exist.
     *
     * @return TModel|null
     */
    public function find(int|string $id): ?Model;

    /**
     * Resolve a single model by primary key or throw.
     *
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $id): Model;

    /**
     * Persist a new model from the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * Persist an already-composed (new or dirty) model.
     *
     * The caller builds or mutates the model — including via forceFill for
     * guarded attributes — and this writes it to the database, keeping the
     * actual persistence call inside the repository.
     *
     * @param  TModel  $model
     * @return TModel
     */
    public function save(Model $model): Model;

    /**
     * Apply the attributes to an existing model and persist it.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model;

    /**
     * Delete the given model.
     *
     * @param  TModel  $model
     */
    public function delete(Model $model): void;
}
