# Introduction #

Often, you will already have a database with POIs you want to expose as a layer. In that case, it would be nice of PorPOISe could connect to your existing database, wouldn't it? Thanks to PorPOISe's  modular structure, this isn't very difficult. This page will explain how to go about this.

# Details #

As with [Filters](Filters.md), it is easiest to alter the data collection behaviour of PorPOISe by extending an existing `POIConnector`. This example will explaing how to extend the `SQLPOIConnector` class to use a different database schema than the default one.

If you already have a database with coordinates and descriptive information, all you basically have to do is write an SQL query that selects the data you want but returns it in a format that PorPOISe understands. Let's assume you have a database with properties for sale. Your database probably has a table `House` with columns like `house_id`, `price`, `street`, `number` and `city`. However, PorPOISe expects a table `POI` and columns like `title`, `line2`, `line3` and `line4`. So, your table (with content) would look like this:

| **house\_id** | **latitude** | **longitude** | **street** | **number** | **city** | **price**|
|:--------------|:-------------|:--------------|:-----------|:-----------|:---------|:|
| 1 | 52.1 | 4.5 | Zuidbuurtseweg | 2 | Zoeterwoude | some euros |
| 2 | 52.11 | 4.3 | Brugsestraat | 39 | Den Haag | many euros |

But, for PorPOISe to deliver it to a Layar client, it _should_ look like this:

| **id** | **lat** | **lon** | **line2** | **line3** | **title** |
|:-------|:--------|:--------|:----------|:----------|:----------|
| 1 | 52.1 | 4.5 | Zuidbuurtseweg 2 | Zoeterwoude | some euros |
| 2 | 52.11 | 4.3 | Brugsestraat 39 | Den Haag | many euros |

You can achieve this transformation fairly easily by _aliasing_ the column names in your SQL query:

`SELECT house_id AS id, latitude AS lat, longitude AS lon, CONCAT(street, ' ', number) AS line2, city AS line3, price AS title FROM House`

When you exexcute this query on the first table the output will look like it came from the second table, so (almost) ready for PorPOISe to send back to the client. However, how do you instruct PorPOISe to use this query?

The most straightforward approach is to adapt the file `sqlpoiconnector.class.php` and substitute the query there for the one that works on _your_ database. However, this will get you in trouble when the next update of PorPOISe comes out: if you want to roll out the new version, you will overwrite the changes you made which you will then have to re-do in your own deployment. This is a tedious and error-prone task.

However, PorPOISe is designed to create your own `POIConnector`s that suit your specific needs. To start creating a `POIConnector` for your property database, create a file in the PorPOISe directory with a descriptive name; for this example we will go with `propertypoiconnector.class.php`. Open the file in an editor and enter this:

```
<?php

class PropertyPOIConnector extends SQLPOIConnector {
}
```

Now you have created a new `POIConnector` which is based on the `SQLPOIConnector`. Because the class body is empty, it does not do anything special (other than what the default `SQLPOIConnector` does). Before we start programming that, we will first tell PorPOISe we created this `POIConnector`. Open `config.xml` and look for a line that looks like this:

```
<connector><name>SQLPOIConnector</name><file>sqlpoiconnector.class.php</file></connector>
```

What this line does is tel PorPOISe that the connector `SQLPOIConnector` can be found in file `sqlpoiconnector.class.php`. We just made a connector `PropertyPOIConnector` in file `propertypoiconnector.class.php`, so _below_ this line we will _add_ the following:

```
<connector><name>PropertyPOIConnector</name><file>propertypoiconnector.class.php</file></connector>
```

Now PorPOISe can find our `PropertyPOIConnector`. Next, we will create a layer definition that describes the property layer. Lower in the config file, find the element `<layers>` which marks the beginning of the layer definition section and, right below that line, enter the following:

```
<layer>
 <name>propertiesforsale</name>
 <source>
  <dsn>mysql:host=localhost;dbname=propertiesforsale</dsn>
  <username></username>
  <password></password>
 </source>
 <connector>PropertyPOIConnector</connector>
</layer>
```

Make sure to enter the right [DSN|http://www.php.net/manual/en/pdo.construct.php], username and password for your server.

Now, we can alter our new POIConnector to make it execute the new SQL we constructed earlier. The `SQLPOIConnector` has an internal method `buildQuery` whose method signature looks like this:

```
protected function buildQuery(Filter $filter = NULL)
```

This method is expected to return a valid SQL statement which will be executed to retrieve the POIs from the database. By _overriding_ (re-implementing in a subclass) this method and returning a different SQL string we can make our `PropertyPOIConnector` behave just like `SQLPOIConnector` _except_ for what kind of SQL it executes. However, simply writing

```
return "SELECT house_id AS id, latitude AS lat, longitude AS lon, CONCAT(street, ' ', number) as line2, city as line3, price as title FROM House";
```

as the method body is not going to work. To see why, we will take a look at the original `buildQuery` method:

```
protected function buildQuery(Filter $filter = NULL) {
    if (empty($filter)) {
        $sql = "SELECT * FROM POI";
    } else {
        $sql = "SELECT *, " . GeoUtil::EARTH_RADIUS . " * 2 * asin(
            sqrt(
                pow(sin((radians(" . addslashes($filter->lat) . ") - radians(lat)) / 2), 2)
                +
                cos(radians(" . addslashes($filter->lat) . ")) * cos(radians(lat)) * pow(sin((radians(" . addslashes($filter->lon) . ") - radians(lon)) / 2), 2)
            )
        ) AS distance
        FROM POI";
        if (!empty($filter->radius)) {
            $sql .= " HAVING distance < (" . addslashes($filter->radius) . " + " . addslashes($filter->accuracy) . ")";
        }
        $sql .= " ORDER BY distance ASC";
    }

    return $sql;
}
```

That looks intimidating at first glance so we will go through it step by step. The method starts off with a check to see if `$filter` is empty. If so, the returned SQL is very simple: return everything from table `POI`. This condition will be met when the `POIConnector` is called by the dashboard, in which case all POIs should be returned (it is not a client request). If the filter is not empty this indicates a request from a mobile device (or something acting like it) and we will want to take into account the request parameters latitude, longitude, search radius and GPS accuracy. We also need to calculate the distance of each POI to the search center, which we will do in SQL as well.

The first part of the SQL string is actually very simple: `SELECT *` simply selects all columns. This simple part is followed by 8 convoluted lines of sines, cosines and request parameters, which ends in the magic words `AS distance`: it's the distance calculation formula. The next line, `FROM POI`, specifies the table to select from. Then, if there is a search radius specified in the request, we append a string to return only points within the search radius, taking into account accuracy tolerance, and finally we want to receive the result ordered, nearest points first.

Now, for our new query, we will want to keep most of this behaviour. What we want to change is the name of the columns and the name of the table. The simplest way to start off is therefore to simply copy the entire `buildQuery` method from `SQLPOIConnector` to `PropertyPOIConnector`. Once we've done this, we can edit it to use our new SQL. Tackling the first case (no filter) is rather easy: instead of

`SELECT * FROM POI`

we want to execute

`SELECT house_id AS id, latitude AS lat, longitude AS lon, CONCAT(street, ' ', number) as line2, city as line3, price as title FROM House`.

So, alter the lines

```
if (empty($filter)) {
    $sql = "SELECT * FROM POI";
}
```

by the lines

```
if (empty($filter)) {
    $sql = "SELECT house_id AS id, latitude AS lat, longitude AS lon, CONCAT(street, ' ', number) as line2, city as line3, price as title FROM House";
}
```

Next, we need to adapt the SQL string for the case where there is a filter. As said earlier, we need to change two things: the names of the columns and the name of the table. For the columns, substitute the `*` (and only that) for `house_id AS id, latitude AS lat, longitude AS lon, CONCAT(street, ' ', number) as line2, city as line3, price as title`. For the table name, substitute `FROM POI` some lines below for `FROM House`. Now comes the hardest part: the distance calculation formula uses the `lat` and `lon` columns of the POI table. However, they are called `latitude` and `longitude` in the `House` table, so we need te replace occurences of `lat` and `lon` by `latitude` and `longitude`. There's two occurences of `lat` and one of `lon` in the original formula: find and change them, but make sure to not touch `$filter->lat` and `$filter->lon`: those are PorPOISe-specific variables and have nothing to do with the database.

That's it, you're done. The entire `PropertyPOIConnector` class should now look like this:

```
<?php

class PropertyPOIConnector extends SQLPOIConnector {
    protected function buildQuery(Filter $filter = NULL) {
        if (empty($filter)) {
            $sql = "SELECT house_id AS id, latitude AS lat, longitude AS lon, CONCAT(street, ' ', number) as line2, city as line3, price as title FROM House";
        } else {
            $sql = "SELECT house_id AS id, latitude AS lat, longitude AS lon, CONCAT(street, ' ', number) as line2, city as line3, price as title, " . GeoUtil::EARTH_RADIUS . " * 2 * asin(
                sqrt(
                    pow(sin((radians(" . addslashes($filter->lat) . ") - radians(latitude)) / 2), 2)
                    +
                    cos(radians(" . addslashes($filter->lat) . ")) * cos(radians(latitude)) * pow(sin((radians(" . addslashes($filter->lon) . ") - radians(longitude)) / 2), 2)
                )
            ) AS distance
            FROM House";
            if (!empty($filter->radius)) {
                $sql .= " HAVING distance < (" . addslashes($filter->radius) . " + " . addslashes($filter->accuracy) . ")";
            }
            $sql .= " ORDER BY distance ASC";
        }

        return $sql;
    }
}
```

Save the `propertypoiconnector.class.php` file and test your layer (the Layar API test page is probably a good start in case there is a bug somewhere).

# Going from here #

You will probably have to adapt this example to match your own database schema. Once you've got this working, you can also use this technique to create [Filters](Filters.md): all the request parameters are passed in the `$filter` parameter, including stuff like searchbox and radiolist values. You can incorporate these values into your SQL query to limit the size of your result set at the earliest possible stage, thus reducing the amount of subsequent processing and thus improving performance. Basically, you can return just about any SQL string, as long as executing it gives PorPOISe a result set with the right column names.

# Using actions and 2D/3D objects #

In the original PorPOISe database schema, actions, objects and object transform information are stored in tables separately from the `POI` table. Because these values are very specific to Layar, they will probably not be part of your original database. If you want to use actions and objects in your layer, you should add tables `Action`, `Object` and `Transform` with the following structures:

### Action ###
| **poiID** | **label** | **uri** | **autoTriggerRange** | **autoTriggerOnly**|
|:----------|:----------|:--------|:---------------------|:|
| <id of the POI in your table> | <label to show in Layar> | <URI to open> | <range in meters for auto trigger> | <allow manual trigger or not?> |

### Object ###
| **poiID** | **baseURL** | **full** | **reduced** | **icon** | **size** |
|:----------|:------------|:---------|:------------|:---------|:---------|
| <id of the POI in your table> | <where your model files reside> | <filename of the full scale model> | <filename of a reduced model> | <filename of icon image> | <approximate size in meters> |

### Transform ###
| **poiID** | **rel** | **angle** | **scale** |
|:----------|:--------|:----------|:----------|
| <id of the POI in your table> | <model always facing user?> | <rotation along Z-axis> | <model scaling factor for display> |

The definitions of these schemas are also in the file `database.sql`