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
 * POI collector from SQL databases
 *
 * @package PorPOISe
 */

/**
 * Requires POI classes
 */
require_once("poi.class.php");

/**
 * Requires POICollector interface
 */
require_once("poicollector.interface.php");

/**
 * Requires GeoUtils
 */
require_once("geoutil.class.php");

/**
 * POI collector from SQL databases
 *
 * @package PorPOISe
 */
class SQLPOICollector implements POICollector {
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
			// force UTF-8 (Layar talks UTF-8 and nothing else)
			$sql = "SET NAMES 'utf8'";
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute();
		}
		return $this->pdo;
	}

	/**
	 * Get POIs
	 *
	 * @param float $lat
	 * @param float $lon
	 * @param int $radius
	 * @param int $accuracy
	 * @param array $options
	 *
	 * @return POI[]
	 *
	 * @throws Exception
	 */
	public function getPOIs($lat, $lon, $radius, $accuracy, $options) {
		try {
			$pdo = $this->getPDO();
			$sql = "SELECT *, " . GeoUtil::EARTH_RADIUS . " * 2 * asin(
				sqrt(
					pow(sin((radians(" . addslashes($lat) . ") - radians(lat)) / 2), 2)
					+
					cos(radians(" . addslashes($lat) . ")) * cos(radians(lat)) * pow(sin((radians(" . addslashes($lon) . ") - radians(lon)) / 2), 2)
				)
			) AS distance
			FROM POI";
			/* new in Layar 3: flexible radius */
			if (!empty($radius)) {
				$sql .= "HAVING distance < (" . addslashes($radius) . " + " . addslashes($accuracy) . ")";
			}
			$stmt = $pdo->prepare($sql);
			$stmt->execute();
			$pois = array();
			while ($row = $stmt->fetch()) {
				$pois[] = $row;
			}
			foreach ($pois as $poi) {
				$sql = "SELECT * FROM Action WHERE poiID=?";
				$stmt = $pdo->prepare($sql);
				$stmt->execute(array($poi->id));
				$poi["actions"] = array();
				while ($row = $stmt->fetch()) {
					$poi["actions"][] = $row;
				}
				$sql = "SELECT * FROM Object WHERE poiID=?";
				$stmt = $pdo->prepare($sql);
				$stmt->execute(array($poi->id));
				if ($row = $stmt->fetch()) {
					$poi["object"] = $row;
				}
				$sql = "SELECT * FROM Transform WHERE poiID=?";
				$stmt = $pdo->prepare($sql);
				$stmt->execute(array($poi->id));
				if ($row = $stmt->fetch()) {
					$poi["transform"] = $row;
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
				$result[] = $poi;
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
					$this->insertPOI($poi);
				}
			} else {
				foreach ($pois as $poi) {
					if (empty($poi->id)) {
						$this->insertPOI($poi->id);
					} else {
						$oldPOI = $this->getPOIByID($poi->id);
						if (empty($oldPOI)) {
							$this->insertPOI($poi);
						} else {
							$this->updatePOI($poi);
						}
					}
				}
			}
			return TRUE;
		} catch (PDOException $e) {
			throw new Exception("Database error: " . $e->getMessage());
		}
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
		$poiFields = array("alt"," attribution"," dimension"," id"," imageURL"," lat"," lon"," line2"," line3"," line4"," relativeAlt"," title"," type");
		
		// is this a new POI or not?
		$isNewPOI = TRUE;
		if (!empty($poi->id)) {
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
			$sql = "UPATE POI SET ";
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
		$poi->id = $pdo->lastInsertId();
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
		
		$sql = "INSERT INTO Transform (poiID," . implode(",", $transformFields) . ") VALUES (:poiID,:" . implode(",:", $transformFields) . ")";
		$stmt = $pdo->prepare($sql);
		foreach ($transformFields as $transformField) {
			$stmt->bindValue(":" . $transformField, $transform->$transformField);
		}
		$stmt->bindValue(":poiID", $poiID);
		$stmt->execute();
	}
}
