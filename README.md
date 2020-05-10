# Laravel Translatable  
The package add provides possibility to translate your Eloquent models into different languages.


## Features 
- Simple and intuitive API
- No need to rewrite existing migrations, models or views
- Storing all translations in the single 'translations' table
- Works with model accessors & mutators
- Works with model casts (even with JSON structures)
- Eager loads only needed translations
- Well suitable for already existing projects
- Provides useful events
- Removes translations of deleted models (respecting soft deletes)
- Allows using with models with UUID primary keys


## Demo
```
$post = Book::create(['title' => 'Book about giraffes']);

// Storing translations
app()->setLocale('es')
$book->title = 'Libro sobre jirafas';
$book->save();

// Accessing translations
echo $book->title; // 'Libro sobre jirafas'
app()->setLocale('en');
echo $book->title; // 'Book about giraffes'
```


## Installation
1. Install a package via composer
```
composer require nevadskiy/laravel-translations
```

2. Publish package migrations (it copies only one file into your migrations folder)
```
php artisan vendor:publish --tag=translatable 
```

3. Optional. If you are going to use translations for models with UUID primary keys, replace the line `$table->bigInteger('translatable_id')->unsigned();` with `$table->uuid('translatable_id');`.

4. Run migrate command
```
php artisan migrate
```


## Making models translatable 
1. Add a `HasTranslations` trait to your models which you want to make translatable
```
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Nevadskiy\Translatable\HasTranslations;

class Post extends Model
{
    use HasTranslations;
}
```

2. Add a `$translatable` array to your models with attributes you want to be translatable.
```
/**
 * The attributes that can be translatable.
 *
 * @var array
 */
protected $translatable = [
    'title',
    'description',
];
```

3. Also, make sure to have translatable attributes in the `$fillable` array
```
/**
 * The attributes that are mass assignable.
 *
 * @var array
 */
protected $fillable = [
    'title',
    'description',
];
```

#### Final model may look like this
```
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Nevadskiy\Translatable\HasTranslations;

class Post extends Model
{
    use HasTranslations; 

    protected $guarded = [];

    protected $translatable = [
        'title', 
        'description',
    ];
}
```


## Documentation
Default language values are stored in the original table as usual.

Values in non default languages of every model are stored in the single `translations` table.

The package takes the default language from the `config('config.app.fallback_locale')` value.

##### Manually store and retrieve translations of the model
```
$post = Post::where('title', 'Post about dolphins')->first();

$post->translate('title', 'Пост о дельфинах', 'ru');

echo $post->getTranslation('title', 'ru'); // 'Пост о дельфинах'
```

##### Automatically store and retrieve translations of the model using translatable attributes
```
$post = Post::where('title', 'Post about birds')->first();

app()->setLocale('ru');
$post->title = 'Пост о птицах';
$post->save();

echo $post->title; // 'Пост о птицах'
app()->setLocale('en');
echo $post->title; // 'Post about birds'
```

##### Translatable models creation
Note that translatable models will always be created in **default** locale even when current locale is different.
Any translations can be attached only to **existing** models.  

```
app()->setLocale('de');
Book::create(...); // This will persist model as usual in default locale.
```

##### Displaying collection of models
The package automatically eager loads translations of the current locale for you, so you can easily retrieve collection of models as usual
```
// In controller
app()->setLocale('ru')
$posts = Post::paginate(20);

// In your views
@foreach ($posts as $post)
    {{ $post->title }} // Shows title in the current locale OR in default locale if translation is missing.
@endforeach
```  

##### Translations work with model accessors
```
class Post extends Model
{
    // ...

    public function getTitleAttribute()
    {
        return Str::ucfirst($this->attributes['title']);
    }
}

$post = Post::create(['title' => 'post about birds']);
$post->translate('title', 'пост о птицах', 'ru');

// Using attribute
app()->setLocale('ru');
echo $post->title; // 'Пост о птицах'

// Using getTranslate method
echo $post->getTranslation('title', 'ru'); // 'Пост о птицах'
```

##### Translations work with model mutators as well
```
class Post extends Model
{
    // ...

    public function setDesciptionAttribute($descrition)
    {
        $this->attributes['descrition'] = Str::substr($description, 0, 10);
    }
}

$post = Post::create(['description' => 'Very long description']);
$post->translate('description', 'Очень длинное описание', 'ru');

// Using attribute
app()->setLocale('ru');
echo $post->description; // 'Очень длин'

// Using getTranslation method
echo $post->getTranslation('description', 'ru'); // 'Очень длин'
```

##### Removing unused translations
The package automatically remove translations of deleted models, but if translatable models have been removed using query builder, their translations would exist in the database.
To remove all unused translations, run the `php artisan translatable:remove-unused` command.

##### Querying models without translations
Sometimes you may need to query translatable model without the `translations` relation. You can do this using `withoutTranslations` scope.
```
$books = Book::withoutTranslations()->get();
```

##### Available scopes
Filter models by a translatable attribute, translation and locale.
```
$books = Book::whereTranslatable('title', 'Книга о жирафах', 'ru')->get();
```
