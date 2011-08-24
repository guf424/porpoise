<?php

/*
 * PorPOISe
 * Copyright 2009 SURFnet BV
 * Released under a permissive license (see LICENSE)
 */

/**
 * PorPOISe dashboard GUI
 *
 * @package PorPOISe
 * @subpackage Dashboard
 */

/**
 * GUI class
 *
 * All methods are static
 *
 * @package PorPOISe
 * @subpackage Dashboard
 */
class GUI {
	/** controls whether the GUI displays developer key */
	const SHOW_DEVELOPER_KEY = TRUE;

	/**
	 * Callback for ob_start()
	 *
	 * Adds header and footer to HTML output and does post-processing
	 * if required
	 *
	 * @param string $output The output in the buffer
	 * @param int $state A bitfield specifying what state the script is in (start, cont, end)
	 *
	 * @return string The new output
	 */
	public static function finalize($output, $state) {
		$result = "";
		if ($state & PHP_OUTPUT_HANDLER_START) {
			$result .= self::createHeader();
		}
		$result .= $output;
		if ($state & PHP_OUTPUT_HANDLER_END) {
			$result .= self::createFooter();
		}
		return $result;
	}

	/**
	 * Print a formatted message
	 *
	 * @param string $message sprintf-formatted message
	 *
	 * @return void
	 */
	public static function printMessage($message) {
		$args = func_get_args();
		/* remove first argument, which is $message */
		array_splice($args, 0, 1);
		vprintf($message, $args);
	}

	/**
	 * Print an error message
	 *
	 * @param string $message sprintf-formatted message
	 *
	 * @return void
	 */
	public static function printError($message) {
		$args = func_get_args();
		$args[0] = sprintf("<p class=\"error\">%s</p>\n", $args[0]);
		call_user_func_array(array("GUI", "printMessage"), $args);
	}

	/**
	 * Create a header
	 *
	 * @return string
	 */
	public static function createHeader() {
		return
		<<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<title>PorPOISe POI Management Interface</title>
<link rel="stylesheet" type="text/css" href="styles.css">
<script type="text/javascript" src="prototype.js"></script>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<script type="text/javascript" src="scripts.js"></script>
</head>
<body>

<div class="menu">
 <a href="?logout=true">Log out</a>
 <a href="?action=main">Home</a>
 <a href="?action=migrate">Copy layers</a>
</div>

<div class="main">
HTML;
	}

	/**
	 * Create a footer
	 *
	 * @return string
	 */
	public static function createFooter() {
		return
		<<<HTML
</div> <!-- end main div -->
</body>
</html>
HTML;
	}

	/**
	 * Create a select box
	 *
	 * @param string $name
	 * @param array $options
	 * @param mixed $selected
	 *
	 * @return string
	 */
	protected static function createSelect($name, $options, $selected = NULL) {
		$result = sprintf("<select name=\"%s\">\n", $name);
		foreach ($options as $value => $label) {
			$result .= sprintf("<option value=\"%s\"%s>%s</option>\n", $value, ($value == $selected ? " selected" : ""), $label);
		}
		$result .="</select>\n";
		return $result;
	}

	/**
	 * Create a Yes/No select box
	 *
	 * @param string $name
	 * @param bool $checked
	 *
	 * @return string
	 */
	protected static function createCheckbox($name, $checked = FALSE) {
		return self::createSelect($name, array("1" => "Yes", "0" => "No"), $checked ? "1" : "0");
	}

	/**
	 * Create "main" screen
	 *
	 * @return string
	 */
	public static function createMainScreen() {
		$result = "";
		$result .= "<p>Welcome to PorPOISe</p>\n";
		$result .= self::createMainConfigurationTable();
		$result .= "<p>Layers:</p>\n";
		$result .= self::createLayerList();
		return $result;
	}

	/**
	 * Create a table displaying current configuration
	 *
	 * @return string
	 */
	public static function createMainConfigurationTable() {
		$config = DML::getConfiguration();
		$result = "";
		$result .= "<table class=\"config\">\n";
		$result .= sprintf("<tr><td>Developer ID</td><td>%s</td></tr>\n", $config->developerID);
		$result .= sprintf("<tr><td>Developer key</td><td>%s</td></tr>\n", (self::SHOW_DEVELOPER_KEY ? $config->developerKey : "&lt;hidden&gt;"));
		$result .= sprintf("</table>\n");
		return $result;
	}

	/**
	 * Create a list of layers
	 *
	 * @return string
	 */
	public static function createLayerList() {
		$config = DML::getConfiguration();
		$result = "";
		$result .= "<ul>\n";
		foreach ($config->layerDefinitions as $layerDefinition) {
			$result .= sprintf("<li><a href=\"%s?action=layer&layerName=%s\">%s</a></li>\n", $_SERVER["PHP_SELF"], $layerDefinition->name, $layerDefinition->name);
		}
		$result .= "</ul>\n";
		return $result;
	}

	/**
	 * Create a screen for viewing/editing a layer
	 *
	 * @param string $layerName
	 *
	 * @return string
	 */
	public static function createLayerScreen($layerName) {
		$layerDefinition = DML::getLayerDefinition($layerName);
		if ($layerDefinition == NULL) {
			throw new Exception(sprintf("Unknown layer: %s\n", $layerName));
		}
		$result = "";
		$result .= sprintf("<p>Layer name: %s</p>\n", $layerName);
		$result .= sprintf("<p>POI connector: %s</p>\n", $layerDefinition->connector);
		$result .= sprintf("<p>Connector options:\n");
		if (!empty($layerDefinition->connectorOptions)) {
			$result .= "<ul>\n";
			foreach ($layerDefinition->connectorOptions as $optionName => $optionValue) {
				$result .= sprintf("<li>%s: %s</li>\n", $optionName, $optionValue);
			}
			$result .= "</ul>\n";
		} else {
			$result .= "none\n";
		}
		$result .= "</p>\n";
		$result .= sprintf("<form accept-charset=\"utf-8\" action=\"?action=layer&layerName=%s\" method=\"POST\">\n", $layerName);

		$layerProperties = DML::getLayerProperties($layerName);
		$result .= sprintf("<table class=\"layer\">\n");
		$result .= sprintf("<tr><td>Response message</td><td><input type=\"text\" name=\"showMessage\" value=\"%s\"></td></tr>\n", $layerProperties->showMessage);
		$result .= sprintf("<tr><td>Refresh interval</td><td><input type=\"text\" name=\"refreshInterval\" value=\"%s\"></td></tr>\n", $layerProperties->refreshInterval);
		$result .= sprintf("<tr><td>Refresh distance</td><td><input type=\"text\" name=\"refreshDistance\" value=\"%s\"></td></tr>\n", $layerProperties->refreshDistance);
		$result .= sprintf("<tr><td>Full refresh</td><td>%s</td></tr>\n", self::createCheckbox("fullRefresh", $layerProperties->fullRefresh));
		$result .= sprintf("<tr><td>Default BIW Style</td><td><input type=\"text\" name=\"biwStyle\" value=\"%s\"></td></tr>\n", $layerProperties->biwStyle);
		foreach ($layerProperties->actions as $key => $action) {
			$result .= sprintf("<tr><td>Action<br><button type=\"button\" onclick=\"GUI.removeLayerAction(%s)\">Remove</button></td><td>%s</td></tr>\n", $key, self::createActionSubtable($key, $action, TRUE));
		}
		$result .= sprintf("<tr><td colspan=\"2\"><button type=\"button\" onclick=\"GUI.addLayerAction(this)\">New action</button></td></tr>\n");

		$index = 0;
		foreach ($layerProperties->animations as $event => $animations) {
			foreach ($animations as $animation) {
				$result .= sprintf("<tr><td>Animation<br><button type=\"button\" onclick=\"GUI.removeLayerAnimation(%s)\">Remove</button></td><td>%s</td></tr>\n", $index, self::createAnimationSubtable($index, $event, $animation));
				$index++;
			}
		}
		$result .= sprintf("<tr><td colspan=\"2\"><button type=\"button\" onclick=\"GUI.addLayerAnimation(this)\">New animation</button></td></tr>\n");
		$result .= sprintf("<caption><button type=\"submit\">Save</button></caption>\n");
		$result .= sprintf("</table>\n");
		$result .= sprintf("</form>\n");


		$result .= sprintf("<form accept-charset=\"utf-8\" action=\"?action=newPOI&layerName=%s\" method=\"POST\">\n", urlencode($layerName));
		$result .= '<input type="hidden" name="dimension" value="2">';
		$result .= '<input type="hidden" name="layerName" value="'.urlencode($layerName).'">';
		$result .= '<button type="submit">Create POI</button>';
		$result .= '</form><br/>';

//		$result .= sprintf("<p><a href=\"?action=newPOI&layerName=%s\">New POI</a></p>\n", urlencode($layerName));
		$result .= self::createPOITable($layerName);
		return $result;
	}

	/**
	 * Create a list of POIs for a layer
	 *
	 * @param string $layerName
	 *
	 * @return string
	 */
	public static function createPOITable($layerName) {
		$result = "";
		$pois = DML::getPOIs($layerName);
		if ($pois === NULL || $pois === FALSE) {
			throw new Exception("Error retrieving POIs");
		}
		$result .= "<table class=\"pois\">\n";
		$result .= "<tr><th>Title</th><th>&nbsp;</th></tr>\n";
		foreach ($pois as $poi) {
			$result .= "<tr>\n";
			$result .= sprintf("<td><a href=\"?action=poi&layerName=%s&poiID=%s\">%s</a></td>\n", urlencode($layerName), urlencode($poi->id), ($poi->text->title ? $poi->text->title : "&lt;no title&gt;"));
			$result .= sprintf("<td><form accept-charset=\"utf-8\" action=\"?action=deletePOI\" method=\"POST\"><input type=\"hidden\" name=\"layerName\" value=\"%s\"><input type=\"hidden\" name=\"poiID\" value=\"%s\"><button type=\"submit\">Delete</button></form></td>\n", urlencode($layerName), urlencode($poi->id));
			$result .= "</tr>\n";
		}
		$result .= "</table>\n";
		return $result;
	}

	/**
	 * Create a screen for a single POI
	 *
	 * @param string $layerName
	 * @param string $poi POI to display in form. Leave empty for new POI
	 *
	 * @return string
	 */
	public static function createPOIScreen($layerName, $poi = NULL) {
		if (empty($poi)) {
			$poi = new POI();
		}

		$result = "";
		$result .= sprintf("<p><a href=\"?action=layer&layerName=%s\">Back to %s</a></p>\n", urlencode($layerName), $layerName);
		$result .= sprintf("<form accept-charset=\"utf-8\" action=\"?layerName=%s&action=poi&poiID=%s\" method=\"POST\">\n", urlencode($layerName), urlencode($poi->id));
		$result .= "<table class=\"poi\">\n";
		$result .= '<tr><th colspan="2">General</th></tr>';
		$result .= sprintf("<tr><td>ID</td><td><input type=\"hidden\" name=\"id\" value=\"%s\">%s</td></tr>\n", $poi->id, $poi->id);
		$result .= sprintf("<tr><td>Title</td><td><input type=\"text\" name=\"text_title\" value=\"%s\"></td></tr>\n", $poi->text->title);
		$result .= sprintf("<tr><td>Description</td><td><textarea name=\"text_description\">%s</textarea></td></tr>\n", $poi->text->description);
		$result .= sprintf("<tr><td>Footnote</td><td><input type=\"text\" name=\"text_footnote\" value=\"%s\"></td></tr>\n", $poi->text->footnote);
		$result .= sprintf("<tr><td>Image URL</td><td><input type=\"text\" name=\"imageURL\" value=\"%s\"></td></tr>\n", $poi->imageURL);
		$result .= sprintf("<tr><td>Prevent indexing</td><td>%s</td></tr>\n", self::createCheckbox("doNotIndex", $poi->doNotIndex));
		$result .= sprintf("<tr><td>Show small BIW</td><td>%s</td></tr>\n", self::createCheckbox("showSmallBiw", $poi->showSmallBiw));
		$result .= sprintf("<tr><td>Show BIW when clicked</td><td>%s</td></tr>\n", self::createCheckbox("showBiwOnClick", $poi->showBiwOnClick));
		$result .= '<tr><th colspan="2">Anchor</th></tr>';
		$result .= sprintf("<tr><td>Lat/lon/alt</td><td><input type=\"text\" name=\"anchor_geolocation_lat\" value=\"%s\" size=\"7\"><input type=\"text\" name=\"anchor_geolocation_lon\" value=\"%s\" size=\"7\"><input type=\"text\" name=\"anchor_geolocation_alt\" value=\"%s\" size=\"2\"></td></tr>\n", $poi->anchor->geolocation['lat'], $poi->anchor->geolocation['lon'], $poi->anchor->geolocation['alt']);
		$result .= sprintf("<tr><td>referenceImage</td><td><input type=\"text\" name=\"anchor_referenceImage\" value=\"%s\" size=\"64\"></td></tr>\n", $poi->anchor->referenceImage);
		$result .= '<tr><th colspan="2">Icon</th></tr>';
		$result .= sprintf("<tr><td>Icon type</td><td><input type=\"text\" name=\"icon_type\" value=\"%s\" size=\"1\"></td></tr>\n", $poi->icon->type);
		$result .= sprintf("<tr><td>Icon URL</td><td><input type=\"text\" name=\"icon_url\" value=\"%s\" size=\"32\" maxlength=\"128\"></td></tr>\n", $poi->icon->url);
		/* Object */
		$result .= '<tr><th colspan="2">Object</th></tr>';
		$result .= sprintf("<tr><td>Full model</td><td><input type=\"text\" name=\"object_url\" value=\"%s\" size=\"64\"></td></tr>\n", $poi->object->url);
		$result .= sprintf("<tr><td>Reduced model</td><td><input type=\"text\" name=\"object_reducedURL\" value=\"%s\" size=\"64\"></td></tr>\n", $poi->object->reducedURL);
		$result .= sprintf("<tr><td>Content Type</td><td><input type=\"text\" name=\"object_contentType\" value=\"%s\" size=\"32\"></td></tr>\n", $poi->object->contentType);
		$result .= sprintf("<tr><td>Model size (approx)</td><td><input type=\"text\" name=\"object_size\" value=\"%s\" size=\"1\"></td></tr>\n", $poi->object->size);

		/* Transform */
		$result .= '<tr><th colspan="2">Transform</th></tr>';
		$result .= sprintf("<tr><td>Rotate over axis x/y/z</td><td><input type=\"text\" name=\"transform_rotate_axis_x\" value=\"%s\" size=\"7\"><input type=\"text\" name=\"transform_rotate_axis_y\" value=\"%s\" size=\"7\"><input type=\"text\" name=\"transform_rotate_axis_z\" value=\"%s\" size=\"2\"></td></tr>\n", $poi->transform->rotate['axis']['x'], $poi->transform->rotate['axis']['y'], $poi->transform->rotate['axis']['z']);
		$result .= sprintf("<tr><td>Angle</td><td><input type=\"text\" name=\"transform_rotate_angle\" value=\"%s\" size=\"1\"></td></tr>\n", $poi->transform->rotate['angle']);
		$result .= sprintf("<tr><td>Use relative angle?</td><td>%s</td></tr>\n", self::createCheckbox("transform_rotate_rel", $poi->transform->rotate['rel']));
		$result .= sprintf("<tr><td>Translate x/y/z</td><td><input type=\"text\" name=\"transform_translate_x\" value=\"%s\" size=\"7\"><input type=\"text\" name=\"transform_translate_y\" value=\"%s\" size=\"7\"><input type=\"text\" name=\"transform_translate_z\" value=\"%s\" size=\"2\"></td></tr>\n", $poi->transform->translate['x'], $poi->transform->translate['y'], $poi->transform->translate['z']);
		$result .= sprintf("<tr><td>Scaling factor</td><td><input type=\"text\" name=\"transform_scale\" value=\"%s\" size=\"2\"></td></tr>\n", $poi->transform->scale);
		$result .= '<tr><th colspan="2">Actions</th></tr>';
		foreach ($poi->actions as $key => $action) {
			$result .= sprintf("<tr><td>Action<br><button type=\"button\" onclick=\"GUI.removePOIAction(%s)\">Remove</button></td><td>%s</td></tr>\n", $key, self::createActionSubtable($key, $action));
		}
		$result .= sprintf("<tr><td colspan=\"2\"><button type=\"button\" onclick=\"GUI.addPOIAction(this)\">New action</button></td></tr>\n");
		$index = 0;
		foreach ($poi->animations as $event => $animations) {
			foreach ($animations as $animation) {
				$result .= sprintf("<tr><td>Animation<br><button type=\"button\" onclick=\"GUI.removePOIAnimation(%s)\">Remove</button></td><td>%s</td></tr>\n", $index, self::createAnimationSubtable($index, $event, $animation));
				$index++;
			}
		}
		$result .= sprintf("<tr><td colspan=\"2\"><button type=\"button\" onclick=\"GUI.addPOIAnimation(this)\">New animation</button></td></tr>\n");

		$result .= "<caption><button type=\"submit\">Save</button></caption>\n";
		$result .= "</table>\n";
		$result .= "</form>";
		return $result;
	}

	/**
	 * Create a subtable for an action for inside a form
	 *
	 * @param string $index Index of the action in the actions[] array
	 * @param Action $action The action
	 * @param bool $layerAction Create a layer action form instead of a POI action form
	 *
	 * @return string
	 */
	public static function createActionSubtable($index, Action $action, $layerAction = FALSE) {
		$result = "";
		$result .= "<table class=\"action\">\n";
		$result .= sprintf("<tr><td>Label</td><td><input type=\"text\" name=\"actions[%s][label]\" value=\"%s\"></td></tr>\n", $index, $action->label);
		$result .= sprintf("<tr><td>URI</td><td><input type=\"text\" name=\"actions[%s][uri]\" value=\"%s\"></td></tr>\n", $index, $action->uri);
		if (!$layerAction) {
			$result .= sprintf("<tr><td>Auto-trigger range (Geo POIs only)</td><td><input type=\"text\" name=\"actions[%s][autoTriggerRange]\" value=\"%s\" size=\"2\"></td></tr>\n", $index, $action->autoTriggerRange);
			$result .= sprintf("<tr><td>Auto-trigger only (Geo POIs only)</td><td>%s</td></tr>\n", self::createCheckbox(sprintf("actions[%s][autoTriggerOnly]", $index), $action->autoTriggerOnly));
			$result .= sprintf("<tr><td>Auto-trigger (Feat. tracked POIs only)</td><td>%s</td></tr>\n", self::createCheckbox(sprintf("actions[%s][autoTrigger]", $index), $action->autoTrigger));
		}
		$result .= sprintf("<tr><td>Content type</td><td><input type=\"text\" name=\"actions[%s][contentType]\" value=\"%s\">\n", $index, $action->contentType);
		$result .= sprintf("<tr><td>Method</td><td>%s</td></tr>\n", self::createSelect(sprintf("actions[%s][method]", $index), array("GET" => "GET", "POST" => "POST"), $action->method));
		$result .= sprintf("<tr><td>Activity type</td><td><input type=\"text\" name=\"actions[%s][activityType]\" value=\"%s\" size=\"2\"></td></tr>\n", $index, $action->activityType);
		$result .= sprintf("<tr><td>Parameters, comma-separated</td><td><input type=\"text\" name=\"actions[%s][params]\" value=\"%s\"></td></tr>\n", $index, implode(",", $action->params));
		if (!$layerAction) {
			$result .= sprintf("<tr><td>Close BIW on action</td><td>%s</td></tr>\n", self::createCheckbox(sprintf("actions[%s][closeBiw]", $index), $action->closeBiw));
		}
		$result .= sprintf("<tr><td>Show activity indication</td><td>%s</td></tr>\n", self::createCheckbox(sprintf("actions[%s][showActivity]", $index), $action->showActivity));
		$result .= sprintf("<tr><td>Activity message</td><td><input type=\"text\" name=\"actions[%s][activityMessage]\" value=\"%s\"></td></tr>\n", $index, $action->activityMessage);

		$result .= "</table>\n";

		return $result;
	}

	/**
	 * Create a dropdown for selecting animation events
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	protected static function createEventSelector($name, $selected = NULL) {
		$result = sprintf("<select name=\"%s\">", $name);
		foreach (array("onCreate", "onUpdate", "onDelete", "onFocus", "onClick") as $event) {
			$result .= sprintf("<option value=\"%s\"%s>%s</option>", $event, ($selected == $event ? " selected" : ""), $event);
		}
		$result .= "</select>";
		return $result;
	}

	/**
	 * Create a subtable for an animation for inside a form
	 *
	 * @param string $index Index of the action in the actions[] array
	 * @param string $event Event for which the animation is
	 * @param Animation $animation The animation
	 *
	 * @return string
	 */
	public static function createAnimationSubtable($index, $event, Animation $animation) {
		$result = "";
		$result .= "<table class=\"animation\">\n";
		$result .= sprintf("<tr><td>Event</td><td>%s</td></tr>\n", self::createEventSelector(sprintf("animations[%s][event]", $index), $event));
		$result .= sprintf("<tr><td>Type</td><td><input type=\"text\" name=\"animations[%s][type]\" value=\"%s\"></td></tr>\n", $index, $animation->type);
		$result .= sprintf("<tr><td>Length</td><td><input type=\"text\" name=\"animations[%s][length]\" value=\"%s\"></td></tr>\n", $index, $animation->length);
		$result .= sprintf("<tr><td>Delay</td><td><input type=\"text\" name=\"animations[%s][delay]\" value=\"%s\"></td></tr>\n", $index, $animation->delay);
		$result .= sprintf("<tr><td>Interpolation</td><td><input type=\"text\" name=\"animations[%s][interpolation]\" value=\"%s\"></td></tr>\n", $index, $animation->interpolation);
		$result .= sprintf("<tr><td>Interpolation parameter</td><td><input type=\"text\" name=\"animations[%s][interpolationParam]\" value=\"%s\"></td></tr>\n", $index, $animation->interpolationParam);
		$result .= sprintf("<tr><td>Persist</td><td>%s</td></tr>\n", self::createCheckbox(sprintf("animations[%s][persist]", $index), $animation->persist));
		$result .= sprintf("<tr><td>Repeat</td><td>%s</td></tr>\n", self::createCheckbox(sprintf("animations[%s][repeat]", $index), $animation->repeat));
		$result .= sprintf("<tr><td>From</td><td><input type=\"text\" name=\"animations[%s][from]\" value=\"%s\"></td></tr>\n", $index, $animation->from);
		$result .= sprintf("<tr><td>To</td><td><input type=\"text\" name=\"animations[%s][to]\" value=\"%s\"></td></tr>\n", $index, $animation->to);
		$result .= sprintf("<tr><td>Axis (x,y,z)</td><td><input type=\"text\" name=\"animations[%s][axis]\" value=\"%s\"></td></tr>\n", $index, $animation->axisString());
		$result .= "</table>\n";

		return $result;
	}

	/**
	 * Create a screen for a new POI
	 *
	 * @param string $layerName
	 *
	 * @return string
	 */
	public function createNewPOIScreen($layerName) {
		$result = "";
		$result .= sprintf("<form accept-charset=\"utf-8\" action=\"?action=newPOI&layerName=%s\" method=\"POST\">\n", urlencode($layerName));
		$result .= sprintf("<table class=\"newPOI\">\n");
		$result .= sprintf("<tr><td>Dimension</td><td><input type=\"text\" name=\"dimension\" size=\"1\"></td></tr>\n");
		$result .= sprintf("<caption><button type=\"submit\">Create</button></caption>");
		$result .= "</table>\n";
		$result .= "</form>\n";
		return $result;
	}

	/**
	 * Create login screen
	 *
	 * @return string
	 */
	public static function createLoginScreen() {
		$result = "";
		/* preserve GET parameters */
		$get = $_GET;
		unset($get["username"]);
		unset($get["password"]);
		unset($get["logout"]);
		$getString = "";
		$first = TRUE;
		foreach ($get as $key => $value) {
			if ($first) {
				$first = FALSE;
				$getString .= "?";
			} else {
				$getString .= "&";
			}
			$getString .= urlencode($key) . "=" . urlencode($value);
		}
		$result .= sprintf("<form accept-charset=\"utf-8\" method=\"POST\" action=\"%s%s\">\n", $_SERVER["PHP_SELF"], $getString);
		$result .= "<table class=\"login\">\n";
		$result .= "<tr><td>Username</td><td><input type=\"text\" name=\"username\" size=\"15\"></td></tr>\n";
		$result .= "<tr><td>Password</td><td><input type=\"password\" name=\"password\" size=\"15\"></td></tr>\n";
		$result .= "<caption><button type=\"submit\">Log in</button></caption>\n";
		$result .= "</table>\n";
		/* preserve POST */
		foreach ($_POST as $key => $value) {
			switch ($key) {
				case "username":
				case "password":
				case "logout":
					break;
				default:
					$result .= sprintf("<input type=\"hidden\" name=\"%s\" value=\"%s\">\n", $key, $value);
					break;
			}
		}

		$result .= "</form>\n";

		return $result;
	}

	/**
	 * Create a screen for migrating (copying) layers
	 *
	 * @return string
	 */
	public static function createMigrationScreen() {
		$result = "";
		$layers = DML::getLayers();
		$layers = array_combine($layers, $layers);
		$result .= sprintf("<form accept-charset=\"utf-8\" method=\"POST\" action=\"%s?action=migrate\">\n", $_SERVER["PHP_SELF"]);
		$result .= sprintf("<p>Copy from %s to %s <button type=\"submit\">Copy</button></p>\n", GUI::createSelect("from", $layers), GUI::createSelect("to", $layers));
		$result .= sprintf("<p>Warning: copying contents will overwrite any old data in the destination layer</p>\n");
		$result .= "</form>\n";
		return $result;
	}

	/**
	 * Handle POST
	 *
	 * Checks whether there is something in the POST to handle and calls
	 * appropriate methods if there is.
	 *
	 * @throws Exception When invalid data is passed in POST
	 */
	public static function handlePOST() {
		$post = $_POST;
		/* not interested in login attempts */
		unset($post["username"]);
		unset($post["password"]);

		if (empty($post)) {
			/* nothing interesting in POST */
			return;
		}
		$action = $_REQUEST["action"];
		switch ($action) {
			case "poi":
				$poi = self::makePOIFromRequest($post);
				DML::savePOI($_REQUEST["layerName"], $poi);
				break;
			case "newPOI":
				$poi = self::makePOIFromRequest($post);
				DML::savePOI($_REQUEST["layerName"], $poi);
				self::redirect("layer", array("layerName" => $_REQUEST["layerName"]));
				break;
			case "deletePOI":
				DML::deletePOI($_REQUEST["layerName"], $_REQUEST["poiID"]);
				self::redirect("layer", array("layerName" => $_REQUEST["layerName"]));
				break;
			case "migrate":
				DML::migrateLayers($_REQUEST["from"], $_REQUEST["to"]);
				break;
			case "layer":
				$layerProperties = self::makeLayerPropertiesFromRequest($post);
				$layerProperties->layer = $_REQUEST["layerName"];
				DML::saveLayerProperties($_REQUEST["layerName"], $layerProperties);
				break;
			default:
				throw new Exception(sprintf("No POST handler defined for action %s\n", $action));
		}
	}

	/**
	 * Turn request data into a POI object
	 *
	 * @param array $request The data from the request
	 *
	 * @return POI
	 */
	protected static function makePOIFromRequest($request) {
		$result = new POI();
		foreach ($request as $key => $value) {
			switch ($key) {
				case 'icon_url':
				case 'anchor_referenceImage':
				case 'imageURL':
				case 'object_reducedURL':
				case 'object_url':
				case 'text_description':
				case 'text_footnote':
				case 'text_title':
					$request[$key] = (string) $request[$key];
					break;
				case 'icon_type':
				case 'id':
				case 'transform_rotate_angle':
					$request[$key] = (int) $request[$key];
					break;
				case 'doNotIndex':
				case 'showBiwOnClick':
				case 'showSmallBiw':
				case 'transform_rotate_rel':
					$request[$key] = (bool) $request[$key];
					break;
				case 'anchor_geolocation_alt':
				case 'anchor_geolocation_lat':
				case 'anchor_geolocation_lon':
				case 'object_size':
				case 'transform_scale':
				case 'transform_translate_x':
				case 'transform_translate_y':
				case 'transform_translate_z':
				case 'transform_rotate_axis_x':
				case 'transform_rotate_axis_y':
				case 'transform_rotate_axis_z':
					$request[$key] = (float) $request[$key];
					break;
				case "actions":
					foreach ($value as $action) {
						$result->actions[] = new POIAction($action);
					}
					break;
				case "animations":
					foreach ($value as $animation) {
						$animationObj = new Animation($animation);
						$result->animations[$animation["event"]][] = $animationObj;
					}
					break;
			}
		}

		// Now convert array keys like "transform_rotate_axis_x" into ['transform']['rotate_axis_x'] and remove their source variable
		$request = Util::simpleArrayToMultiDimArray($request);


		// Run over all input once more to assign freshly created arrays to the POI
		foreach ($request as $key => $value) {
			switch($key) {
				case "icon":
					$result->icon = new POIIcon($value);
					break;
				case "text":
					$result->text = new POIText($value);
					break;
				case "anchor":
					$result->anchor = new POIAnchor($value);
					break;
				case "transform":
					$result->transform = new POITransform($value);
					break;
				case "object":
					$result->object = new POIObject($value);
					break;
				default:
					if (!is_array($request[$key])) $result->$key = $value;
			}
		}
		return $result;
	}

	/**
	 * Turn request data into a LayarResponse object
	 *
	 * @param array $request
	 *
	 * @return LayarResponse
	 */
	public static function makeLayerPropertiesFromRequest($request) {
		$result = new LayarResponse();
		foreach ($request as $name => $value) {
			switch ($name) {
				case "showMessage":
				case "biwStyle":
					$result->$name = (string) $value;
					break;
				case "refreshInterval":
				case "refreshDistance":
					$result->$name = (int) $value;
					break;
				case "fullRefresh":
					$result->$name = (bool) (string) $value;
					break;
				case "actions":
					foreach ($value as $action) {
						$result->actions[] = new Action($action);
					}
					break;
				case "animations":
					foreach ($value as $animation) {
						$animationObj = new Animation($animation);
						$result->animations[$animation["event"]][] = $animationObj;
					}
					break;
			}
		}
		return $result;
	}

	/**
	 * Redirect (HTTP 300) user
	 *
	 * This method fails if headers are already sent
	 *
	 * @param string $action New action to go to
	 * @param array $arguments
	 *
	 * @return void On success, does not return but calls exit()
	 */
	protected static function redirect($where, array $arguments = array()) {
		if (headers_sent()) {
			self::printError("Headers are already sent");
			return;
		}
		$getString = "";
		$getString .= sprintf("?action=%s", urlencode($where));
		foreach ($arguments as $key => $value) {
			$getString .= sprintf("&%s=%s", urlencode($key), urlencode($value));
		}
		if (empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] == "off") {
			$scheme = "http";
		} else {
			$scheme = "https";
		}
		$location = sprintf("%s://%s%s%s", $scheme, $_SERVER["HTTP_HOST"], $_SERVER["PHP_SELF"], $getString);
		header("Location: " . $location);
		exit();
	}

}
