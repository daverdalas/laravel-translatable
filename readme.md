Laravel-Translatable * With dynamic languages
====================


[![Total Downloads](https://poser.pugx.org/dimsav/laravel-translatable/downloads.svg)](https://packagist.org/packages/dimsav/laravel-translatable)
[![Build Status](https://travis-ci.org/dimsav/laravel-translatable.svg?branch=v4.3)](https://travis-ci.org/dimsav/laravel-translatable)
[![Code Coverage](https://scrutinizer-ci.com/g/dimsav/laravel-translatable/badges/coverage.png?s=da6f88287610ff41bbfaf1cd47119f4333040e88)](https://scrutinizer-ci.com/g/dimsav/laravel-translatable/)
[![Latest Stable Version](http://img.shields.io/packagist/v/dimsav/laravel-translatable.svg)](https://packagist.org/packages/dimsav/laravel-translatable)
[![License](https://poser.pugx.org/dimsav/laravel-translatable/license.svg)](https://packagist.org/packages/dimsav/laravel-translatable)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/c105358a-3211-47e8-b662-94aa98d1eeee/mini.png)](https://insight.sensiolabs.com/projects/c105358a-3211-47e8-b662-94aa98d1eeee)

**This package is fork from Dimsav package. I rebuild it in order to use one centralized table with languages data.**

This is a Laravel package for translatable models. Its goal is to remove the complexity in retrieving and storing multilingual model instances. With this package you write less code, as the translations are being fetched/saved when you fetch/save your instance.

If you want to store translations of your models into the database, this package is for you.

* [Demo](#demo)
* [Tutorial](#tutorial)
* [Installation](#installation-in-4-steps)
* [Configuration](#configuration)
* [Documentation](#documentation)
* [Support](#faq)

## Laravel compatibility

 Laravel  | Translatable
:---------|:----------
 5.2      | 5.5
 5.1      | 5.0 - 5.5
 5.0      | 5.0 - 5.4
 4.2.x    | 4.4.x
 4.1.x    | 4.4.x
 4.0.x    | 4.3.x


## Demo

**Getting translated attributes**

```php
$polish = Language::where('code', 'pl')->first();
$poland = Country::where('code', 'pol')->first();

echo $poland->translate($polish)->name // Polska

App::setLocale('en');
echo $poland->name;     // Poland

App::setLocale('de');
echo $poland->name;     // Polen
```

**Saving translated attributes**

```php
$english = Language::where('code', 'en')->first();
$poland = Country::where('code', 'pol')->first();

echo $poland->translate($english)->name; // Poland

$poland->translate($english)->name = 'abc';
$poland->save();

$poland = Country::where('code', 'pol')->first();
echo $poland->translate($english)->name; // abc
```

**Filling multiple translations**

```php
$polish = Language::where('code', 'pl')->first();
$english = Language::where('code', 'en')->first();
$data = [
      'code' => 'grc',
      'translations' => [
          $polish->id => ['name' => 'Grecja'],
          $english->id => ['name' => 'Greece']
      ]
];

$greece = Country::create($data);

echo $greece->translate($english)->name; // Greece
```

## Tutorial

Check the tutorial about laravel-translatable in laravel-news: [*How To Add Multilingual Support to Eloquent*](https://laravel-news.com/2015/09/how-to-add-multilingual-support-to-eloquent/)

## Installation in 4 steps

### Step 1: Install package

Add the package in your composer.json. For now use this syntax:

```bash
"repositories":
[
  {
    "type": "git",
    "url": "https://github.com/daverdalas/laravel-translatable.git"
  }
],
"require": {
  "dimsav/laravel-translatable": "dev-master"
}
```

Next, add the service provider to `app/config/app.php`

```
Dimsav\Translatable\TranslatableServiceProvider::class,
```
### Step 2: Migrations

First create table to store our `languages` data. After that create `countries` and `country_translations tables`.
In this example, we want to translate the model `Country`. We will need an extra table `country_translations`:

```php
Schema::create('languages', function(Blueprint $table) 
{
	$table->increments('id');
	$table->string('code');
	$table->timestamps();
});

Schema::create('countries', function(Blueprint $table)
{
    $table->increments('id');
    $table->string('code')->unique();
    $table->timestamps();
});

Schema::create('country_translations', function(Blueprint $table)
{
    $table->increments('id');
    $table->string('name');
    $table->integer('language_id')->unsigned();
    $table->integer('country_id')->unsigned();

    $table->foreign('language_id')->references('id')->on('languages');
    $table->foreign('country_id')->references('id')->on('countries');
});
```

### Step 3: Models

1. The model representing language in this example `Language` must extend `\Dimsav\Translatable\LanguageModel` class.
2. The translatable model `Country` should [use the trait](http://www.sitepoint.com/using-traits-in-php-5-4/) `Dimsav\Translatable\Translatable`. 
3. The convention for the translation model is `CountryTranslation`.


```php
// models/Language.php
class Language extends \Dimsav\Translatable\LanguageModel{

}

// models/Country.php
class Country extends Eloquent {
    
    use \Dimsav\Translatable\Translatable;
    
    public $translatedAttributes = ['name'];
    protected $fillable = ['code', 'name'];
    
    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    // (optionaly)
    // protected $with = ['translations'];

}

// models/CountryTranslation.php
class CountryTranslation extends Eloquent {

    public $timestamps = false;
    protected $fillable = ['name'];
    // (optionaly) Default retrieved from the config file
    // protected $languageForeginKey = ['language_custom_id'];

}
```

The array `$translatedAttributes` contains the names of the fields being translated in the "Translation" model.

### Step 4: Configuration

Laravel 5.*
```bash
php artisan vendor:publish 
```

With this command, initialize the configuration and modify the created file, located under `app/config/packages/dimsav/laravel-translatable/translatable.php`.

**In this fork there are two new variables added in configuration file:**
```bash
/*
|--------------------------------------------------------------------------
| Languages Model
|--------------------------------------------------------------------------
|
| Points to class representing languages
|
*/
'languages_model' => App\Language::class,

/*
|--------------------------------------------------------------------------
| Language default foregin key
|--------------------------------------------------------------------------
|
| Default name of the foregin language key.
| It can be overwrite by public $languageForeginKey in 
| a class representing translations.
|
*/
'language_def_foregin_key' => 'language_id'
```
Some not used variables have been removed.

## Configuration

### The translation model

The convention used to define the class of the translation model is to append the keyword `Translation`.

So if your model is `\MyApp\Models\Country`, the default translation would be `\MyApp\Models\CountryTranslation`.

To use a custom class as translation model, define the translation class (including the namespace) as parameter. For example:

```php
<?php 

namespace MyApp\Models;

use Dimsav\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Country extends Eloquent
{
    use Translatable;

    public $translationModel = 'MyApp\Models\CountryAwesomeTranslation';
}

```

## Documentation

**Please read the installation steps first, to understand what classes need to be created.**

### Available methods 

```php
// Before we get started, this is how we determine the current locale.
// It is set by laravel or other packages.
App::getLocale(); // 'en' 

// To use this package, first we need an instance of our model
$poland = Country::where('code', 'pol')->first();

//and language that we going to use in the examples.
$german = Language::where('code', 'de')->first();

// This returns an instance of CountryTranslation of using the current locale.
// So in this case, english. If no english translation is found, it returns null.
$translation = $poland->translate();

// If an german translation exists, it returns an instance of 
// CountryTranslation. Otherwise it returns null.
$translation = $poland->translate($german);

// If a german translation doesn't exist, it attempts to get a translation  
// of the fallback language (see fallback locale section below).
$translation = $poland->translate($german, true);

// Alias of the above.
$translation = $poland->translateOrDefault($german);

// Returns instance of CountryTranslation of using the current locale.
// If no translation is found, it returns a fallback translation
// if enabled in the configuration.
$translation = $poland->getTranslation();

// If an german translation exists, it returns an instance of 
// CountryTranslation. Otherwise it returns null.
// Same as $poland->translate($german);
$translation = $poland->getTranslation($german, true);

// Returns true/false if the model has translation about the current locale. 
$poland->hasTranslation();

// Returns true/false if the model has translation in french. 
$poland->hasTranslation($german);

// If a german translation doesn't exist, it returns
// a new instance of CountryTranslation.
$translation = $poland->translateOrNew($german);

// Returns a new CountryTranslation instance for the selected
// language, and binds it to $poland
$translation = $poland->getNewTranslation($german);

// The eloquent model relationship. Do what you want with it ;) 
$poland->translations();
```

### Available scopes

```php
// Returns all countries having translations in german
Country::translatedIn($german)->get();

// Returns all countries not being translated in german
Country::notTranslatedIn($german)->get();

// Returns all countries having translations
Country::translated()->get();

// Eager loads translation relationship only for the default
// and fallback (if enabled) locale
Country::withTranslation()->get();

// Eager loads languages relationship
Country::withLangues()->get();

// Eager load languages and translation
Country::withLangAndTrans()->get();

// Returns an array containing pairs of country ids and the translated
// name attribute. For example: 
// [
//     ['id' => 1, 'name' => 'Greece'], 
//     ['id' => 2, 'name' => 'Belgium']
// ]
Country::listsTranslations('name')->get()->toArray();

// Filters countries by checking the translation against the given value 
Country::whereTranslation('name', 'Poland')->first();

// We can also use langauge Model to specify the language
$english = Language::where('code', 'en')->first();
Country::whereTranslation('name', 'Poland', $english)->first();

// Filters countries by checking the translation against the given string with wildcards
Country::whereTranslationLike('name', '%Pol%')->first();

// Sorts countries according to the given column in translations table. Default order is DESC
Country::orderByTranslation('name', 'ASC')->first();
```

### Magic properties

To use the magic properties, you have to define the property `$translatedAttributes` in your
 main model:

 ```php
 class Country extends Eloquent {

     use \Dimsav\Translatable\Translatable;

     public $translatedAttributes = ['name'];
 }
 ```

```php
// Again we start by having a country instance
$poland = Country::where('code', 'pol')->first();

// We can reference properties of the translation object directly from our main model.
// This uses the default locale and is the equivalent of $germany->translate()->name
$poland->name; // 'Poland'

// We can also quick access a translation with a custom locale
$poland->{'name:de'} // 'Polen'
```

### Fallback locales

If you want to fallback to a default translation when a translation has not been found, enable this in the configuration
using the `use_fallback` key. And to select the default locale, use the `fallback_locale` key.

Configuration example:

```php
return [
    'use_fallback' => true,

    'fallback_locale' => 'en',    
];
```

You can also define *per-model* the default for "if fallback should be used", by setting the `$useTranslationFallback` property:

```php
class Country {

    public $useTranslationFallback = true;

}
```
#### Add ons

Thanks to the community a few packages have been written to make usage of Translatable easier when working with forms:

- [Propaganistas/Laravel-Translatable-Bootforms](https://github.com/Propaganistas/Laravel-Translatable-Bootforms)
- [TypiCMS/TranslatableBootForms](https://github.com/TypiCMS/TranslatableBootForms)
 
## FAQ

#### I need some example code!

Examples for all the package features can be found [in the code](https://github.com/dimsav/laravel-translatable/tree/master/tests/models) used for the [tests](https://github.com/dimsav/laravel-translatable/tree/master/tests).

#### I need help!

Got any question or suggestion? Feel free to open an [Issue](https://github.com/dimsav/laravel-translatable/issues/new).

#### I want to help!

You are awesome! Watched the repo and reply to the issues. You will help offering a great experience to the users of the package. `#communityWorks`

#### I am getting collisions with other trait methods!

Translatable is fully compatible with all kinds of Eloquent extensions, including Ardent. If you need help to implement Translatable with these extensions, see this [example](https://gist.github.com/dimsav/9659552).

#### How can I select a country by a translated field?

For example, let's image we want to find the Country having a CountryTranslation name equal to 'Portugal'.

```php
Country::whereHas('translations', function ($query) {
    $query->where('locale', 'en')
    ->where('name', 'Portugal');
})->first();
```

You can find more info at the Laravel [Querying Relations docs](http://laravel.com/docs/5.1/eloquent-relationships#querying-relations).

#### Why do I get a mysql error while running the migrations?

If you see the following mysql error:

```
[Illuminate\Database\QueryException]
SQLSTATE[HY000]: General error: 1005 Can't create table 'my_database.#sql-455_63'
  (errno: 150) (SQL: alter table `country_translations` 
  add constraint country_translations_country_id_foreign foreign key (`country_id`) 
  references `countries` (`id`) on delete cascade)
```

Then your tables have the MyISAM engine which doesn't allow foreign key constraints. MyISAM was the default engine for mysql versions older than 5.5. Since [version 5.5](http://dev.mysql.com/doc/refman/5.5/en/innodb-default-se.html), tables are created using the InnoDB storage engine by default.

##### How to fix

For tables already created in production, update your migrations to change the engine of the table before adding the foreign key constraint.

```php
public function up()
{
    DB::statement('ALTER TABLE countries ENGINE=InnoDB');
}

public function down()
{
    DB::statement('ALTER TABLE countries ENGINE=MyISAM');
}
```

For new tables, a quick solution is to set the storage engine in the migration:

```php
Schema::create('language_translations', function(Blueprint $table){
  $table->engine = 'InnoDB';
  $table->increments('id');
    // ...
});
```

The best solution though would be to update your mysql version. And **always make sure you have the same version both in development and production environment!**
