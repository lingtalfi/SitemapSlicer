Sitemap Slicer
=====================
2015-10-11



Generate a sitemap index and its related sitemaps using data from your database.



Features
-------------


- handles the slicing of your entries into multiple sitemaps
- supports Google video, image and mobile sitemap extensions



The problem
----------------

You own a dynamic website (where people can post things), and you want to generate a sitemap for your website.



The solution
----------------

The Sitemap Slicer might help you.
Basically, it reads data from your database, turns it into sitemaps, and creates the corresponding sitemap index.<br>
We can specify how many sitemap entries max we want per sitemap (it's called the sliceWidth).


One possible usage of the Sitemap Slicer is to create a script that recreates the whole sitemap of your application,
and you call this script every day at 3:00am for instance (using a cron job).

   
How many entries per sitemap (use the slices)?
-------------------------

One benefit of using the Sitemap Slicer is that it let us decide how many entries we want per sitemap.


Let's say that you have 3 tables in your application, called t1, t2 and t3 and from which you want to generate sitemaps.

- t1 contains 12000 entries
- t2 contains 3000 entries
- t3 contains 24000 entries

We could set a **sliceWidth** (how many entries max per sitemap) of 50000, then we would end up with a big sitemap file containing the entries from all the tables
t1, t2 and t3.

We could also set a sliceWidth of 10000, then you would end up with the following files (by default):

- sitemap.xml    (containing 10000 entries from t1)
- sitemap2.xml   (containing 10000 entries: 2000 entries from t1, 3000 from t2 and 5000 from t3)
- sitemap3.xml   (containing 10000 entries from t3)
- sitemap4.xml   (containing 9000 entries from t3)


We can also decide to map tables t1 and t2 to a sitemap.xml file, and table t3 to a video.sitemap.xml file.
With a sliceWidth of 10000, the Sitemap Slicer would create the following files:

- sitemap.xml           (containing 10000 entries from t1)
- sitemap2.xml          (containing 5000 entries: 2000 entries from t1, 3000 entries from t2)
- video.sitemap.xml     (containing 10000 entries from t3)
- video.sitemap2.xml    (containing 10000 entries from t3)
- video.sitemap3.xml    (containing 4000 entries from t3)


So, with the Sitemap Slicer, we have that kind of control.


Note: in all above examples, the corresponding **sitemap index** file is generated automatically


  
  
  
Let's now dive into examples.
You should read at least the first example (if you're interested), which explains things in details.
The other example is just a variation of the first example.
  
  

Example 1: convert one table into one sitemap
-------------------------

Actually, the title should be: 
    Example 1: convert one table into one base sitemap
    
    
### What's a base sitemap?

A base sitemap is a sitemap (file), except that if you store too many entries (defined by the sliceWidth) in your base sitemap,
the Sitemap Slicer automatically divides it into many sitemaps files.

Actually, we've already seen an example of this in the previous sections: sitemap.xml is the base sitemap after which other sitemaps 
were named: sitemap2.xml, sitemap3.xml, ... 



    
    
That said, let's now show the first example's code.


```php
<?php


use QuickPdo\QuickPdo;
use SitemapBuilderBox\Objects\Url;
use SitemapSlicer\SitemapIndexSlicer\AuthorSitemapIndexSlicer;
use SitemapSlicer\SitemapSlice\AuthorSitemapSlice;
use SitemapSlicer\TableBindure\AuthorTableBindure;



require_once "bigbang.php"; // this is the famous bigbang oneliner




// this is the sliceWidth
$n = 10000;

// this is the pdo connection that I use in this example application
QuickPdo::setConnection(
    "mysql:dbname=sketch;host=127.0.0.1",
    'root',
    'root',
    array(
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
        PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    )
);


AuthorSitemapIndexSlicer::create()
    ->onWarning(function ($msg) {
        // log to the system (you probably don't want to interrupt the script with an Exception)
        a($msg); // a function comes from the bigbang script
    })
    ->file(__DIR__ . '/sitemap.index.xml')
    ->url(function ($fileName) {
        return 'http://mysite.com/' . basename($fileName);
    })
    ->defaultSliceWidth($n)
    ->addSitemapSlice(AuthorSitemapSlice::create()
            ->sliceWidth($n)
            ->file('idea.sitemap{n}.xml')
            ->addTableBindure(AuthorTableBindure::create()
                    ->setCountCallback(function () {
                        $stmt = <<<MMM
select count(*) as count from mecas where active=1
MMM;
                        if (false !== ($row = QuickPdo::fetch($stmt))) {
                            return $row['count'];
                        }
                        return false; // will trigger an error
                    })
                    ->setRowsCallback(function ($offset, $nbItems) { // gets repeated as long as necessary
                        $stmt = <<<FFF
select * from mecas where active=1 order by id asc limit $offset, $nbItems       
FFF;
                        return QuickPdo::fetchAll($stmt);
                    })
                    ->setConvertToSitemapEntryCallback(function (array $row) {
                        $d = new DateTime($row['publish_date']);

                        return Url::create()
//                            ->setLoc(Router::getDynamicUri(URLSPACE_MECA, $row['the_name'], true))
                            ->setLoc('http://sketch/meca/' . $row['the_name'])
                            ->setLastmod($d->format(\DateTime::ISO8601))
                            ->setChangefreq('monthly');
                    })
            )
    )
    ->execute();
```



We start by importing our objects and call the 
[bigbang.php](https://github.com/lingtalfi/universe/blob/master/planets/TheScientist/bigbang/bigbang.php)
 script.<br>
The bigbang script is the oneliner that one can use to make 
[BSR-0](https://github.com/lingtalfi/universe/blob/master/planets/BumbleBee/Autoload/convention.bsr0.eng.md)
classes available to one's application.<br>
The oneliner technique is explained in the 
[portable autoloader](https://github.com/lingtalfi/universe/blob/master/planets/TheScientist/convention.portableAutoloader.eng.md)
document.

Then I define my sliceWidth, n=10000.

Then I define a pdo connection. 
I use 
[pdo](http://php.net/manual/en/book.pdo.php)
, but you can use any connector you like.

I also use 
[QuickPdo](https://github.com/lingtalfi/QuickPdo),
which is a wrapper for pdo, 
but again, you can use any method that you like;
the only thing that matters is that you are able to query your database.


Then we start using the Sitemap Slicer.
Basically, we create the Sitemap Slicer object (AuthorSitemapIndexSlicer),
then we bind slices to it (AuthorSitemapSlice), and then we call the execute method of the Sitemap Slicer.


Now there is more to say about each method.

### the AuthorSitemapIndexSlicer.onWarning method

You define a callback that is called whenever something wrong happens.<br>
The approach here is that the AuthorSitemapIndexSlicer object catches all exceptions internally,
and makes them available to you via the onWarning method.

That's because in that kind of script which can take a while, we generally don't want that an exception halts the whole process.
In the example above, I use the "a" method (from bigbang script).
That's convenient for quick debugging, but in production you should replace it with a real logging (and not halting) method.


### the AuthorSitemapIndexSlicer.file method

Let you define the location of the generated sitemap index file.


### the AuthorSitemapIndexSlicer.url method

Let you define a callback that converts a sitemap file path to a sitemap url (which are required by the sitemap index).


### the AuthorSitemapIndexSlicer.defaultSliceWidth method

Define the default sliceWidth to use for every Sitemap Slice bound to the AuthorSitemapIndexSlicer object.
We will find out more about Sitemap Slice object soon.


### the AuthorSitemapIndexSlicer.addSitemapSlice method

Adds a Sitemap Slice to your Sitemap Slicer.<br>
One can represent a Sitemap Slice as an object that will be eventually converted to 
a **base sitemap file** (remember what the base sitemap is?).



### the AuthorSitemapSlice.sliceWidth method

Define the sliceWidth for a specific Sitemap Slice.
In the above example, it was used only to show you the different methods of the Sitemap Slice object
(because the sliceWidth was already defined with the defaultSliceWidth method of the Sitemap Slicer object).


### the AuthorSitemapSlice.file method

Define the path for the sitemap file.
This method actually accepts a parameter which can be either a string or callback.
It is described with more details in the 
[SitemapSliceInterface](https://github.com/lingtalfi/SitemapSlicer/blob/master/SitemapSlice/SitemapSliceInterface.php)
file.



### the AuthorSitemapSlice.addTableBindure method

Adds a TableBindure object to your Sitemap Slice.

The TableBindure object is the one that does the hard work of converting the rows from your table 
into sitemap entries for your sitemaps.
This will be explained later.

You can bind multiple TableBindures to a Sitemap Slice.

Remember that the Sitemap Slice represents your sitemap file.
Then the TableBindure represents a table that will feed that particular sitemap file.

You can bind one table to one (base) sitemap, or multiple tables to one (base) sitemap.

Now, all this discussion leads us naturally to the AuthorTableBindure object.



### the AuthorTableBindure.setCountCallback method

Define a callback that returns the total number of rows of the table that you originally
want to parse.

The Sitemap Slicer will need that number for its slicing mechanism.

Again, in the above example, I use QuickPdo to query the application database, but you can just
use any utility that you like.


### the AuthorTableBindure.setRows method

Define a callback that returns the rows (from the table) to parse.<br>
When you code this method, be very careful: the callback takes two arguments: offset and nbItems,
and you need to parameterize your database query with those parameters, 
otherwise the **SLICES MECHANISM WON'T WORK AS EXPECTED!!**

The offset parameter represents the offset of the first row to return,
and the second parameter represents the maximum number of rows to return.

If you are using mySql for instance, it would match perfectly with the arguments 
of the limit clause.

Your callback returns the rows that you want to work with.
Those have to be consistent with the number of rows that you specified with the
setCountCallback method, which means that if you were ignoring the offset and 
nbItems parameters and execute the callback of the setRowsCallback method, it should return exactly the same number of rows
that the number returned by the callback of the setCountCallback method.

Now, internally, the Sitemap Slicer will parse those rows, and call a callback on each of them.
That callback is the one that you set using the setConvertToSitemapEntryCallback method described in the next section.



### the AuthorTableBindure.setConvertToSitemapEntryCallback method

Define the callback that is used to convert a row (generated by the callback set with the setRowsCallback method)
to a sitemap entry.
It turns out that from the beginning, our Sitemap Slicer is actually the AuthorSitemapIndexSlicer,
which internally uses the 
[Sitemap Builder Box](https://github.com/lingtalfi/SitemapBuilderBox)
system.


This means that we can use the 
[Url](https://github.com/lingtalfi/SitemapBuilderBox/blob/master/Objects/Url.php)
object from SitemapBuilderBox (we could also use the 
[Video](https://github.com/lingtalfi/SitemapBuilderBox/blob/master/Objects/Video.php)
object, or the 
[Mobile](https://github.com/lingtalfi/SitemapBuilderBox/blob/master/Objects/Mobile.php)
object if needed for instance).


You can use any other sitemap management system that you like, the only thing that matters 
is that you convert the row to a sitemap entry that your sitemap system 
is able to handle.



It is very likely that you will have to inject some business logic from your app in this callback.
So, this callback is **REALLY WHERE THE WORK IS DONE**.



### the AuthorSitemapIndexSlicer.execute method


Now that our Sitemap Slicer is configured thanks to all the above methods,
we can call the Sitemap slicer's execute method, which is where our "configuration" is read and 
the code is actually being executed.<br>
Remember that the Sitemap Slicer will not halt until the end.
Use the onWarning method to be notified if something goes wrong.



So that concludes our overview of all the methods used by the different objects involved in our first example.



Example 2: mixing with video sitemap 
-------------------------

The second example is just a variation on the first example.
It uses two slices (two base sitemaps will be generated), and the first sitemap is fed by two tables,
one of them is used to generate video entries (from the 
[Google Video sitemap](https://developers.google.com/webmasters/videosearch/sitemaps) 
extension). 


```php
$n = 10000;


QuickPdo::setConnection(
    "mysql:dbname=sketch;host=127.0.0.1",
    'root',
    'root',
    array(
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
        PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    )
);


AuthorSitemapIndexSlicer::create()
    ->onWarning(function ($msg) {
        // log to the system (you probably don't want to interrupt the script with an Exception)
        a($msg);
    })
    ->file(__DIR__ . '/sitemap.index.xml')
    ->url(function ($fileName) {
        return 'http://mysite.com/' . basename($fileName);
    })
    ->defaultSliceWidth($n)
    ->addSitemapSlice(AuthorSitemapSlice::create()
            ->sliceWidth($n)
            ->file('idea.sitemap{n}.xml')
            ->addTableBindure(AuthorTableBindure::create()
                    ->setCountCallback(function () {
                        $stmt = <<<MMM
select count(*) as count from videos where active=1
MMM;
                        if (false !== ($row = QuickPdo::fetch($stmt))) {
                            return $row['count'];
                        }
                        return false; // will trigger an error
                    })
                    ->setRowsCallback(function ($offset, $nbItems) { // gets repeated as long as necessary
                        $stmt = <<<FFF
select * from videos where active=1 order by id asc limit $offset, $nbItems       
FFF;
                        return QuickPdo::fetchAll($stmt);
                    })
                    ->setConvertToSitemapEntryCallback(function (array $row) {
                        $d = new DateTime($row['publish_date']);

                        return Url::create()
//                            ->setLoc(Router::getDynamicUri(URLSPACE_MECA, $row['the_name'], true))
                            ->setLoc('http://sketch/meca/' . $row['the_name'])
                            ->setLastmod($d->format(\DateTime::ISO8601))
                            ->setChangefreq('monthly')
                            ->setVideo(Video::create()
                                    // the getVideoThumbnailByUrl function is open source: https://github.com/lingtalfi/video-ids-and-thumbnails/blob/master/testvideo.php
//                                    ->setThumbnailLoc(getVideoThumbnailByUrl($row['url'], 'medium'))
                                    ->setThumbnailLoc('http://thumbnail.youtube.com/' . $row['the_name'])
                                    ->setTitle($row['the_name'])
                                    ->setDescription($row['description'])
                                    ->setPlayerLoc('http://player/loc/' . $row['url'])
                            );
                    })
            )
            ->addTableBindure(AuthorTableBindure::create()
                    ->setCountCallback(function () {
                        $stmt = <<<MMM
select count(*) as count from mecas where active=1
MMM;
                        if (false !== ($row = QuickPdo::fetch($stmt))) {
                            return $row['count'];
                        }
                        return false; // will trigger an error
                    })
                    ->setRowsCallback(function ($offset, $nbItems) { // gets repeated as long as necessary
                        $stmt = <<<FFF
select * from mecas where active=1 order by id asc limit $offset, $nbItems       
FFF;
                        return QuickPdo::fetchAll($stmt);
                    })
                    ->setConvertToSitemapEntryCallback(function (array $row) {
                        $d = new DateTime($row['publish_date']);

                        return Url::create()
//                            ->setLoc(Router::getDynamicUri(URLSPACE_MECA, $row['the_name'], true))
                            ->setLoc('http://sketch/meca/' . $row['the_name'])
                            ->setLastmod($d->format(\DateTime::ISO8601))
                            ->setChangefreq('monthly');
                    })
            )
    )
    ->addSitemapSlice(AuthorSitemapSlice::create()
            ->sliceWidth($n)
            ->file('other.sitemap{n}.xml')
            ->addTableBindure(AuthorTableBindure::create()
                    ->setCountCallback(function () {
                        $stmt = <<<MMM
select count(*) as count from ideas where active=1
MMM;
                        if (false !== ($row = QuickPdo::fetch($stmt))) {
                            return $row['count'];
                        }
                        return false; // will trigger an error
                    })
                    ->setRowsCallback(function ($offset, $nbItems) { // gets repeated as long as necessary
                        $stmt = <<<FFF
select * from ideas where active=1 order by id asc limit $offset, $nbItems       
FFF;
                        return QuickPdo::fetchAll($stmt);
                    })
                    ->setConvertToSitemapEntryCallback(function (array $row) {
                        $d = new DateTime($row['publish_date']);

                        return Url::create()
//                            ->setLoc(Router::getDynamicUri(URLSPACE_MECA, $row['the_name'], true))
                            ->setLoc('http://sketch/ideas/' . $row['the_name'])
                            ->setLastmod($d->format(\DateTime::ISO8601))
                            ->setChangefreq('monthly');
                    })
            )
    )
    ->execute();


```








 
 
 