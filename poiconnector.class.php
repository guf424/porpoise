<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 */

/**
 * File for abstract POI connector
 *
 * @package PorPOISe
 */

/**
 * Include POIConnector interface
 */
require_once("poiconnector.interface.php");

/**
 * POI connector base class
 *
 * A POI connector is in charge of getting POIs from a specified source.
 * Each specific POI connector should be able to open (connect to) the
 * specified source and get POI information from it somehow. A POIConnector
 * is also in charge of storing POIs in the same format at the same source.
 *
 * @package PorPOISe
 */
abstract class POIConnector implements iPOIConnector {

	/**
	 * Set a (connector-specific) option
	 *
	 * Connectors with specific options, such as an XML stylesheet, should
	 * override this method to handle those options but always call the
	 * parent method for unknown options.
	 *
	 * @param string $optionName
	 * @param string $optionValue
	 *
	 * @return void
	 */
	public function setOption($optionName, $optionValue) {
		/* no generic options defined as of yet */
	}

	/**
	 * Determines whether a POI passes the supplied filter options
	 *
	 * @param POI $poi
	 * @param Filter $filter
	 *
	 * @return bool
	 */
	protected function passesFilter(POI $poi, Filter $filter = NULL) {
		return TRUE;
	}
}
