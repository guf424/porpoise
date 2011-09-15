<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 *
 * Acknowledgments:
 * Jerouris for the UTF-8 fix
 */

/**
 * POI connector from SQL databases
 *
 * @package PorPOISe
 */

/**
 * POI connector from SQL databases
 *
 * @package PorPOISe
 */
class SQLPOIConnector extends POIConnector  {
	/** @var string DSN */
	protected $source;
	/** @var string username */
	protected $username;
	/** @var string password */
	protected $password;
	/** @var PDO PDO instance */
	protected $pdo = NULL;

	/**
	 * Constructor
	 *
	 * The field separator can be configured by modifying the public
	 * member $separator.
	 *
	 * @param string $source DSN of the database
	 * @param string $username Username to access the database
	 * @param string $password Password to go with the username
	 */
	public function __construct($source, $username = "", $password = "") {
		$this->source = $source;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Get PDO instance
	 *
	 * @return PDO
	 */
	protected function getPDO() {
		if (empty($this->pdo)) {
			$this->pdo = new PDO ($this->source, $this->username, $this->password);

			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// force UTF-8 (Layar talks UTF-8 and nothing else)
			$sql = "SET NAMES 'utf8'";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
		}
		return $this->pdo;
	}

	/**
	 * Build SQL query based on $filter
	 *
	 * @param Filter $filter
	 *
	 * @return string
	 */
	protected function buildQuery(Filter $filter = NULL) {
		/** @var array Contains all "WHERE" clauses for the query */
		$aWhereClauses = array();
		$aOrderBy = array();

		// Start with the minimal query
		$sql = 'SELECT P.*';

		// If the filter requires so, add distance calculation
		if ($filter->lat !== NULL && $filter->lon !== NULL) {
			$sql .= " , " . GeoUtil::EARTH_RADIUS . " * 2 * asin(sqrt(pow(sin((radians(" . addslashes($filter->lat) . ") - radians(anchor_geolocation_lat)) / 2), 2)+cos(radians(" . addslashes($filter->lat) . ")) * cos(radians(anchor_geolocation_lat)) * pow(sin((radians(" . addslashes($filter->lon) . ") - radians(anchor_geolocation_lon)) / 2), 2))) AS distance";
			$aOrderBy[]='distance';
		}

		$sql.=' FROM POI P , Layer L';
		$aWhereClauses[]='P.LayerID = L.id';

		if ($filter->layerName) {
			$aWhereClauses[]="L.Layer='".$filter->layerName."'";
		}

		/*if (!empty($filter->requestedPoiId)) {
			$aWhereClauses[]="id='" . addslashes($filter->requestedPoiId) . "'";
  	}*/
		if (count($aWhereClauses)>0) {
			$sql .= " WHERE ".implode(' AND ', $aWhereClauses);
		}
		if (!empty($filter->radius) && ($filter->lat !== NULL && $filter->lon !== NULL)) {
			$sql .= ' HAVING ( distance<('.addslashes($filter->radius).'+'.addslashes($filter->accuracy).') OR anchor_referenceImage!="" )';
		}
		if (count($aOrderBy)>0) {
			$sql .= ' ORDER BY '.implode(',', $aOrderBy).' ASC';
		}

		return $sql;

	}

  /**
   * Convert a row from the database into a multidimension POI array
   *
   * @param array $row
   *
   * @return array
   */
  protected static function poiRowToPOIDict($row) {
		$row['anchor']=array();
		$row['icon']=array();
		$row['text']=array();
		$row['transform']=array();
		$row['object']=array();
		$row = Util::simpleArrayToMultiDimArray($row);
    return $row;
  }



	/**
	 * Get POIs
	 *
	 * @param Filter $filter
	 *
	 * @return POI[]
	 *
	 * @throws Exception
	 */
	public function getPOIs(Filter $filter = NULL) {
		try {
			$pdo = $this->getPDO();
			$sql = $this->buildQuery($filter);
			$stmt = $pdo->prepare($sql);
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			$stmt->execute();
			$pois = array();
			while ($row = $stmt->fetch()) {
				$pois[] = $row;
			}

			foreach ($pois as $i => $poi) {
				$sql = "SELECT * FROM Action WHERE poiId=?";
				$stmt = $pdo->prepare($sql);
				$stmt->setFetchMode(PDO::FETCH_ASSOC);
				$stmt->execute(array($poi["id"]));
				$pois[$i]["actions"] = array();
				while ($row = $stmt->fetch()) {
					$pois[$i]["actions"][] = $row;
				}

				$sql = "SELECT * FROM Animation WHERE poiId=?";
				$stmt = $pdo->prepare($sql);
				$stmt->setFetchMode(PDO::FETCH_ASSOC);
				$stmt->execute(array($poi["id"]));
				$pois[$i]["animations"] = array("onCreate" => array(), "onUpdate" => array(), "onDelete" => array(), "onFocus" => array(), "onClick" => array());
				while ($row = $stmt->fetch()) {
					$pois[$i]["animations"][$row["event"]][] = $row;
				}
			}

			foreach ($pois as $i => $poi) {
        $pois[$i] = self::poiRowToPOIDict($poi);
			}
			$result = array();
			foreach ($pois as $row) {
				$poi = new POI($row);

				if (!empty($filter) && !empty($filter->requestedPoiId) && $filter->requestedPoiId == $poi["id"]) {
					// always return the requested POI at the top of the list to
					// prevent cutoff by the 50 POI response limit
					array_unshift($result, $poi);
				} else if ($this->passesFilter($poi, $filter)) {
					$result[] = $poi;
				}
			}

			return $result;
		} catch (PDOException $e) {
			throw new Exception("Database error: " . $e->getMessage());
		}
	}

	/**
	 * Return a Layar response
	 *
	 * @param Filter $filter
	 *
	 * @return LayarResponse
	 */
	public function getLayarResponse(Filter $filter = NULL) {
		$pdo = $this->getPDO();

		$result = new LayarResponse();

		$sql = "SELECT * FROM Layer";
		if (!empty($filter->layerName)) {
			$sql .= " WHERE layer=:layerName";
		}

		$stmt = $pdo->prepare($sql);
		if (!empty($filter)) {
			$stmt->bindValue(":layerName", $filter->layerName);
		}
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		$stmt->execute();
		if ($row = $stmt->fetch()) {
			// no entry means use default
			foreach($row as $name => $value) {
				switch($name) {
				case "refreshInterval":
				case "refreshDistance":
					$result->$name = (int)$value;
					break;
				case "fullRefresh":
					$result->$name = (bool)((string)$value);
					break;
				case "showMessage":
				case "biwStyle":
					$result->$name = (string)$value;
					break;
				default:
					// not relevant
					break;
				}
			}
		}
		$result->actions = $this->getLayerActions($filter);
		$result->animations = $this->getLayerAnimations($filter);
		$result->hotspots = $this->getPOIs($filter);

		return $result;
	}

	/**
	 * Get a layer's actions
	 *
	 * @param Filter $filter
	 *
	 * @return Action[]
	 */
	protected function getLayerActions(Filter $filter = NULL) {
		$pdo = $this->getPDO();
		$sql = "SELECT * FROM Action WHERE poiId IS NULL";	/** @todo nasty, gotta fix that later */
		$stmt = $pdo->prepare($sql);
		$stmt->setFetchMode(PDO::FETCH_ASSOC);
		$stmt->execute();
		$result = array();
		while ($row = $stmt->fetch()) {
			$result[] = new Action($row);
		}
		return $result;
	}

	/**
	 * Get a layer's animations
	 *
	 * @param Filter $filter
	 *
	 * @return Animation[][]
	 */
	protected function getLayerAnimations(Filter $filter = NULL) {
		$pdo = $this->getPDO();
		$sql = "SELECT * FROM Animation WHERE poiId IS NULL";	/** @todo nasty, gotta fix that later */
		$stmt = $pdo->prepare($sql);
		$stmt->execute();
		$result = array("onCreate" => array(), "onUpdate" => array(), "onDelete" => array(), "onFocus" => array(), "onClick" => array());
		while ($row = $stmt->fetch()) {
			$result[$row["event"]][] = new Animation($row);
		}
		return $result;
	}

	/**
	 * Store POIs
	 *
	 * @param POI[] $pois
	 * @param string $mode "update" or "replace"
	 *
	 * @return bool TRUE on success
	 * @throws Exception on database errors
	 */
	public function storePOIs(array $pois, $mode = "update") {
		try {
			$pdo = $this->getPDO();

			if ($mode == "replace") {
				// cleanup!
				$tables = array("POI", "Action", "Animation");
				foreach ($tables as $table) {
					$sql = "DELETE FROM " . $table;
					$stmt = $pdo->prepare($sql);
					$stmt->execute();
				}

				// blindly insert everything
				foreach ($pois as $poi) {
					$this->savePOI($poi);
				}
			} else {
				foreach ($pois as $poi) {
					$this->savePOI($poi);
				}
			}
			return TRUE;
		} catch (PDOException $e) {
			throw new Exception("Database error: " . $e->getMessage());
		}
	}

	/**
	 * Save layer properties
	 *
	 * Note: uses LayarResponse as transport for properties but will not
	 * save the contents of $properties->hotspots. Use storePOIs for that
	 *
	 * @param LayarResponse $properties
	 * @param bool $asString
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function storeLayerProperties(LayarResponse $response) {
		$layerFields = array("layer", "refreshInterval", "refreshDistance", "fullRefresh", "showMessage", "biwStyle");
		try {
			// let's find out if this layer already exists
			$newLayer = TRUE;
			$pdo = $this->getPDO();
			$sql = "SELECT * FROM Layer WHERE layer=?";
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array($response->layer));
			if ($stmt->fetch()) {
				$newLayer = FALSE;
				$sql = "UPDATE Layer SET ";
				$ar = array();
				foreach ($layerFields as $layerField) {
					if ($layerField != "layer") {
						$ar[] = sprintf("%s=:%s", $layerField, $layerField);
					}
				}
				$sql .= implode(",", $ar);
				$sql .= " WHERE layer=:layer";
			} else {
				$sql = "INSERT INTO Layer (";
				$sql .= implode(",", $layerFields);
				$sql .= ") VALUES (:";
				$sql .= implode(",:", $layerFields);
				$sql .= ")";
			}
			$stmt->closeCursor();
			$stmt = $pdo->prepare($sql);
			foreach ($layerFields as $layerField) {
				$stmt->bindValue(":" . $layerField, $response->$layerField);
			}
			$stmt->execute();

			$this->saveActions(NULL, $response->actions);
			$this->saveAnimations(NULL, $response->animations);

		} catch (PDOException $e) {
			throw new Exception("Database error: " . $e->getMessage());
		}
	}

	/**
	 * Delete a POI
	 *
	 * @param string $poiID
	 *
	 * @return void
	 *
	 * @throws Exception When the POI does not exist
	 */
	public function deletePOI($poiID) {
		$poi = self::getPOIByID($poiID);
		if (empty($poi)) {
			throw new Exception(sprintf("Could not delete POI: no POI found with ID %s", $poiID));
		}

		$pdo = self::getPDO();
		$sql = "DELETE FROM Action WHERE poiID=:poiID";
		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();
		$sql = "DELETE FROM POI WHERE id=:id";
		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(":id", $poiID);
		$stmt->execute();
	}

	/**
	 * Get a POI by its id
	 *
	 * @param int $id
	 * @return POI
	 */
	protected function getPOIByID($id) {
		$pdo = $this->getPDO();
		$sql = "SELECT * FROM POI WHERE id=:id";
		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(":id", $id);
		$stmt->execute();
		if ($row = $stmt->fetch()) {
      $row = self::poiRowToPOIDict($row);
      $poi = new POI($row);
		}
		return $poi;
	}

	/**
	 * Save a POI
	 *
	 * Replaces old POI with same id
	 *
	 * @param POI $poi
	 * @return void
	 */
	protected function savePOI(POI $poi) {
		$pdo = $this->getPDO();

		$flatPoiData = Array();
		foreach (array('doNotIndex', 'imageURL', 'showBiwOnClick', 'showSmallBiw') as $poiField) {
			$flatPoiData[$poiField] = $poi->$poiField;
		}

		// Certain subobjects of the POI object do not have a 1-to-n relationship with the actual POI so they are stored with the POI itself.
		// Therefor "anchor","text" and "icon" are being "flattened" to normal.
		$flatPoiData['anchor_geolocation_lat'] = $poi->anchor->geolocation['lat'];
		$flatPoiData['anchor_geolocation_lon'] = $poi->anchor->geolocation['lon'];
		$flatPoiData['anchor_geolocation_alt'] = $poi->anchor->geolocation['alt'];
		$flatPoiData['anchor_referenceImage'] = $poi->anchor->referenceImage;
		$flatPoiData['text_title'] = $poi->text->title;
		$flatPoiData['text_description'] = $poi->text->description;
		$flatPoiData['text_footnote'] = $poi->text->footnote;
		$flatPoiData['icon_type'] = $poi->icon->type;
		$flatPoiData['icon_url'] = $poi->icon->url;
		$flatPoiData['object_url'] = $poi->object->url;
		$flatPoiData['object_reducedURL'] = $poi->object->reducedURL;
		$flatPoiData['object_size'] = $poi->object->size;
		$flatPoiData['object_contentType'] = $poi->object->contentType;
		$flatPoiData['transform_rotate_angle'] = $poi->transform->rotate['angle'];
		$flatPoiData['transform_scale'] = $poi->transform->scale;
		$flatPoiData['transform_rotate_rel'] = $poi->transform->rotate['rel'];
		$flatPoiData['transform_rotate_axis_x'] = $poi->transform->rotate['axis']['x'];
		$flatPoiData['transform_rotate_axis_y'] = $poi->transform->rotate['axis']['y'];
		$flatPoiData['transform_rotate_axis_z'] = $poi->transform->rotate['axis']['z'];
		$flatPoiData['transform_translate_x'] = $poi->transform->translate['x'];
		$flatPoiData['transform_translate_y'] = $poi->transform->translate['y'];
		$flatPoiData['transform_translate_z'] = $poi->transform->translate['z'];


		// is this a new POI or not?
		$isNewPOI = TRUE;
		if (isset($poi->id)) {
			$oldPOI = $this->getPOIByID($poi->id);
			if (!empty($oldPOI)) {
				$isNewPOI = FALSE;
			}
		}

		// build update or insert SQL string
		if ($isNewPOI) {
			// Fetch the layer ID that matches with the POI's layerName
			$layerID = $this->fetchLayerIdForName($poi->layerName);
			// Create the SQL query that inserts the new POI.
			$sql = "INSERT INTO POI (" . implode(",", array_keys($flatPoiData)) . ", layerID)
			        VALUES (:" . implode(",:", array_keys($flatPoiData)) . ", :layerID)";
		} else {
			$sql = "UPDATE POI SET ";
			$kvPairs = array();
			foreach (array_keys($flatPoiData) as $sKey) {
				$kvPairs[] = sprintf("%s=:%s", $sKey, $sKey);
			}

			$sql .= implode(",", $kvPairs);
			$sql .= " WHERE id=:id";
		}

		$stmt = $pdo->prepare($sql);

		if ($isNewPOI) $stmt->bindValue(":layerID", $layerID);
		foreach ($flatPoiData as $sKey =>$mValue) {
			$stmt->bindValue(":" . $sKey, $mValue);
		}
		if (!$isNewPOI) {
			$stmt->bindValue(":id", $poi->id);
		}
		$stmt->execute();

		if ($isNewPOI) {
			$poi->id = $pdo->lastInsertId();
		}

		$this->saveActions($poi->id, $poi->actions);
		$this->saveAnimations($poi->id, $poi->animations);
	}

	private function fetchLayerIdForName($sLayerName) {
		if (!empty($sLayerName)) {
			$pdo = $this->getPDO();
			$sql = 'SELECT id FROM Layer WHERE layer=:sLayerName';
			$stmt = $pdo->prepare($sql);
			$stmt->bindValue(":sLayerName", $sLayerName);
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			$stmt->execute();
			if ($stmt->rowCount() > 0) {
				$row = $stmt->fetch();
				return (int)$row['id'];
			}
		}
		return false;
	}

	/**
	 * Save actions for a POI
	 *
	 * Replaces all previous actions for this POI
	 *
	 * @param int $poiID
	 * @param POIAction[] $actions
	 * @return void
	 */
	protected function saveActions($poiID, array $actions) {
		$actionFields = array("uri", "label", "autoTriggerRange", "autoTriggerOnly", "autoTrigger", "contentType", "method", "activityType", "params", "closeBiw", "showActivity", "activityMessage");
		$pdo = $this->getPDO();

		// cleanup old
		if ($poiID !== NULL) {
			$sql = "DELETE FROM Action WHERE poiId=:poiId";
			$stmt = $pdo->prepare($sql);
			$stmt->bindValue(":poiId", $poiID);
		} else {
			$sql = "DELETE FROM Action WHERE poiId IS NULL";
			$stmt = $pdo->prepare($sql);
		}
		$stmt->execute();

		// insert new actions
		foreach ($actions as $action) {
			$sql = "INSERT INTO Action (poiID," . implode(",", $actionFields) . ") VALUES (:poiID,:" . implode(",:", $actionFields) . ")";
			$stmt = $pdo->prepare($sql);
			foreach ($actionFields as $actionField) {
				if ($actionField == "params") {
					$stmt->bindValue(":" . $actionField, implode(",", $action->$actionField));
				} else {
					$stmt->bindValue(":" . $actionField, $action->$actionField);
				}
			}
			$stmt->bindValue(":poiID", $poiID);
			$stmt->execute();
		}
	}

	/**
	 * Save animations for a POI
	 *
	 * Replaces all previous animations for this POI
	 *
	 * @param int $poiID
	 * @param POIAnimation[][] $animations
	 * @return void
	 */
	protected function saveAnimations($poiID, array $animations) {
		$animationFields = array("event", "type", "length", "delay", "interpolation", "interpolationParam", "persist", "`repeat`", "`from`", "`to`", "axis");
		$pdo = $this->getPDO();

		// cleanup old
		if ($poiID !== NULL) {
			$sql = "DELETE FROM Animation WHERE poiId=:poiId";
			$stmt = $pdo->prepare($sql);
			$stmt->bindValue(":poiId", $poiID);
		} else {
			$sql = "DELETE FROM Animation WHERE poiId IS NULL";
			$stmt = $pdo->prepare($sql);
		}
		$stmt->execute();

		// insert new animations
		foreach ($animations as $event => $subAnimations) {
			foreach ($subAnimations as $animation) {
				$sql = "INSERT INTO Animation (poiID,event,type,length,delay,interpolation,interpolationParam,persist,`repeat`,`from`,`to`,axis) VALUES (:poiID,:event,:type,:length,:delay,:interpolation,:interpolationParam,:persist,:repeat,:from,:to,:axis)";
				$stmt = $pdo->prepare($sql);
				$stmt->bindValue(":event", $event);
				$stmt->bindValue(":type", $animation->type);
				$stmt->bindValue(":length", $animation->length);
				$stmt->bindValue(":delay", $animation->delay);
				$stmt->bindValue(":interpolation", $animation->interpolation);
				$stmt->bindValue(":interpolationParam", $animation->interpolationParam);
				$stmt->bindValue(":persist", $animation->persist);
				$stmt->bindValue(":repeat", $animation->repeat);
				$stmt->bindValue(":from", $animation->from);
				$stmt->bindValue(":to", $animation->to);
				$stmt->bindValue(":axis", $animation->axisString());

				$stmt->bindValue(":poiID", $poiID);
				$stmt->execute();
			}
		}
	}



	public function setOption($name, $value) {
		// dummy
	}
}
