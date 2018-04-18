<?php

namespace RS\Form\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;

trait HasRelationships
{

    protected $relationsMap = [
      HasOne::class        => "hasOne",
      HasMany::class       => "hasMany",
      BelongsToMany::class => "belongsToMany"
    ];

    /**
     * The related model instance.
     *
     * @var Model
     */
    protected $related;

    /**
     * The relation.
     *
     * @var Relation
     */
    protected $relation;

    /**
     * Add relation to form
     *
     * @param string $relationKey
     * @param string $formlet
     * @param int    $count
     */
    public function relation(string $relationKey, string $formlet, \Closure $closure = null, int $count = 1)
    {

        if (!isset($this->model)) {
            return;
        }

        $relation = $this->getRelationshipFromMethod($relationKey);

        if ($method = @$this->relationsMap[get_class($relation)]) {
            $this->$method($relation, $relationKey, $formlet, $closure, $count);
        }
    }

    /**
     * Get the related model
     *
     * @return Model|null
     */
    public function getRelated(): ?Model
    {
        return $this->related;
    }

    /**
     * Has One Relation
     *
     * @param HasOne        $relation
     * @param string        $relationKey
     * @param string        $class
     * @param \Closure|null $closure
     */
    protected function hasOne(HasOne $relation, string $relationKey, string $class, \Closure $closure = null)
    {

        if ($this->modelExists()) {

            $formlet = $this->addRelationFormlet($relation, $relationKey, $class);
            $formlet->model($relation->getResults());
        } else {
            $this->addFormlet($relationKey, $class);
        }
    }

    /**
     * HasMany relation
     *
     * @param HasMany       $relation
     * @param string        $relationKey
     * @param string        $class
     * @param \Closure|null $closure
     * @param int           $count
     */
    protected function hasMany(
      HasMany $relation,
      string $relationKey,
      string $class,
      \Closure $closure = null,
      int $count
    ) {

        if ($this->modelExists()) {

            $query = $this->applyRelationScopes($relation, $closure);

            $query->get()->each(function (Model $model) use ($relation, $relationKey, $class) {
                $formlet = $this->addRelationFormlet($relation, $relationKey, $class);
                $formlet->model($model);
            });
        } else {
            $this->addFormlet($relationKey, $class, $count);
        }
    }

    /**
     * BelongsToMany relation
     *
     * @param BelongsToMany $relation
     * @param string        $relationKey
     * @param string        $class
     * @param \Closure|null $closure
     */
    protected function belongsToMany(
      BelongsToMany $relation,
      string $relationKey,
      string $class,
      \Closure $closure = null
    ) {
        // Get subscribed models
        $subscribed = $this->modelExists() ? $relation->getResults() : false;

        $query = $this->applyRelationScopes($relation->getRelated()->newQuery(), $closure);

        foreach ($query->get() as $model) {

            $formlet = $this->addRelationFormlet($relation, $relationKey, $class);

            // Related model can be used in the subscriber formlet
            $formlet->related = $model;

            // Set the model for any subscribed models
            if ($subscribed) {
                $formlet->model($subscribed->firstWhere($model->getKeyName(), $model->getKey()));
            }
        }
    }

    protected function addRelationFormlet(Relation $relation, string $relationKey, string $class)
    {
        $formlet = $this->addFormlet($relationKey, $class);
        $formlet->relation = $relation;
        return $formlet;
    }

    /**
     * Get relation from model
     *
     * @param $method
     * @return Relation
     * @throws LogicException
     */
    protected function getRelationshipFromMethod($method): Relation
    {

        if (!method_exists($this->model, $method)) {

            throw new LogicException(sprintf(
              '%s::%s method does not exist on the model', static::class, $method
            ));
        }

        $relation = $this->model->{$method}();

        if (!$relation instanceof Relation) {

            throw new LogicException(sprintf(
              '%s::%s must return a relationship instance', static::class, $method
            ));
        }

        return $relation;
    }

    /**
     * Does the formlet have pivot columns
     *
     * @return bool
     */
    protected function hasPivotColumns(): bool
    {
        return !is_null($this->relation) && $this->relation instanceof BelongsToMany;
    }

    /**
     * Get the pivot accessor for this formlet
     *
     * @return string
     */
    protected function getPivotAccessor(): string
    {
        return $this->relation->getPivotAccessor();
    }

    /**
     * Apply any scopes provided by the developer
     *
     * @param $query
     * @param $closure
     * @return mixed
     */
    protected function applyRelationScopes($query, $closure)
    {
        if (!is_null($closure)) {
            $closure($query);
        }

        return $query;
    }

}