<?php namespace Dimsav\Translatable;
use App;
use Illuminate\Database\Eloquent\Model;

class LanguageModel extends Model
{
    const codeColumnName = 'code';

    /**
     * @return self|null
     */
    public static function getFallback(){
        $languageFallbackCode = self::getFallbackCode();
        $query = self::getByCodeKey($languageFallbackCode);
        return $query->first();
    }

    /**
     * @param $key
     * @return mixed
     */
    public static function getByCodeKey($key){
        $query = self::where(self::codeColumnName, $key);
        return $query->first();
    }

    /**
     * @return mixed
     */
    private static function getFallbackCode()
    {
        return App::make('config')->get('translatable.fallback_locale');
    }
}