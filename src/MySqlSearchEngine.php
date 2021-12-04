<?php

namespace Firebed\Scout;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class MySqlSearchEngine extends Engine
{
    private DatabaseManager $database;
    private UnicodeTokenizer $tokenizer; 
    private FullTextQuery $fullText;

    public function __construct(DatabaseManager $database, UnicodeTokenizer $tokenizer, FullTextQuery $fullText)
    {
        $this->database = $database;
        $this->tokenizer = $tokenizer;
        $this->fullText = $fullText;
    }

    public function update($models): void
    {
        $config = Container::getInstance()->make('config');
        if ($config->get('scout.soft_delete', false) && $this->usesSoftDelete($models->first())) {
            $models->each->pushSoftDeleteMetadata();
        }

        $models->map(function ($model) {
            $terms = array_merge($model->toSearchableArray(), $model->scoutMetadata());
            
            $terms = implode(' ', $terms);
            
            $tokens = $this->tokenizer->tokenize($terms);
            $tokens = $this->fullText->prepareForIndex($tokens);
            
            if (empty($tokens)) {
                return null;
            }

            return [
                'objectID' => $model->getKey(),
                'index'    => $model->searchableAs(),
                'keywords' => implode(' ', $tokens),
            ];
        })
            ->filter()
            ->each(function ($record) {
                $attributes = [
                    'index'    => $record['index'],
                    'objectID' => $record['objectID'],
                ];
                $this->query()->updateOrInsert($attributes, $record);
            });
    }

    public function delete($models): void
    {
        $index = $models->first()->searchableAs();

        $ids = $models->map(fn($model) => $model->getKey())->values()->all();
        
        $this->query()
            ->where('index', $index)
            ->whereIn('objectID', $ids)
            ->delete();
    }

    public function search(Builder $builder): array
    {
        $results = $this->performSearch($builder)->get();

        return ['results' => $results, 'total' => count($results)];
    }

    public function paginate(Builder $builder, $perPage, $page): LengthAwarePaginator
    {
        $results = $this->performSearch($builder)
            ->forPage($page, $perPage)
            ->get();
     
        $total = $this->performSearch($builder)->count();

        return new LengthAwarePaginator($results, $total, $perPage, $page);
    }

    public function map(Builder $builder, $results, $model): Collection
    {
        if ($results['total'] === 0) {
            return Collection::make();
        }

        $keys = $this->mapIds($results);

        $query = $model->whereIn($model->getQualifiedKeyName(), $keys);

        if ($this->usesSoftDelete($model)) {
            $query = $query->withTrashed();
        }

        $models = $query->get()->keyBy($model->getKeyName());

        return Collection::make($results['results'])
            ->map(fn($record) => $models[$record->objectID] ?? null)->filter();
    }

    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if ($results['total'] === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = $this->mapIds($results);

        $objectIdPositions = $objectIds->flip();

        return $model->queryScoutModelsByIds(
            $builder, $objectIds
        )->cursor()->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds->all(), true);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * @throws Exception
     */
    public function createIndex($name, array $options = [])
    {
        throw new Exception('Database indexes are created automatically upon adding objects.');
    }

    public function deleteIndex($name): void
    {
        $this->query()->where('index', $name)->delete();
    }

    public function getTotalCount($results): int
    {
        return $results['total'];
    }

    public function flush($model): void
    {
        $index = $model->searchableAs();

        $this->query()
            ->where('index', $index)
            ->delete();
    }

    public function mapIds($results): Collection
    {
        return $results['results']->pluck('objectID')->values();
    }

    protected function query(): \Illuminate\Database\Query\Builder
    {
        return $this->database->table('scout_index');
    }

    protected function performSearch(Builder $builder, array $options = [])
    {
        $index = $builder->index ?: $builder->model->searchableAs();

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->query(),
                $builder->query,
                $options
            );
        }

        $tokens = $this->tokenizer->tokenize($builder->query);
        $tokens = $this->fullText->prepareForSearch($tokens);
        $tokens = implode(' ', $tokens);

        return $this->query()
            ->select('objectID')
            ->where('index', '=', $index)
            ->whereRaw("MATCH(`keywords`) AGAINST (? IN BOOLEAN MODE)", $tokens)
            ->when($builder->limit, fn($q, $limit) => $q->take($limit));
    }

    protected function usesSoftDelete($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }
}
