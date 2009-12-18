<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 */

/**
 * Filter class file
 *
 * @package PorPOISe
 */

/**
 * Filter class
 *
 * Used to contain filter values as passed by a client app
 *
 * @package PorPOISe
 */
abstract class Filter {
}

/**
 * Filter class for Layar filter values
 *
 * @package PorPOISe
 */
class LayarFilter extends Filter {
	/** @var string UID for the user as determined by PorPOISe */
	public $porpoiseUID = NULL;
	/** @var string User ID (derived from phone ID) */
	public $userID = NULL;
	/** @var int timestamp Timestamp of the client */
	public $timestamp = NULL;
	/** @var float Latitude of the client */
	public $lat = NULL;
	/** @var float Longitude of the client */
	public $lon = NULL;
	/** @var int (In)accuracy of the current geolocation reading */
	public $accuracy;
	/** @var string Radio option selected */
	public $radiolist = NULL;
	/** @var string Searchbox value (Layar v2)/First searchbox value (Layar v3) */
	public $searchbox1 = NULL;
	/** @var string Second searchbox */
	public $searchbox2 = NULL;
	/** @var string Third searchbox */
	public $searchbox3 = NULL;
	/** @var int Radius the user would like to see */
	public $radius = NULL;
	/** @var float Custom slider value (Layar v2)/First custom slider value (Layar v3) */
	public $customSlider1 = NULL;
	/** @var float Second custom slider value */
	public $customSlider2 = NULL;
	/** @var float Third custom slider value */
	public $customSlider3 = NULL;
	/** @var string[] checkboxlist */
	public $checkboxlist = array();
	/** @var int Altitude of the client */
	public $alt = NULL;
}
