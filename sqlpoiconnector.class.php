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
 * Requires POI classes
 */
require_once("poi.class.php");

/**
 * Requires POIConnector class
 */
require_once("poiconnector.class.php");

/**
 * Requires GeoUtils
 */
require_once("geoutil.class.php");

/**
 * POI connector from SQL databases
 *
 * @package PorPOISe
 */
class SQLPOIConnector extends POIConnector {
	/** @var string DSN */
	protected $source;
	/** @var string username */
	protected $username;
	/** @var string password */
	protected $password;
	/** @var PDO PDO instance */
	protected $pdo;

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

			if (!empty($filter->requestedPoiId) || !empty($filter->radius)) {
				$sql .= " HAVING";
				if (!empty($filter->requestedPoiId)) {
					$sql .= " id='" . addslashes($filter->requestedPoiId) . "'";
					if (!empty($filter->radius)) {
						$sql .= " AND";
					}
				}
				if (!empty($filter->radius)) {
					$sql .= " distance < (" . addslashes($filter->radius) . " + " . addslashes($filter->accuracy) . ")";
				}
 			}
			$sql .= " ORDER BY distance ASC";
		}

		return $sql;
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
		if (!empty($filter)) {
			$lat = $filter->lat;
			$lon = $filter->lon;
			$radius = $filter->radius;
			$accuracy = $filter->accuracy;
		}

		try {
			$pdo = $this->getPDO();
			$sql = $this->buildQuery($filter);
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
			$pois = array();
			while ($row = $stmt->fetch()) {
				$pois[] = $row;
			}
			foreach ($pois as $i => $poi) {
				$sql = "SELECT * FROM Action WHERE poiID=?";
				$stmt = $pdo->prepare($sql);
				$stmt->execute(array($poi["id"]));
				$pois[$i]["actions"] = array();
				while ($row = $stmt->fetch()) {
					$pois[$i]["actions"][] = $row;
				}
				$sql = "SELECT * FROM Object WHERE poiID=?";
				$stmt = $pdo->prepare($sql);
				$stmt->execute(array($poi["id"]));
				if ($row = $stmt->fetch()) {
					$pois[$i]["object"] = $row;
				}
				$sql = "SELECT * FROM Transform WHERE poiID=?";
				$stmt = $pdo->prepare($sql);
				$stmt->execute(array($poi["id"]));
				if ($row = $stmt->fetch()) {
					$pois[$i]["transform"] = $row;
				}
			}

			$result = array();
			foreach ($pois as $row) {
				if (empty($row["dimension"]) || $row["dimension"] == 1) {
					$poi = new POI1D($row);
				} else if ($row["dimension"] == 2) {
					$poi = new POI2D($row);
				} else if ($row["dimension"] == 3) {
					$poi = new POI3D($row);
				} else {
					throw new Exception("Invalid dimension: " . $row["dimension"]);
				}

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
				$tables = array("POI", "Action", "Object", "Transform");
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
		$sql = "DELETE FROM Object WHERE poiID=:poiID";
		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();
		$sql = "DELETE FROM Transform WHERE poiID=:poiID";
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
			if (empty($row["dimension"]) || $row["dimension"] == 1) {
				$poi = new POI1D($row);
			} else if ($row["dimension"] == 2) {
				$poi = new POI2D($row);
			} else if ($row["dimension"] == 3) {
				$poi = new POI3D($row);
			} else {
				throw new Exception("Invalid dimension: " . $row["dimension"]);
			}
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
		$poiFields = array("alt","attribution","dimension","id","imageURL","lat","lon","line2","line3","line4","relativeAlt","title","type","doNotIndex","showSmallBiw","showBiwOnClick");
		
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
			$sql = "INSERT INTO POI (" . implode(",", $poiFields) . ")
			        VALUES (:" . implode(",:", $poiFields) . ")";
		} else {
			$sql = "UPDATE POI SET ";
			$kvPairs = array();
			foreach ($poiFields as $poiField) {
				$kvPairs[] = sprintf("%s=:%s", $poiField, $poiField);
			}
			$sql .= implode(",", $kvPairs);
			$sql .= " WHERE id=:id";
		}

		$stmt = $pdo->prepare($sql);
		foreach ($poiFields as $poiField) {
			$stmt->bindValue(":" . $poiField, $poi->$poiField);
		}
		if (!$isNewPOI) {
			$stmt->bindValue(":id", $poi->id);
		}
		$stmt->execute();
		if (!isset($poi->id)) {
			$poi->id = $pdo->lastInsertId();
		}
		$this->saveActions($poi->id, $poi->actions);
		if ($poi->dimension > 1) {
			$this->saveObject($poi->id, $poi->object);
			$this->saveTransform($poi->id, $poi->transform);
		}
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
		$actionFields = array("uri", "label", "autoTriggerRange", "autoTriggerOnly");
		$pdo = $this->getPDO();

		// cleanup old
		$sql = "DELETE FROM Action WHERE poiID=:poiID";
		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();

		// insert new actions
		foreach ($actions as $action) {
			$sql = "INSERT INTO Action (poiID," . implode(",", $actionFields) . ") VALUES (:poiID,:" . implode(",:", $actionFields) . ")";
			$stmt = $pdo->prepare($sql);
			foreach ($actionFields as $actionField) {
				$stmt->bindValue(":" . $actionField, $action->$actionField);
			}
			$stmt->bindValue(":poiID", $poiID);
			$stmt->execute();
		}
	}

	/**
	 * Save an Object for a POI
	 *
	 * Deletes old Object if it exists
	 *
	 * @param int $poiID
	 * @param POIObject $object
	 * @return void
	 */
	protected function saveObject($poiID, POIObject $object) {
		$objectFields = array("baseURL", "full", "reduced", "icon", "size");
		$pdo = $this->getPDO();

		$sql = "DELETE FROM Object WHERE poiID=:poiID";
		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();
		
		$sql = "INSERT INTO Object (poiID," . implode(",", $objectFields) . ") VALUES (:poiID,:" . implode(",:", $objectFields) . ")";
		$stmt = $pdo->prepare($sql);
		foreach ($objectFields as $objectField) {
			$stmt->bindValue(":" . $objectField, $object->$objectField);
		}
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();
	}

	/**
	 * Save a Transform for a POI
	 *
	 * Deletes old Transform if it exists
	 *
	 * @param int $poiID
	 * @param POITransform $transform
	 * @return void
	 */
	protected function saveTransform($poiID, POITransform $transform) {
		$transformFields = array("angle", "rel", "scale");
		$pdo = $this->getPDO();
		
		$sql = "DELETE FROM Transform WHERE poiID=:poiID";
		$stmt = $pdo->prepare($sql);
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();
		
		$sql = "INSERT INTO Transform (poiID," . implode(",", $transformFields) . ") VALUES (:poiID,:" . implode(",:", $transformFields) . ")";
		$stmt = $pdo->prepare($sql);
		foreach ($transformFields as $transformField) {
			$stmt->bindValue(":" . $transformField, $transform->$transformField);
		}
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();
	}
}
