<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 */

/**
 * Layer for Layar
 *
 * @package PorPOISe
 */

/**
 * Requires POI
 */
require_once("poi.class.php");

/**
 * Layer description class
 *
 * @package PorPOISe
 */
class Layer {
	// number of POIs returned per page
	const POIS_PER_PAGE = 10;

	// verify hash or not?
	const VERIFY_HASH = TRUE;

	// fields making up this layer
	public $developerId;
	public $developerKey;
	public $layerName;
	
	/** @var POIConnector */
	protected $poiConnector;

	// state variables for returning POI information
	protected $nearbyPOIs;
	protected $hasMorePOIs;
	protected $nextPageKey;
	
	protected $radius = null;

	/**
	 * Constructor
	 *
	 * @param string $layerName
	 * @param string $developerId
	 * @param string $developerKey
	 */
	public function __construct($layerName, $developerId, $developerKey) {
		$this->layerName = $layerName;
		$this->developerId = $developerId;
		$this->developerKey = $developerKey;
	}

	/**
	 * Set a POI connector
	 *
	 * @param POIConnector $poiConnector
	 */
	public function setPOIConnector(iPOIConnector $poiConnector) {
		$this->poiConnector = $poiConnector;
	}

	/**
	 * Add a POI to this layer
	 *
	 * @return void
	 */
	public function addPOI(POI $poi) {
		$this->pois[] = $poi;
	}

	/**
	 * Determines nearby POIs and stores them for later use
	 *
	 * Filter $filter
	 *
	 * @return void
	 */
	public function determineNearbyPOIs(Filter $filter) {
		$this->nearbyPOIs = array();

		if (isset($filter->pageKey)) {
			$offset = $filter->pageKey * self::POIS_PER_PAGE;
		} else {
			$offset = 0;
		}

		if (($offset == 0 || // always reload for 1st page request
			!$this->session_restore($filter->userID)) && // or when no session data exists
				!empty($this->poiConnector)) {

					$pois = $this->poiConnector->getPOIs($filter);
					
					foreach($pois as $poi) {
						if ($poi->distance > $this->radius) {
							$this->radius = $poi->distance;
						}
					}
					$this->nearbyPOIs = $pois;
					$this->session_save($filter->userID);
		}
		// iterate over POIs and determine max distance
		// TODO: do something sensible with this
		// current implementation adds all POIs in the order they are
		// retrieved, while according to the spec max 50 POIs are displayed.
		// So limit POIs to max. 50, optionally after sorting by distance.
		// Maybe make the sorting order a config setting
				
		$this->hasMorePOIs = FALSE;
		$this->nextPageKey = NULL;

		if (count($this->nearbyPOIs) - $offset > self::POIS_PER_PAGE) {
			$this->hasMorePOIs = TRUE;
			$this->nextPageKey = ($offset / self::POIS_PER_PAGE) + 1;
		}
		if ($offset > count($this->nearbyPOIs)) {
			// no POIs on this page
			$nearbyPOIs = array();
		} else {
			$limit = min(self::POIS_PER_PAGE, count($this->nearbyPOIs) - $offset);
			$this->nearbyPOIs = array_slice($this->nearbyPOIs, $offset, $limit);
		}
		if (!$this->hasMorePOIs) {
			$this->session_delete($filter->userID);
		}
	}

	// NOTE: session ID needs to be set correctly, see also WebApp class
	protected function session_init($sid) {
		if ($sid != session_id($sid)) {
			@session_destroy(); // ugly suppression of warnings if no session exists
			session_id($sid);
			session_name('PorPOISe');
			session_start();
		}
	}

	protected function session_restore($sid) {
		$this->session_init($sid);
		// sanity check: are we requesting POIs from the same layer?
		if ($_SESSION['layerName'] != $this->layerName) {
			$this->session_delete($sid);
			return false;
		}
		if (isset($_SESSION['nearbyPOIs'])) {
			$this->nearbyPOIs = $_SESSION['nearbyPOIs'];
			$this->radius = (isset($_SESSION['radius'])) ? $_SESSION['radius'] : null;
			return true;
		} else {
			return false;
		}
	}
	
	
	protected function session_save($sid) {
		$this->session_init($sid);
		$_SESSION['nearbyPOIs'] = $this->nearbyPOIs;
		$_SESSION['layerName'] = $this->layerName;
		$_SESSION['radius'] = $this->radius;
		session_commit();
	}
	
	protected function session_delete($sid) {
		$this->session_init($sid);
		unset($_SESSION['nearbyPOIs']);
		unset($_SESSION['layerName']);
		unset($_SESSION['radius']);
		session_commit();
	}
	
	
	/**
	 * Get the nearby POIs determined after calling determineNearbyPOIs()
	 *
	 * @return POI[]
	 */
	public function getNearbyPOIs() {
		return $this->nearbyPOIs;
	}

		
	/**
	 * Get the Layer name
	 *
	 * @return string $layerName
	 */
	public function getLayerName() {
		return $this->layerName;
	}
		
	/**
	 * Get the max. radius plus some margin
	 *
	 * @return int $radius
	 */
	public function getRadius() {
		return $this->radius;
	}
	
	
	/**
	 * Check if there are more POIs than returned (for additional pages)
	 *
	 * @return bool
	 */
	public function hasMorePOIs() {
		return $this->hasMorePOIs;
	}

	/**
	 * Get the key of the next page (if there are more POIs)
	 *
	 * @return bool
	 */
	public function getNextPageKey() {
		return $this->nextPageKey;
	}

	/**
	 * Verify a supplied hash
	 *
	 * Check can be disabled by setting Layer::VERIFY_HASH to FALSE
	 *
	 * @return bool
	 */
	public function isValidHash($hash, $timestamp) {
		if (!self::VERIFY_HASH) {
			return TRUE;
		}
		$goodHash = sha1($this->developerKey . $timestamp);
		return $hash == $goodHash;
	}
}

