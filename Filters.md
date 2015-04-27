# Introduction #

Layar allows you to add filters to your layer, such as search boxes, sliders and radio buttons. Whatever the users enters into these filter values is sent to PorPOISe. PorPOISe however does nothing with these values out-of-the box because it does not know how to interpret them in relation to the data you are offering through your layer. If you want to use filter values in your layer you will have to write your own custom `POIConnector`. Don't fret though, PorPOISe is structured so that you can use existing `POIConnector`s and only write some simple tests to create a working filter. I will explain below all the steps you need. To make this example work, you need to have already have layer you can experiment with that contains a few POIs near you so you can see the effect your filters have. The example uses an `XMLPOIConnector` but you can use `FlatPOIConnector` or `SQLPOIConnector` by substituting those names wherever it says `XMLPOIConnector`.

# Creating your own `POIConnector` #

PorPOISe uses so-called `POIConnector` classes to manage POIs in a data source, such as an XML file or MySQL database. To create your own, custom `POIConnector` you can start from scratch but you can also adapt an existing one through a mechanism called "inheritance". In this example, we are going to create a `POIConnector` based on the existing `XMLPOIConnector`. To start, create a file called "mypoiconnector.class.php" in the root of your PorPOISe directory. Open it with your favourite text editor and enter the following piece of PHP code:

```
<?php

class MyPOIConnector extends XMLPOIConnector {
}
```

Save the file and you've done it: your own custom `POIConnector`. It doesn't do anything yet but it exists.

Now we have to tell PorPOISe that we have created a new `POIConnector` so we can use it. Open "config.xml" and look for a line that reads like

```
<connector><name>XMLPOIConnector</name><file>xmlpoiconnector.class.php</file></connector>
```

Add a similar line below it with the details of our new `POIConnector`:

```
<connector><name>MyPOIConnector</name><file>mypoiconnector.class.php</file></connector>
```

This tells PorPOISe that it can find the definition of `MyPOIConnector` in the file "mypoiconnector.class.php". If PorPOISe needs the definition of `MyPOIConnector`, it will read it from the file you specified here.

Now we have to tell PorPOISe to _use_ `MyPOIConnector` to retrieve your POIs. In "config.xml", navigate to the definition of your test layer and replace whatever is between `<connector>` and `</connector>` with the string "MyPOIConnector". Save the file and open your layer in the Layar client or the Layar API test page.

If everything went OK, your layer looks exactly the same as it did before. This is because `MyPOIConnector` still behaves just like `XMLPOIConnector`. Now, it's time to change this. First, add a filter to your layer on the Layar publishing site by editing your layer and going to the tab "Filters". Here, add a search box and give it a name (we don't use the name in the code so it doesn't matter what you call it).

Reload the layer on your phone or the API test page and you will see that a search box has been added to the available filters. Entering a value will not do anything though, so let's do something about that. Suppose we want to filter depending on the value that the user entered in the search box: if there's nothing there, we show everything, but is the user entered something, we will only show POIs that contain the search string in the title or in one of the extra lines 2, 3 or 4. To do this, open "mypoiconnector.class.php" again and add the following code between the accolades:

```
	public function passesFilter(POI $poi, Filter $filter = NULL) {
		if (empty($filter)) {
			return TRUE;
		}
		if (empty($filter->searchbox1)) {
			return TRUE;
		}

		if (strpos($poi->title, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}
		if (strpos($poi->line2, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}
		if (strpos($poi->line3, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}
		if (strpos($poi->line4, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}

		return FALSE;
	}
```

What we're doing here is _overriding_ a method (class function) of `XMLPOIConnector`, namely `passesFilter()`. Every default `POIConnector` that comes with PorPOISe has a `passesFilter()` method that is called for each POI that is within the client' range. If this method returns boolean TRUE, the POI is included in the response. If the method returns boolean FALSE, the POI is _not_ sent back to the client. By overriding this method, we can define custom behaviour for `MyPOIConnector`. What we do is 1) check if there is a filter at all (no filter means the call came from the dashboard) 2) check if there is a search value and 3) if there is a search value, see if it occurs in either the title or one of the extra lines. If any of these conditions hold, the POI under evaluation is suitable to be returned to the client so we return boolean TRUE. If none holds, the POI is _not_ suitable and we return boolean FALSE.

Save the file and refresh your layer to check all the changes are correct. If you see your POIs again, everything is fine. Otherwise, check that your accolades match up (for every `{` there should be a matching `}` later in the file). Now try entering a search value (tap the radar in the Layar client to quickly access filters) and see your new filter in effect!

You may have noticed in the example above that the property that we check against is called `searchbox1` while in the Layar API it is called `SEARCHBOX_1`. This has been done to prevent confusion between variables, which are usually written with lowercast letters, and constants, which are usually in capitals. The same has been done for `SEARCHBOX_2` and `SEARCHBOX_3` as well as `RADIOLIST` (`radiolist` in PorPOISe), `CUSTOM_SLIDER_n` (`customSlidern`) and `CHECKBOXLIST` (`checkboxlist`). Furthermore, multiple search boxes and sliders have only been introduced in Layar 3, in Layar 2 there was only one of either and they did not have an `_n` suffix. PorPOISe handles this discrepancy for you and _always_ stores the value of the only (if only one) or first (when there are multiple) searchbox/slider in `searchbox1`/`customSlider1`, so as a coder your don't have to worry about it.

This concludes this example. You can now edit this `POIConnector` to do the filtering _you_ want or create a new one with a completely different filter: the choice is yours.

Below is the complete example `MyPOIConnector`, with extra comments inline explaining what's what.

```
<?php

/*
 * Example custom POI connector
 */

/*
 * Begin definition of custom POI connector. It's based on the XML POI
 * connector, so that is the class we extend
 */
class MyPOIConnector extends XMLPOIConnector {
	/* 
	 * The default XML POI connector has two methods that relate directly to
	 * filtering: buildQuery() and passesFilter():
	 *
	 * buildQuery() is expected to return an XPath expression to search for
	 * POIs in your document and is used to prune the data set at selection
	 * time.
	 *
	 * passesFilter() is called for every POI that is read from the data file
	 * before it is added to the result set of POIs. If this method returns
	 * FALSE, the POI is not added to the result set but discarded.
	 *
	 * You can override either or both of these methods to control the reponse
	 * you send to the client that made the request. In this example, we will
	 * override the passesFilter() method to take into account the value of
	 * the SEARCHBOX_1 parameter.
	 */

	/*
	 * Our custom passesFilter() method
	 */
	public function passesFilter(POI $poi, Filter $filter = NULL) {
		/*
		 * Always return TRUE if no filter is given
		 */
		if (empty($filter)) {
			return TRUE;
		}

		/*
		 * If the searchbox value is empty, we do not apply a filter but
		 * return all POIs, so passesFilter() always returns TRUE for
		 * those cases.
		 */
		if (empty($filter->searchbox1)) {
			return TRUE;
		}

		/*
		 * If there is a value in SEARCHBOX_1 we only want to return POIs
		 * that have the search value in the title or any of line2/3/4.
		 *
		 * We use strpos() to search in strings, which returns FALSE when
		 * the search string does not appear in the target string but can
		 * also return integer 0 if the target string starts with the
		 * search string, so we have to check the function result using
		 * the !== operator.
		 */
		if (strpos($poi->title, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}
		if (strpos($poi->line2, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}
		if (strpos($poi->line3, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}
		if (strpos($poi->line4, $filter->searchbox1) !== FALSE) {
			return TRUE;
		}

		/*
		 * If we reached this point, none of the checks above resulted in
		 * the method returning TRUE, so we have to conclude that the POI
		 * does not pass the filter and thus return FALSE.
		 */
		return FALSE;
	}
}
```