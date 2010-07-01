<?xml version='1.0' encoding='utf-8'?>
<!--
XSL to convert Google Earth KML export to PorPOIse XML
Original version: Adam Moore - moore.adam@gmail.com
Centre for Geospatial Science
University of Nottingham
December 2009
Version 0.2
Adapted for Layar 3.x: Johannes la Poutre <info@squio.nl>
TAB Worldmedia, April 2010
-->


<!-- 

How to use from google my-maps:

	* Select the map with POIs you want to export
	* Copy the RSS link (at the top of the map)
	* Edit the URI request parameter 'output', replace 'georss' by 'kml'
	* Run the following shell commands (tested on Mac OSX):

curl 'edited_rss_lnk' > output.kml
xsltproc gmaps-kml.xsl output.kml > output.xml
	
Now output.xml contains the correct PorPOISe xml definition for your map's POIs

Optional parameters
	* dimension {1,2,3} default 1 - with dimension 2 the POI icons will be used as images in a 2D layer

Example:
http://maps.google.nl/maps/ms?ie=UTF8&hl=en&vps=2&jsv=250a&msa=0&output=georss&msid=104128426231234843804.00047c58233dfc05bdae2

Edited version:
http://maps.google.nl/maps/ms?ie=UTF8&hl=en&vps=2&jsv=250a&msa=0&output=kml&msid=104128426231234843804.00047c58233dfc05bdae2

Run the following shell commands:

	curl 'http://maps.google.nl/maps/ms?ie=UTF8&hl=en&vps=2&jsv=250a&msa=0&output=kml&msid=104128426231234843804.00047c58233dfc05bdae2' > ../xml/vinkeveen.kml
	xsltproc gmaps-kml.xsl ../xml/leegstandleiden.kml > ../xml/leegstandleiden.xml
	
OR	
	xsltproc -param dimension 2 gmaps-kml.xsl ../xml/leegstandleiden.kml > ../xml/leegstandleiden.xml

-->
<xsl:stylesheet version='1.0' 
	xmlns:kml="http://earth.google.com/kml/2.2" 
	xmlns:xsl='http://www.w3.org/1999/XSL/Transform'>

<xsl:output method='xml' version='1.0' encoding='utf-8' indent='yes'/>

<!-- Change to whatever attribution you wish to use -->
<xsl:param name="attribution">(c) TABworldmedia.com</xsl:param>

<!-- use POI images for AR view if dimension = 2 -->
<xsl:param name="dimension">1</xsl:param>

<xsl:template match="kml:kml">
<xsl:comment>Produced using kml2layar</xsl:comment>
  <pois>
  <!-- Placemark is the only KML attribute processed so far -->
  <xsl:for-each select="kml:Document//kml:Placemark">
  <poi>
   <!-- ID is the position in the Placemark List -->
   <!-- make sure these are unique otherwise they will not show up in Layar Stream -->
   <id><xsl:number value ="position()"/></id>
   <!-- Title is the KML Name -->
   <title><xsl:value-of select="kml:name" /></title>
   <!-- Lat and Lon are the first 2 strings of Point/coordinates -->
   <lat><xsl:value-of select="substring-before(substring-after(kml:Point/kml:coordinates,','),',')" /></lat>
   <lon><xsl:value-of select="substring-before(kml:Point/kml:coordinates,',')" /></lon>
   <!-- Attribution set to the variable declared at the start -->
   <attribution><xsl:value-of select="$attribution" /></attribution>
   <xsl:variable name="style" select="substring-after(kml:styleUrl/text(), '#')"/>
   <imageURL><xsl:value-of select="../kml:Style[@id=$style]/kml:IconStyle/kml:Icon/kml:href" /></imageURL>
   <!-- Currently every Placemark is output as type=1 -->
   <type>1</type>
   <!-- FIXME: do some preprocessing to strip out the relevant data from 
   the tagsoup from the kml:description element, e.g.:
   Find: <!\[CDATA\[<div dir="ltr">Prijs: ([^<]+)<br>Link: ([^<]+)</div>]]>
   Replace: <line2>Prijs: \1</line2><action><uri>\2</uri><label>Meer...</label></action>
   -->
   <!-- EXAMPLE
   <line2><xsl:value-of select="kml:description/kml:line2" /></line2>
   <action>
<xsl:if test="kml:description/kml:action/kml:uri">
   	<uri><xsl:value-of select="kml:description/kml:action/kml:uri" /></uri>
   	<label><xsl:value-of select="kml:description/kml:action/kml:label" /></label>
</xsl:if>
   </action>
   -->
<xsl:if test="$dimension = 2">
	<dimension>2</dimension>
	<alt>0</alt>
	<transform><rel/><angle>0</angle><scale>1</scale></transform>
	<object>
		<baseURL>
			<xsl:call-template name="baseUrl">
				<xsl:with-param name="path" select="../kml:Style[@id=$style]/kml:IconStyle/kml:Icon/kml:href"/>
			</xsl:call-template>
		</baseURL>
		<full/>
		<reduced/>
		<icon>
			<xsl:call-template name="imageFile">
				<xsl:with-param name="path" select="../kml:Style[@id=$style]/kml:IconStyle/kml:Icon/kml:href"/>
			</xsl:call-template>
		</icon>
		<size>0</size>
	</object>
</xsl:if> 
  </poi>
  </xsl:for-each>
  </pois>
</xsl:template>

<xsl:template name="baseUrl">
	<xsl:param name="baseUrl" select="''"/>
	<xsl:param name="path"/>
	<xsl:choose>
		<xsl:when test="contains($path, '/')">
			<xsl:call-template name="baseUrl">
				<xsl:with-param name="baseUrl">
					<xsl:value-of select="concat($baseUrl, substring-before($path, '/'), '/')"/>
				</xsl:with-param>
				<xsl:with-param name="path">
					<xsl:value-of select="substring-after($path, '/')"/>
				</xsl:with-param>
			</xsl:call-template>
		</xsl:when>
		<xsl:otherwise>
			<xsl:value-of select="$baseUrl"/>
		</xsl:otherwise>
	</xsl:choose>
</xsl:template>

<xsl:template name="imageFile">
	<xsl:param name="baseUrl" select="''"/>
	<xsl:param name="path"/>
	<xsl:choose>
		<xsl:when test="contains($path, '/')">
			<xsl:call-template name="imageFile">
				<xsl:with-param name="baseUrl">
					<xsl:value-of select="concat($baseUrl, '/', substring-before($path, '/'))"/>
				</xsl:with-param>
				<xsl:with-param name="path">
					<xsl:value-of select="substring-after($path, '/')"/>
				</xsl:with-param>
			</xsl:call-template>
		</xsl:when>
		<xsl:otherwise>
			<xsl:value-of select="$path"/>
		</xsl:otherwise>
	</xsl:choose>
</xsl:template>



</xsl:stylesheet>
