<?php namespace Dimsav\Translatable;

use App;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

trait Translatable
{
    /**
     * Alias for getTranslation()
     *
     * @param LanguageModel|null $languagef
     * @param bool $withFallback
     *
     * @return Model|null
     */
    public function translate(LanguageModel $language = null, $withFallback = false)
    {
        return $this->getTranslation($language, $withFallback);
    }

    /**
     * Alias for getTranslation()
     *
     * @param LanguageModel $language
     *
     * @return Model|null
     * @internal param LanguageModel|string $locale
     */
    public function translateOrDefault(LanguageModel $language)
    {
        return $this->getTranslation($language, true);
    }

    /**
     * Alias for getTranslationOrNew()
     *
     * @param $language
     *
     * @return Model|null
     */
    public function translateOrNew(LanguageModel $language)
    {
        return $this->getTranslationOrNew($language);
    }

    /**
     * @param LanguageModel|null $language
     * @param bool $withFallback
     *
     * @return Model|null
     */
    public function getTranslation(LanguageModel $language = null, $withFallback = null)
    {
        $language     = $language ?: $this->localeLanguage();
        $withFallback = $withFallback === null ? $this->useFallback() : $withFallback;

        if ($translation = $this->getTranslationByLanguage($language)) {
            return $translation;
        }
        if ($withFallback) {
            $fallbackLanguage = $this->getFallbackLanguage();
            if ($fallbackLanguage && $translation = $this->getTranslationByLanguage($fallbackLanguage)) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @param LanguageModel|null $language
     *
     * @return bool
     */
    public function hasTranslation(LanguageModel $language = null)
    {
        $language = $language ?: $this->localeLanguage();

        foreach ($this->translations as $translation) {
            if ($translation->getAttribute($this->getLanguageRelationKey()) == $language->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getTranslationModelName()
    {
        return $this->translationModel ?: $this->getTranslationModelNameDefault();
    }

    /**
     * @return string
     */
    public function getTranslationModelNameDefault()
    {
        $config = App::make('config');

        return get_class($this) . $config->get('translatable.translation_suffix', 'Translation');
    }

    /**
     * @return string
     */
    public function getRelationKey()
    {
        if ($this->translationForeignKey) {
            $key = $this->translationForeignKey;
        } elseif ($this->primaryKey !== 'id') {
            $key = $this->primaryKey;
        } else {
            $key = $this->getForeignKey();
        }

        return $key;
    }

    /**
     * @return mixed
     */
    private function getLanguageRelationKey()
    {
        $translationModelName = $this->getTranslationModelName();
        $translationModel     = new $translationModelName();
        $languageForeginKey   = $translationModel->languageForeginKey ?: $this->getDefaultLanguageRelationKey();

        return $languageForeginKey;
    }

    /**
     * @return mixed
     */
    private function getDefaultLanguageRelationKey()
    {
        return App::make('config')->get('translatable.language_def_foregin_key');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany($this->getTranslationModelName(), $this->getRelationKey());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\belongsToMany
     */
    public function languages()
    {
        return $this->belongsToMany($this->getLanguageModelName(), $this->getTranslationsTable(),
            $this->getRelationKey(), $this->getLanguageRelationKey());
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (str_contains($key, ':')) {
            list($key, $locale) = explode(':', $key);
            $language = $this->getLanguageByCodeKey($locale);
        }

        if ($this->isTranslationAttribute($key)) {
            if ( ! isset($language)) {
                $language = $this->localeLanguage();
            }
            if ($this->getTranslation($language) === null) {
                return;
            }

            return $this->getTranslation($language)->$key;
        }

        return parent::getAttribute($key);
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (str_contains($key, ':')) {
            list($key, $locale) = explode(':', $key);
            $language = $this->getLanguageByCodeKey($locale);
        }

        if ($this->isTranslationAttribute($key)) {
            if ( ! isset($language)) {
                $language = $this->localeLanguage();
            }
            $this->getTranslationOrNew($language)->$key = $value;
        } else {
            return parent::setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * @param array $options
     *
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            if (count($this->getDirty()) > 0) {
                // If $this->exists and dirty, parent::save() has to return true. If not,
                // an error has occurred. Therefore we shouldn't save the translations.
                if (parent::save($options)) {
                    return $this->saveTranslations();
                }

                return false;
            } else {
                // If $this->exists and not dirty, parent::save() skips saving and returns
                // false. So we have to save the translations
                if ($saved = $this->saveTranslations()) {
                    $this->fireModelEvent('saved', false);
                    $this->fireModelEvent('updated', false);
                }

                return $saved;
            }
        } elseif (parent::save($options)) {
            // We save the translations only if the instance is saved in the database.
            return $this->saveTranslations();
        }

        return false;
    }

    /**
     * @param LanguageModel $language
     *
     * @return Model|null
     */
    protected function getTranslationOrNew(LanguageModel $language)
    {
        if (($translation = $this->getTranslation($language, false)) === null) {
            $translation = $this->getNewTranslation($language);
        }

        return $translation;
    }

    /**
     * @param array $attributes
     *
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();
        foreach ($attributes as $key => $values) {
            if ($key === 'translations') {
                foreach ($values as $languageId => $translations) {
                    foreach ($translations as $translationAttribute => $translationValue) {
                        if ($this->alwaysFillable() || $this->isFillable($translationAttribute)) {
                            $languageSkeletor     = $this->getNewLanguageModel();
                            $languageSkeletor->id = $languageId;
                            $translation          = $this->getTranslationOrNew($languageSkeletor);
                            $translation->id;
                            $translation->fill([$translationAttribute => $translationValue]);
                        } elseif ($totallyGuarded) {
                            throw new MassAssignmentException($key);
                        }
                    }
                }
                unset($attributes[$key]);
            }
        }

        return parent::fill($attributes);
    }

    /**
     * @param LanguageModel $language
     */
    private function getTranslationByLanguage(LanguageModel $language)
    {
        foreach ($this->translations as $translation) {
            if ($translation->getAttribute($this->getLanguageRelationKey()) == $language->id) {
                return $translation;
            }
        }

        return;
    }

    /**
     * @return LanguageModel $language
     */
    private function getFallbackLanguage()
    {
        $languageModel = $this->getLanguageModelName();
        $language      = call_user_func_array([$languageModel, 'getFallback'], []);

        return $language;
    }

    /**
     * @return mixed
     */
    private function getLanguageModelName()
    {
        return App::make('config')->get('translatable.languages_model');
    }

    /**
     * @return LanguageModel
     */
    private function getNewLanguageModel()
    {
        $class = $this->getLanguageModelName();

        return new $class;
    }

    /**
     * @return bool|null
     */
    private function useFallback()
    {
        if (isset($this->useTranslationFallback) && $this->useTranslationFallback !== null) {
            return $this->useTranslationFallback;
        }

        return App::make('config')->get('translatable.use_fallback');
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isTranslationAttribute($key)
    {
        return in_array($key, $this->translatedAttributes);
    }

    /**
     * @return bool
     */
    protected function saveTranslations()
    {
        $saved = true;
        foreach ($this->translations as $translation) {
            if ($saved && $this->isTranslationDirty($translation)) {
                $translation->setAttribute($this->getRelationKey(), $this->getKey());
                $saved = $translation->save();
            }
        }

        return $saved;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $translation
     *
     * @return bool
     */
    protected function isTranslationDirty(Model $translation)
    {
        $dirtyAttributes = $translation->getDirty();

        return count($dirtyAttributes) > 0;
    }

    /**
     * @param LanguageModel $language
     *
     * @return Model
     */
    public function getNewTranslation(LanguageModel $language)
    {
        $modelName   = $this->getTranslationModelName();
        $translation = new $modelName();
        $translation->setAttribute($this->getLanguageRelationKey(), $language->id);
        $translation->setAttribute($this->getRelationKey(), $this->id);
        $this->translations->add($translation);

        return $translation;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return ($this->isTranslationAttribute($key) || parent::__isset($key));
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param LanguageModel|null $language
     *
     * @return Builder|static
     */
    public function scopeTranslatedIn(Builder $query, LanguageModel $language = null)
    {
        $language = $language ?: $this->localeLanguage();

        return $query->whereHas('translations', function (Builder $q) use ($language) {
            $q->where($this->getLanguageRelationKey(), '=', $language->id);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param LanguageModel|null $language
     *
     * @return Builder|static
     */
    public function scopeNotTranslatedIn(Builder $query, LanguageModel $language = null)
    {
        $language = $language ?: $this->localeLanguage();

        return $query->whereDoesntHave('translations', function (Builder $q) use ($language) {
            $q->where($this->getLanguageRelationKey(), '=', $language->id);
        });
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function scopeTranslated(Builder $query)
    {
        return $query->has('translations');
    }

    /**
     * Adds scope to get a list of translated attributes, using the current locale.
     *
     * Example usage: Country::listsTranslations('name')->get()->toArray()
     * Will return an array with items:
     *  [
     *      'id' => '1',                // The id of country
     *      'name' => 'Griechenland'    // The translated name
     *  ]
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $translationField
     */
    public function scopeListsTranslations(Builder $query, $translationField)
    {
        $withFallback       = $this->useFallback();
        $translationTable   = $this->getTranslationsTable();
        $languageForeginKey = $this->getLanguageRelationKey();

        $query
            ->select($this->getTable() . '.' . $this->getKeyName(), $translationTable . '.' . $translationField)
            ->leftJoin($translationTable, $translationTable . '.' . $this->getRelationKey(), '=',
                $this->getTable() . '.' . $this->getKeyName())
            ->where($translationTable . '.' . $languageForeginKey, $this->localeLanguage()['id']);
        if ($withFallback) {
            $query->orWhere(function (Builder $q) use ($translationTable, $languageForeginKey) {
                $q->where($translationTable . '.' . $languageForeginKey, $this->getFallbackLanguage()['id'])
                  ->whereNotIn($translationTable . '.' . $this->getRelationKey(),
                      function (QueryBuilder $q) use ($translationTable, $languageForeginKey) {
                          $q->select($translationTable . '.' . $this->getRelationKey())
                            ->from($translationTable)
                            ->where($translationTable . '.' . $languageForeginKey, $this->localeLanguage()['id']);
                      });
            });
        }
    }

    /**
     * This scope eager loads the translations for the default and the fallback locale only.
     * We can use this as a shortcut to improve performance in our application.
     *
     * @param Builder $query
     */
    public function scopeWithTranslation(Builder $query)
    {
        $query->with([
            'translations' => function (Relation $query) {
                $query->where($this->getTranslationsTable() . '.' . $this->getLanguageRelationKey(),
                    $this->localeLanguage()['id']);

                if ($this->useFallback()) {
                    return $query->orWhere($this->getTranslationsTable() . '.' . $this->getLanguageRelationKey(),
                        $this->localeLanguage()['id']);
                }
            }
        ]);
    }

    /**
     * @param Builder $query
     */
    public function scopeWithLangues(Builder $query)
    {
        $query->with('languages');
    }

    /**
     * @param Builder $query
     */
    public function scopeWithLangAndTrans(Builder $query)
    {
        $this->scopeWithLangues($query);
        $this->scopeWithTranslation($query);
    }

    /**
     * This scope filters results by checking the translation fields.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param string $value
     * @param LanguageModel|null $language
     *
     * @return Builder|static
     */
    public function scopeWhereTranslation(Builder $query, $key, $value, LanguageModel $language = null)
    {
        return $query->whereHas('translations', function (Builder $query) use ($key, $value, $language) {
            $query->where($this->getTranslationsTable() . '.' . $key, $value);
            if ($language) {
                $query->where($this->getTranslationsTable() . '.' . $this->getLanguageRelationKey(), $language->id);
            }
        });
    }


    /**
     * This scope filters results by checking the translation fields.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param string $value
     * @param LanguageModel|null $language
     *
     * @return Builder|static
     *
     */
    public function scopeWhereTranslationLike(Builder $query, $key, $value, LanguageModel $language = null)
    {
        return $query->whereHas('translations', function (Builder $query) use ($key, $value, $language) {
            $query->where($this->getTranslationsTable() . '.' . $key, 'LIKE', $value);
            if ($language) {
                $query->where($this->getTranslationsTable() . '.' . $this->getLanguageRelationKey(), 'LIKE',
                    $language->id);
            }
        });
    }


    /**
     * @param bool $force Default skip language model
     *
     * @return array
     */
    public function toArray($force = false)
    {
        $attributes = parent::toArray();

        $hiddenAttributes   = $this->getHidden();
        $langugaesModelName = $this->getLanguageModelName();
        if ( ! $this instanceof $langugaesModelName && ! $force) {
            foreach ($this->translatedAttributes as $field) {
                if (in_array($field, $hiddenAttributes)) {
                    continue;
                }

                if ($translations = $this->getTranslation()) {
                    $attributes[$field] = $translations->$field;
                }
            }
        }

        return $attributes;
    }

    /**
     * @return bool
     */
    private function alwaysFillable()
    {
        return App::make('config')->get('translatable.always_fillable', false);
    }

    /**
     * @return string
     */
    private function getTranslationsTable()
    {
        return App::make($this->getTranslationModelName())->getTable();
    }

    /**
     * @return LanguageModel|null;
     */
    protected function localeLanguage()
    {
        $locale = $this->locale();

        return $this->getLanguageByCodeKey($locale);
    }

    /**
     * @param string $key
     *
     * @return LanguageModel|null;
     */
    protected function getLanguageByCodeKey($key)
    {
        $languageModel = $this->getLanguageModelName();
        $languageCode  = call_user_func_array([$languageModel, 'getCodeColumnName'], []);

        if ( ! isset($this->relations['languages'])) {
            return call_user_func_array([$languageModel, 'getByCodeKey'], [$key]);
        } else {
            foreach ($this->languages as $language) {
                if ($language[$languageCode] === $key) {
                    return $language;
                }
            }
        }

        return null;
    }

    /**
     * @return string
     */
    protected function locale()
    {
        return App::make('config')->get('translatable.locale')
            ?: App::make('translator')->getLocale();
    }
}
