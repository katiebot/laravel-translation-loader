<?php

namespace Spatie\TranslationLoader;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class LanguageLine extends Model
{
    /** @var array */
    public $translatable = ['text'];

    /** @var array */
    public $guarded = ['id'];

    /** @var array */
    protected $casts = ['text' => 'array'];

    public static function boot()
    {
        parent::boot();
        static::saved(function (LanguageLine $languageLine) {
            $languageLine->flushGroupCache();
        });

        static::deleted(function (LanguageLine $languageLine) {
            $languageLine->flushGroupCache();
        });
    }

    public static function getTranslationsForGroup($locale, $group)
    {
        return Cache::rememberForever(static::getCacheKey($group, $locale), function () use ($group, $locale) {
            return static::query()
                         ->where('group', $group)
                         ->get()
                         ->map(function (LanguageLine $languageLine) use ($locale) {
                             return [
                                 'key' => $languageLine->key,
                                 'text' => $languageLine->getTranslation($locale),
                             ];
                         })
                         ->pluck('text', 'key')
                         ->toArray();
        });
    }

    public static function getCacheKey($group, $locale)
    {
        return "spatie.translation-loader.{$group}.{$locale}";
    }

    /**
     * @param string $locale
     *
     * @return string
     */
    public function getTranslation($locale)
    {
        if(! isset($this->text[$locale])) {
            $fallback = config('app.fallback_locale');

            return isset($this->text[$fallback]) ? $this->text[$fallback] : null;
        }

        return $this->text[$locale];
    }

    /**
     * @param string $locale
     * @param string $value
     *
     * @return $this
     */
    public function setTranslation($locale, $value)
    {
        $this->text = array_merge(isset($this->text) ? $this->text : [], [$locale => $value]);

        return $this;
    }

    protected function flushGroupCache()
    {
        foreach ($this->getTranslatedLocales() as $locale) {
            Cache::forget(static::getCacheKey($this->group, $locale));
        }
    }

    protected function getTranslatedLocales()
    {
        return array_keys($this->text);
    }
}
