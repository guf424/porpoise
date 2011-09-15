<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 */

/**
 * Classes for Point of Interest definition
 *
 * @package PorPOISe
 */

/**
 * Subclasses of this class can all be converted to (associative) arrays
 * (useful for a.o. JSON-ing).
 *
 * @package PorPOISe
 */
abstract class Arrayable {
	/**
	 * Stores the contents of this object into an associative array
	 * with elements named after the members of the object. Members that
	 * contain properties are converted recursively.
	 *
	 * @return array
	 */
	public function toArray() {
		$result = array();
		$reflectionClass = new ReflectionClass($this);
		$reflectionProperties = $reflectionClass->getProperties();
		foreach ($reflectionProperties as $reflectionProperty) {
			$propertyName = $reflectionProperty->getName();
			$result[$propertyName] = $this->$propertyName;
			if (is_object($result[$propertyName])) {
				$result[$propertyName] = $result[$propertyName]->toArray();
			} else if (is_array($result[$propertyName])) {
				$result[$propertyName] = self::arrayToArray($result[$propertyName]);
			}
		}
		return $result;
	}

	/**
	 * Traverse an array recursively to call toArray on each object
	 *
	 * @return array
	 */
	protected function arrayToArray($array) {
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$array[$key] = self::arrayToArray($value);
			} else if (is_object($value)) {
				$array[$key] = $value->toArray();
			} else {
				$array[$key] = $value;
			}
		}
		return $array;
	}
}

/** Class to store an action. Can be a "whole layer" action or a POI action
 *
 * @package PorPOISe
 */
class Action extends Arrayable {
	/** Default action label. Only for flat files */
	/* LEGACY */
	const DEFAULT_ACTION_LABEL = "Do something funky";

	/** @var string URI that should be invoked by activating this action */
	public $uri = NULL;
	/** @var string Label to show in the interface */
	public $label = NULL;
	/** @var string Content type */
	public $contentType = NULL;
	/** @var string HTTP method */
	public $method = "GET";
	/** @var int Activity type. Possible types are currently undocumented */
	public $activityType = NULL;
	/** @var string[] Which parameters to include in the call */
	public $params = array();
	/** @var bool Close the BIW after the action has finished */
	public $closeBiw = FALSE;
	/** @var bool Show activity indicator while action completes */
	public $showActivity = TRUE;
	/** @var string Message to show instead of default spinner */
	public $activityMessage = NULL;


	/**
	 * Constructor
	 *
	 * If $source is a string, it must be a URI and a default label will be
	 * assigned to it, no other properties will be changed.
	 * If $source is an array or an object all relevent properties will
	 * be extracted from it.
	 *
	 * @param mixed $source
	 */
	public function __construct($source = NULL) {
		if (empty($source)) {
			return;
		}
		$optionalFields = array("contentType", "method", "activityType", "params", "closeBiw", "showActivity", "activityMessage");

		if (is_string($source)) {
			$this->label = self::DEFAULT_ACTION_LABEL;
			$this->uri = $source;
		} else if (is_array($source)) {
			$this->label = $source["label"];
			$this->uri = $source["uri"];
			foreach ($optionalFields as $field) {
				if (isset($source[$field])) {
					switch($field) {
					case "activityType":
						$this->$field = (int)$source[$field];
						break;
					case "closeBiw":
					case "showActivity":
            $this->$field = (bool)(string)$source[$field];
            break;
					case "params":
						$value = (string)$source[$field];
						if (!empty($value)) {
							$this->$field = explode(",", $value);
						}
						break;
					default:
						$this->$field = (string)$source[$field];
						break;
					}
				}
			}
		} else {
			$this->label = (string)$source->label;
			$this->uri = (string)$source->uri;
			foreach ($optionalFields as $field) {
				if (isset($source->$field)) {
					switch($field) {
					case "activityType":
						$this->$field = (int)$source->$field;
						break;
					case "closeBiw":
					case "showActivity":
            $this->$field = (bool)(string)$source->$field;
            break;
					case "params":
						$value = (string)$source->$field;
						if (!empty($value)) {
							$this->$field = explode(",", $value);
						}
						break;
					default:
						$this->$field = (string)$source->$field;
						break;
					}
				}
			}
		}
	}
}

/**
 * Class to store a POI action
 *
 * @package PorPOISe
 */
class POIAction extends Action {
	/** @var int Range for action autotrigger */
	public $autoTriggerRange = NULL;
	/** @var bool Only act on autotrigger */
	public $autoTriggerOnly = FALSE;
	/** @var bool Auto trigger this action. ONLY effective when applied to a referenceImage */
	public $autoTrigger = FALSE;

	/**
	 * Constructor
	 *
	 * If $source is a string, it must be a URI and a default label will be
	 * assigned to it
	 * If $source is an array it is expected to contain elements "label"
	 * and "uri".
	 * If $source is an object, it is expected to have members "label" and
	 * "uri".
	 *
	 * @param mixed $source
	 */
	public function __construct($source = NULL) {

		if (empty($source)) {
			return;
		}

		parent::__construct($source);

		if (is_string($source)) {
			return;
		} else if (is_array($source)) {
			if (!empty($source["autoTriggerRange"])) {
				$this->autoTriggerRange = (int)$source["autoTriggerRange"];
      }
      if (!empty($source["autoTriggerOnly"])) {
				$this->autoTriggerOnly = (bool)$source["autoTriggerOnly"];
			}
      if (!empty($source["autoTrigger"])) {
			  $this->autoTrigger = (bool)$source["autoTrigger"];
      }
		} else {
			if (!empty($source->autoTriggerRange)) {
				$this->autoTriggerRange = (int)$source->autoTriggerRange;
      }
			if (!empty($source->autoTriggerOnly)) {
				$this->autoTriggerOnly = (bool)(string)$source->autoTriggerOnly;
			}
			if (!empty($source->autoTrigger)) {
			  $this->autoTrigger = (bool)(string)$source->autoTrigger;
      }
		}
	}
}


/**
 * Holds transformation information for multi-dimensional POIs
 *
 * @package PorPOISe
 */
class POITransform extends Arrayable {
	/** @var array Contains the rel, angle and axis values */
	public $rotate = array();
	/** @var array Contains the x, y and z values */
	public $translate = array();
	/** @var float Scaling factor */
	public $scale = 1;

	/**
	 * Constructor
	 */
	public function __construct($source = NULL) {
		if (empty($source)) {
			return;
		}

		$this->rotate['axis']=array();

		if (is_array($source)) {
			$this->rotate['rel'] = (bool)$source["rotate"]["rel"];
			$this->rotate['angle'] = (float)$source["rotate"]["angle"];
			$this->rotate['axis']['x'] = (float)$source["rotate"]["axis"]["x"];
			$this->rotate['axis']['y'] = (float)$source["rotate"]["axis"]["y"];
			$this->rotate['axis']['z'] = (float)$source["rotate"]["axis"]["z"];
			$this->translate['x'] = (float)$source["translate"]["x"];
			$this->translate['y'] = (float)$source["translate"]["y"];
			$this->translate['z'] = (float)$source["translate"]["z"];
			$this->scale = (float)$source["scale"];
		} else {
			if (!empty($source->rotate)) {
				$this->rotate['rel'] = (bool)(string)$source->rotate->rel;//) ? true : false;	/* SimpleXMLElement objects always get cast to TRUE even when representing an empty element */
				$this->rotate['angle'] = (float)$source->rotate->angle;
				if (isset($source->rotate->axis)) {
					$this->rotate['axis']['x'] = (float)$source->rotate->axis->x;
					$this->rotate['axis']['y'] = (float)$source->rotate->axis->y;
					$this->rotate['axis']['z'] = (float)$source->rotate->axis->z;
				}
			}
			if (!empty($source->translate)) {
				if (isset($source->translate->x)) {
					$this->translate['x'] = (float)$source->translate->x;
					$this->translate['y'] = (float)$source->translate->y;
					$this->translate['z'] = (float)$source->translate->z;
				}
				$this->scale = (float)$source->scale;
			}
		}
	}
}

/**
 * Holds icon information for POIs
 *
 * @package PorPOISe
 */
class POIIcon extends Arrayable {
        /** @var string Full URL to the icon  */
        public $url = NULL;
		/** @var int POI iconset */
		public $type = NULL;

	/**
	 * Constructor
	 */
	public function __construct($source = NULL) {
		if (empty($source)) {
			return;
		}
		if (is_array($source)) {
            if (!empty($source["url"])) $this->url = (string)$source["url"];
			if (!empty($source["type"])) $this->type = (int)$source["type"];
		} else {
            $this->url = (string)$source->url;
			$this->type = (int)$source->type;
		}
	}
}
/**
 * Holds Descriptive information for POIs
 *
 * @package PorPOISe
 */
class POIText extends Arrayable {
	/** @var string Description of POI */
	public $description = NULL;
	/** @var string footnote text */
	public $footnote = NULL;
	/** @var array Title */
	public $title = NULL;

	/**
	 * Constructor
	 */
	public function __construct($source = NULL) {
		if (empty($source)) {
			return;
		}
		if (is_array($source)) {
            $this->footnote = (string)$source["footnote"];
			$this->title = (string)$source["title"];
			$this->description = (string)$source["description"];
		} else {
            $this->footnote = (string)$source->footnote;
			$this->title = (string)$source->title;
			$this->description = (string)$source->description;
		}
	}
}


/**
 * Holds POI anchor information
 *
 * @package PorPOISe
 */
class POIAnchor extends Arrayable {
	    /** @var string Reference Image keyname */
        public $referenceImage = '';
        /** @var array Geolocation object */
        public $geolocation = array();

	/**
	 * Constructor
	 */
	public function __construct($source = NULL) {
		if (empty($source)) {
			return;
		}
		if (is_array($source)) {
            $this->geolocation['lat'] = (float)$source["geolocation"]["lat"];
			$this->geolocation['lon'] = (float)$source["geolocation"]["lon"];
			$this->geolocation['alt'] = (float)$source["geolocation"]["alt"];
			$this->referenceImage = (string)$source["referenceImage"];
		} else {
			$this->geolocation['lat'] = (float)$source->geolocation->lat;
			$this->geolocation['lon'] = (float)$source->geolocation->lon;
			$this->geolocation['alt'] = (float)$source->geolocation->alt;
			$this->referenceImage = (string)$source->referenceImage;
		}
	}
}




/**
 * Class for storing 2D/3D object information
 *
 * @package PorPOISe
 */
class POIObject extends Arrayable {
	/** @var string Filename of the full size object (Specify complete URL)*/
	public $url;
	/** @var string Filename of a pre-scaled reduced object */
	public $reducedURL = NULL;
	/** @var string Content type of the resouce */
	public $contentType = 'image/vnd.layar.generic';
	/** @var float Size of the object in meters, i.e. the length of the smallest cube that can contain the object */
	public $size;

	/**
	 * Constructor
	 */
	public function __construct($source = NULL) {
		if (empty($source)) {
			return;
		}

		if (is_array($source)) {
			$this->url = $source["url"];
			if (!empty($source["reducedURL"])) {
				$this->reducedURL = $source["reducedURL"];
			}
			if (!empty($source["contentType"])) {
				$this->contentType = $source["contentType"];
			}
			$this->size = (float)$source["size"];
		} else {
			foreach (array("url", "reducedURL", "size", "contentType") as $fieldName) {
				switch ($fieldName) {
				case "url":
				case "reducedURL":
				case "contentType":
					if (empty($source->$fieldName)) {
						break;
					}
					$this->$fieldName = (string)$source->$fieldName;
					break;
				case "size":
					$this->size = (float)$source->size;
					break;
				}
			}
		}
	}
}

/**
 * Class for storing an animation definition
 *
 * @package PorPOISe
 */
class Animation extends Arrayable {
	/** @var string type of animation */
	public $type;
	/** @var int length of the animation in milliseconds */
	public $length;
	/** @var int delay in milliseconds before the animation starts */
	public $delay = NULL;
	/** @var string interpolation to apply */
	public $interpolation = NULL;
	/** @var float interpolation parameter */
	public $interpolationParam = NULL;
	/** @var bool persist post-state when animation completes */
	public $persist = FALSE;
	/** @var bool repeat the animation */
	public $repeat = FALSE;
	/** @var float modifier for start state */
	public $from = NULL;
	/** @var float to modifier for end state */
	public $to = NULL;
	/** @var vector (x,y,z assoicative array) axis for the animation */
	public $axis = array("x" => NULL, "y" => NULL, "z" => NULL);

	public function axisString() {
		if ($this->axis["x"] === NULL || $this->axis["y"] === NULL || $this->axis["z"] === NULL) {
			return "";
		}
		return sprintf("%s,%s,%s", $this->axis["x"], $this->axis["y"], $this->axis["z"]);
	}

	public function set($key, $value) {
		switch($key) {
		case "axis":
			if (is_array($value)) {
				$this->axis = $value;
			} else {
				$axisValues = explode(",", (string)$value);
				foreach ($axisValues as $k => $v) {
					$axisValues[$k] = (float)$v;
				}
				if (count($axisValues) != 3) {
					$axisValues = array(NULL, NULL, NULL);
				}
				$this->axis = array_combine(array("x","y","z"), $axisValues);
			}
			break;
		case "type":
		case "interpolation":
			$this->$key = (string)$value;
			break;
		case "length":
		case "delay":
			$this->$key = (int)$value;
			break;
		case "interpolationParam":
		case "from":
		case "to":
			$this->$key = (float)$value;
			break;
		case "persist":
		case "repeat":
			$this->$key = (bool)(string)$value;
			break;
		}
	}

	public function __construct($source = NULL) {
		if (empty($source)) {
			return;
		}

		if (is_array($source)) {
			foreach ($this as $key => $value) {
				// $value is not relevant here
				$this->set($key, $source[$key]);
			}
		} else {
			foreach ($this as $key => $value) {
				// $value is not relevant here
				$this->set($key, $source->$key);
			}
		}
	}
}

/**
 * Class for storing POI information
 *
 * Subclasses should define a "dimension" property or they will
 * always be interpreted by Layar as 1-dimensional points.
 *
 * @package PorPOISe
 */
class POI extends Arrayable {
	/** @var POIAction[] Possible actions for this POI */
	public $actions = array();
	/** @var anchor[] Anchor info for this POI */
	public $anchor = array();
	/** @var Animation[] Animations for this POI */
	public $animations = array("onCreate" => array(), "onUpdate" => array(), "onDelete" => array(), "onFocus" => array(), "onClick" => array());
	/** @var string biwStyle defines whether the BIW should be the "classic" (common for common POIs) or "collapsed" (The default for Feature tracked POIs) or null to make the client decide */
	public $biwStyle = null;
	/** @var int Distance in meters between the user and this POI */
	public $distance = NULL;
	/** @var bool doNotIndex */
	public $doNotIndex = FALSE;
	/** @var array Contains icon details */
	public $icon = array();
	/** @var string Identifier for this POI */
	public $id = NULL;
	/** @var string URL of an image to show for this POI */
	public $imageURL = NULL;
	/** @var bool inFocus */
	public $inFocus = FALSE;
	/** @var POIObject Object specification */
	public $object;
	/** @var bool Show the small BIW on the bottom of the screen */
	public $showSmallBiw = TRUE;
	/** @var show the big BIW when the POI is tapped */
	public $showBiwOnClick = TRUE;
	/** @var array Contains all textual descriptive information (title, description, footnote) */
	public $text = array();
	/** @var POITransform Transformation specification */
	public $transform;

	/**
	 * Constructor
	 *
	 * $source is expected to be an array or an object, with element/member
	 * names corresponding to the member names of POI. This allows both
	 * constructing from an associative array as well as copy constructing.
	 *
	 * @param mixed $source
	 */
	public function __construct($source = NULL) {
		if (!empty($source)) {
			$reflectionClass = new ReflectionClass($this);
			$reflectionProperties = $reflectionClass->getProperties();
			foreach ($reflectionProperties as $reflectionProperty) {
				$propertyName = $reflectionProperty->getName();
				if (is_array($source)) {
					if (isset($source[$propertyName])) {
						if ($propertyName == "actions") {
							$value = array();
							foreach ($source["actions"] as $sourceAction) {
								$value[] = new POIAction($sourceAction);
							}
						} else if ($propertyName == "anchor") {
							$value = new POIAnchor($source["anchor"]);
						} else if ($propertyName == "animations") {
							$value = array("onCreate" => array(), "onUpdate" => array(), "onDelete" => array(), "onFocus" => array(), "onClick" => array());
							foreach ($source["animations"] as $event => $animations) {
								foreach ($animations as $animation) {
									$value[$event][] = new Animation($animation);
								}
							}
						} else if ($propertyName == "icon") {
							$value = new POIIcon($source["icon"]);
						} else if ($propertyName == "object") {
							$value = new POIObject($source["object"]);
						} else if ($propertyName == "text") {
							$value = new POIText($source["text"]);
						} else if ($propertyName == "transform") {
							$value = new POITransform($source["transform"]);
						} else {
							switch ($propertyName) {
								case "showSmallBiw":
								case "showBiwOnClick":
								case "disableClueMenu":
								case "doNotIndex":
									$value = (bool)(string)$source[$propertyName];
								break;
								default:
									$value = (string)$source[$propertyName];
								break;
							}
						}
						$this->$propertyName = $value;
					}
				} else {
					if (isset($source->$propertyName)) {
						if ($propertyName == "actions") {
							$value = array();
							foreach ($source->actions as $sourceAction) {
								$value[] = new POIAction($sourceAction);
							}
						} else if ($propertyName == "animations") {
							$value = array("onCreate" => array(), "onUpdate" => array(), "onDelete" => array(), "onFocus" => array(), "onClick" => array());
							foreach ($source->animations as $event => $animations) {
								foreach ($animations as $animation) {
									$value[$event][] = new Animation($animation);
								}
							}
						} else if ($propertyName == "object") {
							$value = new POIObject($source->object);
						} else if ($propertyName == "text") {
							$value = new POIText($source->text);
						} else if ($propertyName == "anchor") {
							$value = new POIAnchor($source->anchor);
						} else if ($propertyName == "transform") {
							$value = new POITransform($source->transform);
						} else {
							switch ($propertyName) {
							case "dimension":
							case "alt":
								$value = (int)$source->$propertyName;
								break;
							case "lat":
							case "lon":
								$value = (float)$source->$propertyName;
								break;
							case "showSmallBiw":
							case "showBiwOnClick":
							case "doNotIndex":
								$value = strtolower((string)$source->$propertyName) == 'true' ? true : false;
								break;
							default:
								$value = (string)$source->$propertyName;
								break;
							}
						}
						$this->$propertyName = $value;
					}
				}
			}
		}
	}
}

