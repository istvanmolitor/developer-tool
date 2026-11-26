<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent base for every repository.
 *
 * Subclasses name their model via {@see Model()} and inherit the generic CRUD
 * operations; domain-specific queries live in the subclass, built on top of
 * {@see newQuery()} so they never reach for the model statically.
 *
 * @template TModel of Model
 *
 * @implements Repository<TModel>
 */
abstract class EloquentRepository implements Repository
{
    /**
     * Fully-qualified model class this repository wraps.
     *
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * A fresh query builder for the wrapped model — the single entry point every
     * query in a subclass should start from.
     *
     * @return Builder<TModel>
     */
    protected function newQuery(): Builder
    {
        return $this->model()::query();
    }

    /**
     * @return Collection<int, TModel>
     */
    public function all(): Collection
    {
        return $this->newQuery()->get();
    }

    /**
     * @return TModel|null
     */
    public function find(int|string $id): ?Model
    {
        return $this->newQuery()->find($id);
    }

    /**
     * @return TModel
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->newQuery()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model
    {
        return $this->newQuery()->create($attributes);
    }

    /**
     * @param  TModel  $model
     * @return TModel
     */
    public function save(Model $model): Model
    {
        $model->save();

        return $model;
    }

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->update($attributes);

        return $model;
    }

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): void
    {
        $model->delete();
    }
}
