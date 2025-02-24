<?php

namespace Nevadskiy\Translatable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Scope;
use Nevadskiy\Translatable\HasTranslations;

class TranslationsEagerLoadScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Model|HasTranslations $translatable
     */
    public function apply(Builder $query, Model $translatable): void
    {
        if ($translatable::getTranslator()->isDefaultLocale()) {
            return;
        }

        if (! $this->shouldLoadTranslations($translatable)) {
            return;
        }

        $query->with(['translations' => static function (MorphMany $query) use ($translatable) {
            $query->forLocale($translatable::getTranslator()->getLocale());
        }]);
    }

    /**
     * Determine whether the translations should be loaded.
     *
     * @param Model|HasTranslations $translatable
     */
    private function shouldLoadTranslations(Model $translatable): bool
    {
        foreach ($translatable->getTranslatable() as $attribute) {
            if ($translatable->autoLoadTranslations($attribute)) {
                return true;
            }
        }

        return false;
    }
}
