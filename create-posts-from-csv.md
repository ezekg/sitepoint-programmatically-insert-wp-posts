# Programmatically creating WordPress posts from a CSV

As WordPress developers, we often times encounter projects that need to include previously attained data; whether that be from simple text files, CSV files, or even an old database. Data migration is something any back-end developer will encounter. A few months back, we had a project that needed nearly 1,000 posts to be generated from a plethora CSV files. Now, usually this wouldn't be _that_ hard; but, this data also needed to be under its own post type, and that custom post type had a few custom fields, including a media attachment for an MP3 file.

I won't bore you with the code for creating custom post types and custom fields, because there's already [a]() [ton]() [of]() [articles]() [floating]() [around]() [the]() [web]() [on]() [that]() [subject](). I'll just mention that I am using [Custom Post Type UI]() and [Advanced Custom Fields]() for each respective task. As the title suggests, what we're going to be covering here is programmatically taking data from a bunch of CSV files (one for each post), and then turning that data into WordPress posts for a custom post type. We'll even go over attaching an MP3 media file to each post.

1. http://www.sitepoint.com/getting-started-with-advanced-custom-fields/
1. http://www.sitepoint.com/acf-flexible-content-fields/
1. http://www.sitepoint.com/custom-post-types-take-wordpress-beyond-blogging/
1. http://www.sitepoint.com/creating-custom-post-types-in-wordpress/
1. http://www.sitepoint.com/custom-wordpress-taxonomies/

In order to get all the data we need from the CSV files, we'll be making use of a few nifty PHP functions, such as: [`glob()`](http://php.net/manual/en/function.glob.php), which 'globs' a directory and returns an array of filenames within it; [`fopen()`](http://php.net/manual/en/function.fopen.php), which opens up a file so that we can read its contents; and finally, [`fgetcsv()`](http://php.net/manual/en/function.fgetcsv.php), which parses a CSV file into a nice associative array housing all our data.

In reality, most of the data we'll be using for this article would probably be inside of a single CSV, as opposed to how we're going to be doing it today where the data is scattered throughout multiple files. This is done so that the techniques used here can be implemented using other types of data, such as JSON, Yaml, or even plain text files. The idea for this whole article came from the severe lack of tutorials and articles concerning this subject, especially when you're custom post types and custom fields.

## The Data

Before we really get going, let's take a look at the data we're going to be dealing with. If you want to follow along, you can grab the needed CSV files (and all of the code used in this article, if you want) from [this repo]().

![Example of CSV data](/img.jpg)

## The Code

```php
$this->is_sparta;
```

It's worth mentioning that the code used in this article requires at least **PHP 5.3**. We'll be making use of [anonymous functions](http://php.net/manual/en/functions.anonymous.php), as well as `fgetcsv()`, both of which require 5.3; so, before you go off and use this on an old rickety production server _(please, don't do that)_, [you might want to upgrade](http://www.sitepoint.com/legacy-code-cancer/). I'm also not going to get into PHP's [`max_execution_time`](http://php.net/manual/en/info.configuration.php#ini.max-execution-time), which can cause some issues with inserting a large amount of posts in one go; the setting varies so much from server to server that it's not feasible to discuss it in this article.