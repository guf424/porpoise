<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 *
 * Acknowledgments:
 * Robert Harm for the "Increase range" error message
 */

/**
 * POI Server for Layar
 *
 * The server consists of a server class whose objects serve Layar responses when
 * properly configured and a factory class that helps you create a properly
 * configured server.
 *
 * @package PorPOISe
 */

/** Requires POI definition */
require_once("poi.class.php");
/** Requires Layer definition */
require_once("layer.class.php");
/** Requires LayarFilter */
require_once("filter.class.php");
/** Requires FlatPOIConnector */
require_once("flatpoiconnector.class.php");
/** Requires FlatPOIConnector */
require_once("xmlpoiconnector.class.php");
/** Requires SQLPOIConnector */
require_once("sqlpoiconnector.class.php");

/**
 * Server class that serves up POIs for Layar
 *
 * @package PorPOISe
 */
class LayarPOIServer {
	/** Default error code */
	const ERROR_CODE_DEFAULT = 20;
	/** Request has no POIs in result */
	const ERROR_CODE_NO_POIS = 21;

	/** @var string[] Error messages stored in an array because class constants cannot be arrays */
	protected static $ERROR_MESSAGES = array(
		self::ERROR_CODE_DEFAULT => "An error occurred"
		, self::ERROR_CODE_NO_POIS => "No POIs found. Increase range or adjust filters to see POIs"
	);

	// layers in this server
	protected $layers = array();

	protected $requiredFields = array("userId", "developerId", "developerHash", "timestamp", "layerName", "lat", "lon");
	protected $optionalFields = array("accuracy", "RADIOLIST", "SEARCHBOX_1", "SEARCHBOX_2", "SEARCHBOX_3", "CUSTOM_SLIDER_1", "CUSTOM_SLIDER_2", "CUSTOM_SLIDER_3", "pageKey", "oath_consumer_key", "oauth_signature_method", "oauth_timestamp", "oauth_nonce", "oauth_version", "oauth_signature", "radius", "alt");

	/**
	 * Add a layer to the server
	 *
	 * @param Layer $layer
	 *
	 * @return void
	 */
	public function addLayer(Layer $layer) {
		$this->layers[$layer->layerName] = $layer;
	}

	/**
	 * Handle a request
	 *
	 * Request variables are expected to live in the $_REQUEST superglobal
	 *
	 * @return void
	 */
	public function handleRequest(LayarLogger $loghandler = null) {
		$filter = $this->buildFilter();
		try {
			$this->validateRequest();
			
			$layer = $this->layers[$_REQUEST["layerName"]];
			$numPois = $layer->determineNearbyPOIs($filter);
			if ($loghandler) {
				$loghandler->log($filter, array('numpois' => $numPois));
			}
	
			$pois = $layer->getNearbyPOIs();
			if (count($pois) == 0) {
				$this->sendErrorResponse(self::ERROR_CODE_NO_POIS);
				return;
			}
			$morePages = $layer->hasMorePOIs();
			if ($morePages) {
				$nextPageKey = $layer->getNextPageKey();
			} else {
				$nextPageKey = NULL;
			}
			$radius = $layer->getRadius();
			
			$this->sendResponse($pois, $morePages, $nextPageKey, $radius);
		} catch (Exception $e) {
			if ($loghandler) {
				$loghandler->log($filter, array('errorMessage' => $e->getMessage()));
			}
	
			$this->sendErrorResponse(self::ERROR_CODE_DEFAULT, $e->getMessage());
		}
	}

	/**
	 * Send a Layar response to a client
	 *
	 * @param array $pois An array of POIs that match the client's request
	 * @param bool $morePages Pass TRUE if there are more pages beyond this set of POIs
	 * @param string $nextPageKey Pass a valid key if $morePages is TRUE
	 *
	 * @return void
	 */
	protected function sendResponse(array $pois, $morePages = FALSE, $nextPageKey = NULL, $radius = NULL) {
		$response = array();
		$response["morePages"] = $morePages;
		$response["nextPageKey"] = (string)$nextPageKey;
		$response["layer"] = $_REQUEST["layerName"];
		$response["errorCode"] = 0;
		$response["errorString"] = "ok";
		$response["hotspots"] = array();
		if ($radius) {
			$radius *= 1.25; // extend radius with 25% to avoid far away POI's dropping off when location changes
			$response["radius"] = intval($radius);
		}
		foreach ($pois as $poi) {
			// test if current POI was requested and should be in focus
			if ($poi->id == @$this->filter->requestedPoiId) {
				$poi->inFocus = true;
			}
			
			$aPoi = $poi->toArray();
			
			// strip out optional fields to cut on bandwith
			if (!$aPoi['inFocus']) unset($aPoi['inFocus']);
			if (!$aPoi['alt']) unset($aPoi['alt']);
			if (!$aPoi['relativeAlt']) unset($aPoi['relativeAlt']);
			if (!$aPoi['doNotIndex']) unset($aPoi['doNotIndex']);
			foreach($aPoi['actions'] as &$action) {
				if(!$action['autoTriggerRange']) {
					unset($action['autoTriggerRange']);
					unset($action['autoTriggerOnly']);
				}
			}
			// upscale coordinate values and truncate to int because of inconsistencies in Layar API
			// (requests use floats, responses use integers?)
			$aPoi["lat"] = (int)($aPoi["lat"] * 1000000);
			$aPoi["lon"] = (int)($aPoi["lon"] * 1000000);
			// fix some types that are not strings
			$aPoi["type"] = (int)$aPoi["type"];
			$aPoi["distance"] = (float)$aPoi["distance"];
			
			$i = count($response["hotspots"]);
			$response["hotspots"][$i] = $aPoi;
		}

		/* Set the proper content type */
		header("Content-Type: application/json");

		printf("%s", json_encode($response));
	}

	/**
	 * Send an error response
	 *
	 * @param int $code Error code for this error
	 * @param string $msg A message detailing what went wrong
	 *
	 * @return void
	 */
	protected function sendErrorResponse($code = self::ERROR_CODE_DEFAULT, $msg = NULL) {
		$response = array();
		if (isset($_REQUEST["layerName"])) {
			$response["layer"] = $_REQUEST["layerName"];
		} else {
			$response["layer"] = "unspecified";
		}
		$response["errorCode"] = $code;
		if (!empty($msg)) {
			$response["errorString"] = $msg;
		} else {
			$response["errorString"] = self::$ERROR_MESSAGES[$code];
		}
		$response["hotspots"] = array();
		$response["nextPageKey"] = NULL;
		$response["morePages"] = FALSE;

		/* Set the proper content type */
		header("Content-Type: text/javascript");

		printf("%s", json_encode($response));
	}

	/**
	 * Validate a client request
	 *
	 * If this function returns (i.e. does not throw anything) the request is
	 * valid and can be processed with no further input checking
	 * 
	 * @throws Exception Throws an exception of something is wrong with the request
	 * @return void
	 */
	protected function validateRequest() {
		foreach ($this->requiredFields as $requiredField) {
			if (empty($_REQUEST[$requiredField])) {
				throw new Exception(sprintf("Missing parameter: %s", $requiredField));
			}
		}
		foreach ($this->optionalFields as $optionalField) {
			if (!isset($_REQUEST[$optionalField])) {
				$_REQUEST[$optionalField] = "";
			}
		}

		$layerName = $_REQUEST["layerName"];
		if (empty($this->layers[$layerName])) {
			throw new Exception(sprintf("Unknown layer: %s", $layerName));
		}

		$layer = $this->layers[$layerName];
		if ($layer->developerId != $_REQUEST["developerId"]) {
			throw new Exception(sprintf("Unknown developerId: %s", $_REQUEST["developerId"]));
		}

		if (!$layer->isValidHash($_REQUEST["developerHash"], $_REQUEST["timestamp"])) {
			throw new Exception(sprintf("Invalid developer hash", $_REQUEST["developerHash"]));
		}

		if ($_REQUEST["lat"] < -90 || $_REQUEST["lat"] > 90) {
			throw new Exception(sprintf("Invalid latitude: %s", $_REQUEST["lat"]));
		}

		if ($_REQUEST["lon"] < -180 || $_REQUEST["lon"] > 180) {
			throw new Exception(sprintf("Invalid longitude: %s", $_REQUEST["lon"]));
		}

	}

	/**
	 * Build a filter object from the request
	 *
	 * @return LayarFilter
	 */
	protected function buildFilter() {
		$result = new LayarFilter();
		foreach ($_REQUEST as $key => $value) {
			switch ($key) {
			case "userId":
				$result->userID = $value;
				break;
			case "pageKey":
			case "lang":
			case "countryCode":
			case "layerName":				
			case "version":	
				$result->$key = $value;
				break;
			case "requestedPoiId":				
				$result->$key = ($value == 'None') ? null : $value;
				break;
			case "timestamp":
			case "accuracy":
			case "radius":
			case "alt":
				$result->$key = (int)$value;
				break;
			case "lat":
			case "lon":
				$result->$key = (float)$value;
				break;
			case "RADIOLIST":
				$result->radiolist = $value;
				break;
			case "SEARCHBOX":
				$result->searchbox1 = $value;
				break;
			case "SEARCHBOX_1":
				/* special case: if SEARCHBOX and SEARCHBOX_1 are set, SEARCHBOX takes precedence */
				if (empty($_REQUEST["SEARCHBOX"])) {
					$result->searchbox1 = $value;
				}
				break;
			case "SEARCHBOX_2":
				$result->searchbox2 = $value;
				break;
			case "SEARCHBOX_3":
				$result->searchbox3 = $value;
				break;
			case "CUSTOM_SLIDER":
				$result->customSlider1 = (float)$value;
				break;
			case "CUSTOM_SLIDER_1":
				/* special case: if CUSTOM_SLIDER and CUSTOM_SLIDER_1 are set, CUSTOM_SLIDER takes precedence */
				if (empty($_REQUEST["CUSTOM_SLIDER"])) {
					$result->customSlider1 = (float)$value;
				}
				break;
			case "CUSTOM_SLIDER_2":
				$result->customSlider2 = (float)$value;
				break;
			case "CUSTOM_SLIDER_3":
				$result->customSlider3 = (float)$value;
				break;
			case "CHECKBOXLIST":
				$result->checkboxlist = explode(",", $value);
				break;
			}
		}
		// As of 20100601 Format is: Layar/x.y [OS name]/x.y.z ([Brand] [Model])
		if (isset($_SERVER['HTTP_USER_AGENT'])) {
			$result->userAgent = $_SERVER['HTTP_USER_AGENT'];
		}

		if (!empty($_COOKIE[$_REQUEST["layerName"] . "Id"])) {
			$result->porpoiseUID = $_COOKIE[$_REQUEST["layerName"] . "Id"];
		}

		$this->filter = $result;
		return $result;
	}
}

/**
 * Factory class to create LayarPOIServers
 *
 * @package PorPOISe
 */
class LayarPOIServerFactory {
	/** @var $developerId */
	protected $developerId;
	/** @var $developerKey */
	protected $developerKey;

	/**
	 * Constructor
	 *
	 * @param string $developerID Your developer ID
	 * @param string $developerKey Your developer key
	 */
	public function __construct($developerID, $developerKey) {
		$this->developerId = $developerID;
		$this->developerKey = $developerKey;
	}
	
	/**
	 * Create a LayarPOIServer with content from a list of files
	 *
	 * @deprecated Use the more generic createLayarPOIServer
	 *
	 * @param array $layerFiles The key of each element is expected to be the
	 * layer's name, the value to be the filename of the file containing the
	 * layer's POI in tab delimited format.
	 *
	 * @return LayarPOIServer
	 */
	public function createLayarPOIServerFromFlatFiles(array $layerFiles) {
		$result = new LayarPOIServer();
		foreach ($layerFiles as $layerName => $layerFile) {
			$layer = new Layer($layerName, $this->developerId, $this->developerKey);
			$poiConnector = new FlatPOIConnector($layerFile);
			$layer->setPOIConnector($poiConnector);
			$result->addLayer($layer);
		}
		return $result;
	}

	/**
	 * Create a LayarPOIServer with content from a list of XML files
	 *
	 * @deprecated Use the more generic createLayarPOIServer
	 *
	 * @param array $layerFiles The key of each element is expected to be the
	 * layer's name, the value to be the filename of the file containing the
	 * layer's POIs in XML format.
	 *
	 * @param string[] $layerFiles
	 * @param string $layerXSL
	 *
	 * @return LayarPOIServer
	 */
	public function createLayarPOIServerFromXMLFiles(array $layerFiles, $layerXSL = "") {
		$result = new LayarPOIServer();
		foreach ($layerFiles as $layerName => $layerFile) {
			$layer = new Layer($layerName, $this->developerId, $this->developerKey);
			$poiConnector = new XMLPOIConnector($layerFile);
			$poiConnector->setStyleSheet($layerXSL);
			$layer->setPOIConnector($poiConnector);
			$result->addLayer($layer);
		}
		return $result;
	}

	/**
	 * Create a LayarPOIServer with content from a database
	 *
	 * @deprecated Use the more generic createLayarPOIServer
	 *
	 * @param array $layerDefinitions The keys of $layerDefinitions define
	 * the names of the created layers, the values should be arrays with
	 * the elements "dsn", "username" and "password" used to connect to the
	 * database. Username and password may be omitted.
	 *
	 * @return LayarPOIServer
	 */
	public function createLayarPOIServerFromDatabase(array $layerDefinitions) {
		$result = new LayarPOIServer();
		foreach ($layerDefinitions as $layerName => $credentials) {
			$layer = new Layer($layerName, $this->developerId, $this->developerKey);
			if (empty($credentials["username"])) {
				$credentials["username"] = "";
			}
			if (empty($credentials["password"])) {
				$credentials["password"] = "";
			}
			$poiConnector = new SQLPOIConnector($credentials["dsn"], $credentials["username"], $credentials["password"]);
			$layer->setPOIConnector($poiConnector);
			$result->addLayer($layer);
		}

		return $result;
	}

	/**
	 * Create a server based on SimpleXML configuration directives
	 *
	 * @deprecated Use the more generic createLayarPOIServer
	 *
	 * $config is an array of SimpleXMLElements, each element should contain
	 * layer nodes specifying connector (class name), layer name and data source.
	 * The root node name is not important but "layers" is suggested.
	 * For flat files and XML, use a URI as source. For SQL, use dsn, username
	 * and password elements.
	 * Example:
	 * <layers>
	 *  <layer>
	 *   <connector>SQLPOIConnector</connector>
	 *   <name>test</name>
	 *   <source>
	 *    <dsn>mysql:host=localhost</dsn>
	 *    <username>default</username>
	 *    <password>password</password>
	 *   </source>
	 *  </layer>
	 * </layers>
	 *
	 * @param SimpleXMLElement $config
	 *
	 * @return LayarPOIServer
	 */
	public function createLayarPOIServerFromSimpleXMLConfig(SimpleXMLElement $config) {
		$result = new LayarPOIServer();
		foreach ($config->xpath("layer") as $child) {
			$layer = new Layer((string)$child->name, $this->developerId, $this->developerKey);
			if ((string)$child->connector == "SQLPOIConnector") {
				$poiConnector = new SQLPOIConnector((string)$child->source->dsn, (string)$child->source->username, (string)$child->source->password);
			} else {
				$connectorName = (string)$child->connector;
				$poiConnector = new $connectorName((string)$child->source);
			}
			$layer->setPOIConnector($poiConnector);
			$result->addLayer($layer);
		}
		return $result;
	}

	/**
	 * Create a server from an array of LayerDefinitions
	 *
	 * @param LayerDefinition[] $definitions
	 * @return LayarPOIServer
	 */
	public function createLayarPOIServerFromLayerDefinitions(array $definitions) {
		$result = new LayarPOIServer();
		foreach ($definitions as $definition) {
			$layer = new Layer($definition->name, $this->developerId, $this->developerKey);
			if ($definition->getSourceType() == LayerDefinition::DSN) {
				$poiConnector = new $definition->connector($definition->source["dsn"], $definition->source["username"], $definition->source["password"]);
			} else {
				$poiConnector = new $definition->connector($definition->source);
			}
			foreach ($definition->connectorOptions as $optionName => $option) {
				$poiConnector->setOption($optionName, $option);
			}
			// for WebApi: pass full defnition object
			if (method_exists($poiConnector, 'initDefinition')) {
				$poiConnector->initDefinition($definition);
			}
			$layer->setPOIConnector($poiConnector);
			$result->addLayer($layer);
		}
		return $result;
	}

	/**
	 * Create a server from a PorPOISeConfig object
	 *
	 * @param PorPOISeConfig $config
	 * @return LayarPOIServer
	 */
	public static function createLayarPOIServer(PorPOISeConfig $config) {
		$factory = new self($config->developerID, $config->developerKey);
		return $factory->createLayarPOIServerFromLayerDefinitions($config->layerDefinitions);
	}
}

