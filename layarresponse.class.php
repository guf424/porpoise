<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 */

/**
 * Response for Layar
 *
 * @package PorPOISe
 */

/**
 * Response object
 *
 * Contains only overall response parameters, not specific ones
 * such as errorCode, errorMessage, nextPageKey and hasMorePages
 *
 * @package PorPOISe
 */
class LayarResponse {
	/** @var POI[] */
	public $hotspots = array();
	/** @var string[] When sending a POI update (instead of a full refresh), this specifies hotspots to delete from the previous set */
	public $deletedHotspots = array();
	/** @var int Radius containing the returned POI set */
	public $radius = 0;
	/** @var int Refresh interval in seconds */
	public $refreshInterval = 300;
	/** @var int Refresh distance in meters */
	public $refreshDistance = 100;
	/** @var bool Do a full refresh or an update */
	public $fullRefresh = TRUE;
	/** @var string Response message to display */
	public $showMessage = NULL;
	/** @var Action[] */
	public $actions = array();
	/** @var Animation[] */
	public $animations = array("onCreate" => array(), "onUpdate" => array(), "onDelete" => array(), "onFocus" => array(), "onClick" => array());
	/** @var bool */
	public $morePages = FALSE;
	/** @var string */
	public $nextPageKey = NULL;
	/** @var string */
	public $layer = NULL;
	/** @var int */
	public $errorCode = 0;
	/** @var string */
	public $errorString = "ok";
	/** @var string biwStyle defines the default BIW to use as a default for all BIWs in the layer although they can be overruled on a per-POI-basis */
	public $biwStyle = NULL;
}
