<?php

namespace Nevadskiy\Translatable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Nevadskiy\Translatable\Events\TranslationNotFound;
use Nevadskiy\Translatable\Exceptions\NotTranslatableAttributeException;
use Nevadskiy\Translatable\Models\Translation;
use Nevadskiy\Translatable\Scopes\TranslationsEagerLoadScope;

/**
 * @mixin Model
 * @property Collection|Translation[] translations
 */
trait HasTranslations
{
    use TranslationScopes,
        TranslatableUrlRouting,
        ArchivingTranslations;

    /**
     * Prepared translations to be saved into the database.
     *
     * @var array
     */
    protected $preparedTranslations = [];

    /**
     * Resolved attribute translations from the database.
     *
     * @var array
     */
    protected $resolvedTranslations = [];

    /**
     * Boot the trait.
     */
    protected static function bootHasTranslations(): void
    {
        static::addGlobalScope(new TranslationsEagerLoadScope());

        static::saving(static function (self $translatable) {
            $translatable->handleSavingEvent();
        });

        static::deleted(static function (self $translatable) {
            $translatable->handleDeletedEvent();
        });
    }

    /**
     * Morph many translations relation.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $attribute
     * @return mixed
     */
    public function getAttribute($attribute)
    {
        if (! $this->shouldBeTranslated($attribute)) {
            return $this->getDefaultTranslation($attribute);
        }

        if (! $this->autoLoadTranslations($attribute)) {
            return $this->getDefaultTranslation($attribute);
        }

        return $this->getTranslationOrDefault($attribute);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $attribute
     * @param mixed $value
     * @return mixed
     */
    public function setAttribute($attribute, $value)
    {
        if (! $this->shouldBeTranslated($attribute)) {
            return $this->setDefaultAttribute($attribute, $value);
        }

        if (! $this->autoSaveTranslations($attribute)) {
            return $this->setDefaultAttribute($attribute, $value);
        }

        return $this->setTranslation($attribute, $value);
    }

    /**
     * Get attribute's default value without translation.
     *
     * @return mixed
     */
    public function getDefaultTranslation(string $attribute)
    {
        return parent::getAttribute($attribute);
    }

    /**
     * Set attribute's value without translation.
     *
     * @param mixed $value
     * @return mixed
     */
    public function setDefaultAttribute(string $attribute, $value)
    {
        return parent::setAttribute($attribute, $value);
    }

    /**
     * Determine if the model should automatically load translations on attribute get.
     */
    public function autoLoadTranslations(string $attribute): bool
    {
        return resolve(Translatable::class)->shouldAutoLoadTranslations();
    }

    /**
     * Determine if the model should automatically save translations on attribute set.
     */
    public function autoSaveTranslations(string $attribute): bool
    {
        return resolve(Translatable::class)->shouldAutoSaveTranslations();
    }

    /**
     * Save translation for the given attribute and locale.
     *
     * @param mixed $value
     * @return HasTranslations|mixed
     */
    public function translate(string $attribute, $value, string $locale)
    {
        $this->setTranslation($attribute, $value, $locale);
        $this->save();

        return $this;
    }

    /**
     * Save many translations for the given attribute and locale.
     *
     * @return HasTranslations
     */
    public function translateMany(array $translations, string $locale)
    {
        foreach ($translations as $attribute => $value) {
            $this->setTranslation($attribute, $value, $locale);
        }

        $this->save();

        return $this;
    }

    /**
     * Get translation value for the attribute.
     *
     * @return mixed
     */
    public function getTranslation(string $attribute, string $locale = null)
    {
        $this->assertTranslatableAttribute($attribute);

        $locale = $locale ?: static::getTranslator()->getLocale();

        if (static::getTranslator()->isDefaultLocale($locale)) {
            return $this->getDefaultTranslation($attribute);
        }

        $rawTranslation = $this->getRawTranslation($attribute, $locale);

        if (is_null($rawTranslation)) {
            return null;
        }

        return $this->withAttributeAccessors($attribute, $rawTranslation);
    }

    /**
     * Get raw translation value for the attribute.
     *
     * @return mixed
     */
    public function getRawTranslation(string $attribute, string $locale = null)
    {
        $locale = $locale ?: static::getTranslator()->getLocale();

        if (! $this->hasResolvedTranslation($attribute, $locale)) {
            $this->resolveTranslation($attribute, $locale);
        }

        $translation = $this->getResolvedTranslation($attribute, $locale);

        if (is_null($translation)) {
            event(new TranslationNotFound($this, $attribute, $locale));
        }

        return $translation;
    }

    /**
     * Determine whether the attribute has resolved translation according to the given locale.
     */
    protected function hasResolvedTranslation(string $attribute, string $locale): bool
    {
        return isset($this->resolvedTranslations[$locale][$attribute]);
    }

    /**
     * Set the given value as the resolved attribute translation.
     */
    protected function setResolvedTranslation(string $attribute, string $locale, $value): void
    {
        $this->resolvedTranslations[$locale][$attribute] = $value;
    }

    /**
     * Get the loaded attribute translation.
     *
     * @return mixed
     */
    protected function getResolvedTranslation(string $attribute, string $locale)
    {
        return $this->resolvedTranslations[$locale][$attribute];
    }

    /**
     * Resolve a translation for the given attribute and locale.
     */
    protected function resolveTranslation(string $attribute, string $locale): void
    {
        $this->setResolvedTranslation($attribute, $locale, static::getTranslator()->get($this, $attribute, $locale));
    }

    /**
     * Determine whether the model has same resolved translation.
     *
     * @param $value
     */
    protected function hasSameResolvedTranslation(string $attribute, string $locale, $value): bool
    {
        return $this->hasResolvedTranslation($attribute, $locale)
            && $this->getResolvedTranslation($attribute, $locale) === $value;
    }

    /**
     * Prepare translation to be stored in the database.
     *
     * @return HasTranslations|mixed
     */
    protected function prepareTranslation(string $attribute, string $locale, $value)
    {
        if ($this->hasSameResolvedTranslation($attribute, $locale, $value)) {
            return $this;
        }

        $this->preparedTranslations[$locale][$attribute] = $value;
        $this->setResolvedTranslation($attribute, $locale, $value);

        return $this;
    }

    /**
     * Pull any prepared translations.
     */
    protected function pullPreparedTranslations(): array
    {
        $translations = $this->preparedTranslations;

        $this->preparedTranslations = [];

        return $translations;
    }

    /**
     * Set translation to the attribute.
     *
     * @param mixed $value
     * @return HasTranslations|mixed
     */
    public function setTranslation(string $attribute, $value, string $locale = null)
    {
        $this->assertTranslatableAttribute($attribute);

        $locale = $locale ?: static::getTranslator()->getLocale();

        if (static::getTranslator()->isDefaultLocale($locale)) {
            return $this->setDefaultAttribute($attribute, $value);
        }

        return $this->prepareTranslation($attribute, $locale, $this->withAttributeMutators($attribute, $value));
    }

    /**
     * Determine whether the attribute should be translated.
     */
    protected function shouldBeTranslated(string $attribute): bool
    {
        return $this->exists && $this->isTranslatable($attribute);
    }

    /**
     * Determine whether the attribute is translatable.
     */
    protected function isTranslatable(string $attribute): bool
    {
        return in_array($attribute, $this->getTranslatable(), true);
    }

    /**
     * Get translatable attributes.
     */
    public function getTranslatable(): array
    {
        return $this->translatable ?? [];
    }

    /**
     * Handle the model saving event.
     */
    protected function handleSavingEvent(): void
    {
        $this->savePreparedTranslations();
    }

    /**
     * Save the model translations.
     */
    protected function savePreparedTranslations(): void
    {
        static::getTranslator()->save($this, $this->pullPreparedTranslations());
    }

    /**
     * Handle the model deleted event.
     */
    protected function handleDeletedEvent(): void
    {
        if ($this->shouldDeleteTranslations()) {
            $this->deleteTranslations();
        }
    }

    /**
     * Determine whether the model should delete translations.
     */
    protected function shouldDeleteTranslations(): bool
    {
        if (! $this->isUsingSoftDeletes()) {
            return true;
        }

        if ($this->isForceDeleting()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the model uses soft deletes.
     */
    protected function isUsingSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this), true);
    }

    /**
     * Delete the model translations.
     */
    protected function deleteTranslations(): void
    {
        $this->translations()->delete();
    }

    /**
     * Get a translation of the attribute or default value if translation is missing.
     *
     * @return mixed
     */
    public function getTranslationOrDefault(string $attribute, string $locale = null)
    {
        $translation = $this->getTranslation($attribute, $locale);

        if (is_null($translation)) {
            return $this->getDefaultTranslation($attribute);
        }

        return $translation;
    }

    /**
     * Get the attribute value with all accessors and casts applied.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function withAttributeAccessors(string $attribute, $value)
    {
        $original = $this->attributes[$attribute];

        $this->attributes[$attribute] = $value;

        $processed = parent::getAttribute($attribute);

        $this->attributes[$attribute] = $original;

        return $processed;
    }

    /**
     * Get the attribute value with all mutators and casts applied.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function withAttributeMutators(string $attribute, $value)
    {
        $original = $this->attributes[$attribute];

        parent::setAttribute($attribute, $value);

        $processed = $this->attributes[$attribute];

        $this->attributes[$attribute] = $original;

        return $processed;
    }

    /**
     * Get the model translator instance.
     */
    protected static function getTranslator(): ModelTranslator
    {
        return app(ModelTranslator::class);
    }

    /**
     * Assert that attribute is translatable.
     */
    protected function assertTranslatableAttribute(string $attribute): void
    {
        if (! $this->isTranslatable($attribute)) {
            throw NotTranslatableAttributeException::fromAttribute($attribute);
        }
    }

    /**
     * Convert the model's attributes to an array.
     */
    public function attributesToArray(): array
    {
        return array_merge(parent::attributesToArray(), $this->getTranslations());
    }

    /**
     * Get model translations.
     */
    public function getTranslations(string $locale = null): array
    {
        $locale = $locale ?: static::getTranslator()->getLocale();

        $translations = [];

        foreach ($this->getTranslatable() as $attribute) {
            $translations[$attribute] = $this->getTranslationOrDefault($attribute, $locale);
        }

        return $translations;
    }
}
