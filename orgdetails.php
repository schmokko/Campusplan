<?php 

	// load the additional CSS, we're gonna show a map:
	$leaftletCSS = true;
	
	require_once("functions.php");
	checkCache();
	
	$org_uri   = $_GET["org_uri"]; 
	
	getHead();
?>

<div class="container">
		
		<?php 
			if(!isset($_GET['lang'])){
				$lang = 'de';
			} else {
				$lang = $_GET['lang'];
			}
			
			$orgDetails = getOrgDetails($org_uri, $lang);				
		?>	

	</div>

<?php addMapCode($orgDetails); ?>

<?php 
getFoot();


// generates the Leaflet JS Code
function addMapCode($orgDetails){

	//only show the map if we have any geodata:
	
	if(isset($orgDetails->wkt->value) || (isset($orgDetails->lat->value) && isset($orgDetails->long->value))){
	
	// spit out the JS code that works for buildings with and without WKT 
	
	echo"
	<script>
	
	function error(msg){
		$('button.route').remove();
		$('a.route').html('Navigation via Google Maps');
		$('div#instructions').addClass('lead alert').html('Dein Gerät untersützt keine Ermittlung deines aktuellen Standortes, oder die Funktion ist deaktiviert. Daher können wir dir die Navigation nur über Google Maps anbieten, nach Eingabe deines Standortes.');
	}

	// wait until the page is loaded:
	$(function(event){

		if (navigator.geolocation) {
			// create the link for the bus route button:
			navigator.geolocation.getCurrentPosition(enableBusRoute, error);
		}

	 	// enable the navigation button:
	 	$('.route').click(function(){

	 		$('.route').each(function(){
	 			$(this).removeClass('btn-info');
	 		});
	
			$(this).addClass('btn-info')
	 		var id = $(this).attr('id');

	 		// get position via HTML5 geolocation API
	 		if (navigator.geolocation) {	 			
 				navigator.geolocation.getCurrentPosition(function(position){
 					showRoute(position, id, map, layerGroup);	 					
 				}, error, {timeout:10000});	 			
			} else {
				alert('Ihr Gerät scheint die HTML5 Geolocation API nicht zu unterstützen.');// todo - link to google maps only with destination, user has to put in start
			} 

	 		
	 	});


	 	$('#map').show();

	 	var map = new L.Map('map', {
	 		zoomControl: false
	 	});

		var layerGroup = new L.LayerGroup();
	 	
	 	var osm = new L.TileLayer('tiles.php?z={z}&x={x}&y={y}', {
            attribution: ''
		});
	 	
	 	map.setView([51.9663, 7.6099], 14).addLayer(osm);
	 	
	 	map.on('locationfound', onLocationFound);	 		 	
	 	
	 	var destlat, destlng ; // we'll assign these later
	 	
	 	function onLocationFound(e) {
	 	    var marker = new L.Marker(e.latlng);
	 	    map.addLayer(marker);
	 	}	 	
	";
	
	
	if(isset($orgDetails->wkt->value)){ // handle orgs with WKTs for buildings
		
		
		include_once('geoPHP.inc');
	
		//clean the WKT from the CDATA stuff to make geoPHP swallow it:
		$wkt = str_ireplace("<![CDATA[ <http://www.opengis.net/def/crs/OGC/1.3/CRS84> ", "", $orgDetails->wkt->value);
		$wkt = str_ireplace(" ]]>", "", $wkt);			
		
			
		$wkt_reader = new WKT();
		$geometry = $wkt_reader->read($wkt,TRUE);
		$centroid = $geometry->centroid();
		$x = $centroid->x();
		$y = $centroid->y();
		$json_writer = new GeoJSON();
		$json_geometry = $json_writer->write($geometry);
	
		
		// let's add some info details to the geojson; they will be shown in the popup bubble:
		$json_geometry = '{'.substr($json_geometry, 1); 
	echo "	
		
		var center = new L.LatLng(" .$y. ", ".$x.");
		destlat =  " .$y. ";
		destlng =  " .$x. ";
		map.setView(center, 17);
		var geojsonLayer = new L.GeoJSON();
		
		geojsonLayer.on('featureparse', function (e) {
		    if (e.properties && e.properties.popupContent){
		        e.layer.bindPopup(e.properties.popupContent);
		    }
		});
		
		var geoJSONfeature = ".$json_geometry.";
		geojsonLayer.addData(geoJSONfeature);
						
		map.addLayer(geojsonLayer);
		";
	
		
	}else{  //handle orgs that only have lat/lon
		echo "
		var center = new L.LatLng(".$orgDetails->lat->value.", ".$orgDetails->long->value.");
		destlat =  " .$orgDetails->lat->value. ";
		destlng =  " .$orgDetails->long->value. ";
		map.setView(center, 17);
	
		var marker = new L.Marker(center);
		map.addLayer(marker);	
		";	
	}
		echo"			
		// fixes the problem where some map tiles are not shown initally:
		L.Util.requestAnimFrame(map.invalidateSize,map,!1,map._container);

		$('div.leaflet-control-attribution').hide();
	});	
	</script>
		";


	} else { // no lat/lon nor WKT - show notification that we don't have a map for this one:
		echo "
			<script>
				$(function(event){
					$('#address').after('<p class=\"lead alert\">Für diese Einrichtung steht leider keine Karte / Navigation zur Verfügung.</p>');
				});				
			</script>
		";		
	}
}

// loads the details for this organization

function getOrgDetails($org, $lang = "de"){
	
	$query = "

prefix foaf: <http://xmlns.com/foaf/0.1/> 
prefix geo: <http://www.w3.org/2003/01/geo/wgs84_pos#> 
prefix vcard: <http://www.w3.org/2006/vcard/ns#>
prefix lodum: <http://vocab.lodum.de/helper/>
prefix ogc: <http://www.opengis.net/ont/OGC-GeoSPARQL/1.0/>
prefix xsd: <http://www.w3.org/2001/XMLSchema#> 

SELECT DISTINCT ?name ?homepage ?address ?street ?zip ?city ?buildingaddress ?lat ?long ?wkt WHERE {
  
  <".$org."> foaf:name ?name.
  
  OPTIONAL { <".$org."> foaf:homepage ?homepage . }
  
  OPTIONAL { <".$org."> vcard:adr ?address . 
  	FILTER ( datatype(?address) = xsd:string )
  }
  
  OPTIONAL { <".$org."> lodum:building ?building .              
                       
     OPTIONAL { ?building geo:lat ?lat ; 
                              geo:long ?long . }
             
     OPTIONAL { ?building vcard:adr ?buildingAddress . 

     			?buildingAddress vcard:street-address ?street ;
     			    vcard:postal-code ?zip ;
     			    vcard:region ?city .     			
     } 
         
     OPTIONAL { ?building ogc:hasGeometry ?geometry .
                          ?geometry ogc:asWKT ?wkt . } 
         
  }   
  
  FILTER langMatches(lang(?name),'".$lang."') . 
}

	";
	$orgDetails = sparql_get($query);	
	
	if( !isset($orgDetails) ) {
		print '<p class="alert alert-error">Fehler beim Abruf der Informationen über diese Organisation:</li>';		
	}else{		

		// only start if there are any results:
		if($orgDetails->results->bindings){
			
			$thisOrg = $orgDetails->results->bindings[0];			
			
			$orgName = $thisOrg->name->value;

			if(endsWith($orgName, " Institut für")){
				$orgName = "Institut für ".substr($orgName, 0, -13);
			}

			echo '<div class="row-fluid"><div class="span12" id="orgInfo"><h1><span id="title">'.$orgName.'</span></h1>
						

				<div class="btn-group btn-group-vertical" style="float:right; margin-bottom: 15px">';

				
				if(isset($thisOrg->homepage->value)){

					// remove http:// and trailing slash from the website for display:
					$www = str_replace('http://', '', $thisOrg->homepage->value);
					if ( endsWith($www, '/') ) { $www = substr($www, 0, -1); }

					echo ' <a class="btn" style="width: 70px" href="'.$thisOrg->homepage->value.'" target="_blank"><i class="icon-globe"></i> Website</a>
					';
				}
				
					// Bookmark Button:
					echo '<button id="favorite" class="btn" style="width:96px"><i class="icon-star"></i> Merken</button>					

				</div>

				<p class="lead" id="address"><span style="margin-right: 20px">
				';
				
				$dest = '';
				$destAddr = '';

				// use the coords as destination for the navigation
				if((isset($thisOrg->lat->value) && isset($thisOrg->long->value))){
					$dest = $thisOrg->lat->value.','.$thisOrg->long->value;
				}


				// ... or the address
				if(isset($thisOrg->address->value)){
					$destAddr = urlencode($thisOrg->address->value);
					echo $thisOrg->address->value.'</span>';
				} else if(isset($thisOrg->street->value) && isset($thisOrg->zip->value) && isset($thisOrg->city->value)) {
					$destAddr = urlencode($thisOrg->street->value.', '.$thisOrg->zip->value.' '.$thisOrg->city->value);
					echo $thisOrg->street->value.', '.$thisOrg->zip->value.' '.$thisOrg->city->value.'</span>';
				}

				?>

				</p>				

				<?php
				
				echo "

					<script>
						// forward to google maps for public transport options
						function enableBusRoute(position) {
			  
						  // add the following parameters to the URI in case we want to distinguish the 
					      // different routing options for google maps inside the web app at some point:
		
						  // dirflg=r: rail / public transport
						  // dirflg=w: walk
						  // default: car
		
						  var uri = 'https://maps.google.com/maps?saddr='+position.coords.latitude+','+position.coords.longitude+'&daddr=";
						  
						  if($destAddr != ''){ echo $destAddr; } else { echo $dest; }

						  echo "&hl=de&ie=UTF8&ttype=now&dirflg=r&noexp=0&noal=0&sort=def&mra=ltm&t=m&start=0';

						  $('a#bus').attr('href', uri);
						  
						}

						// all other routing requests:
						function showRoute(position, mode, map, layerGroup) {
			  			  
						  // we round the current position to 4 digits (a couple of meters) to avoid unnecessary requests to the routing service:
						  var url = 'route.php?coords='+(Math.round(10000*position.coords.latitude)/10000)+','+(Math.round(10000*position.coords.longitude)/10000)+',".$dest."&mode='+mode+'&lang=de';

						  $('#navlogo').hide();
						  $('#navloader').show();
						  $.ajax({
					 		    url: url,	   
					 		    success: function(json) {
					 			    
					 		    	var polyline = L.polyline(json.route_geometry, {color: 'red'});
					 			    
					 			    map.removeLayer(layerGroup);
					 			    layerGroup.clearLayers();
					 			    layerGroup.addLayer(polyline);
					 			    map.addLayer(layerGroup);

									// zoom the map to the polyline
									map.fitBounds(polyline.getBounds());
					 		    	
					 		        // show instructions:
					 		        $('#instructions').empty();
					 		        
					 		        // headline: distance / duration:
					 		        var distance = json.route_summary.total_distance/1000;
									distance = Math.round(10*distance)/10;
									
									$('#instructions').append('<h4>Deine Route: '+distance+'km</h4>');
					 		        
					 		        $('#instructions').append('<table class=\"table table-striped table-bordered\" id=\"instructionsTable\">');
  
  									var j = 0;
					 		        $.each(json.route_instructions, function(i){
					 		        	var thisInstruction = json.route_instructions[i];
					 		        	$('#instructionsTable').append('<tr><td>' + (i+1) + '</td><td>' + thisInstruction[0] +'</td><td>' + thisInstruction[4] + '</td></tr>');
					 		        	j++;
					 		        });
									
									$('#instructionsTable').append('<tr><td>' + (j+1) + '</td><td><strong>Du hast dein Ziel erreicht.</strong></td><td><i class=\"icon-flag\"></i></td></tr>');

									$('#navloader').hide();
						  			$('#navlogo').show();
					 		        
					 		    }	   
					 		});						  
		
						}
					</script>
				
				";
				
				
				
				
					if(isset($thisOrg->wkt->value) || (isset($thisOrg->lat->value) && isset($thisOrg->long->value))){
						?>
						
						<div class="btn-toolbar" style="text-align: center">
							<div class="btn-group" id="navbuttons">
	  							<button class="btn btn-warning"><img src="img/route.png" id="navlogo" style="height: 24px; width: 24px; margin: -3px 0 -3px -3px"  /><img src="img/loader.gif" style="height: 24px; width: 24px; ; margin: -3px 0 -3px -3px; display: none" id="navloader" /><span class="hidden-phone" style="color: black"> Wegbeschreibung </button>
	  							<button class="btn route" id="bicycle"><span class="visible-phone">Rad</span><span class="hidden-phone">Per Fahrrad</button>
	  							<button class="btn route" id="foot">Zufuß</button>
	  							<button class="btn route" id="car"><span class="visible-phone">Auto</span><span class="hidden-phone">Mit dem Auto</button>
	  							<a class="btn route" href="https://maps.google.com/maps?daddr=<?php if($destAddr != ''){ echo $destAddr; } else { echo $dest; } ?>&hl=de&ie=UTF8&ttype=now&dirflg=r&noexp=0&noal=0&sort=def&mra=ltm&t=m&start=0';" id="bus"><span class="visible-phone">Bus</span><span class="hidden-phone">Mit dem Bus (öffnet Google Maps)</a>  							
							</div>

						</div>

						<div class="container" id="instructions"></div>

					<?php
			 		} //end if

			 		// show suborganizations (for 'Fachbereich' pages) or food offerings (for 'Mensa' pages)
			 		listSubOrganizations();
			 		listMenu();			 	 
				
 			echo '</div>
 			</div>
 			';  			 			
 			
 			return $thisOrg;
 		}
 	}

}

function listMenu(){
	
	$l = setlocale(LC_TIME, 'deu', 'de_DE.UTF-8');

	$time = strtotime('monday this week');  	
	$date = date('Y-m-d', $time);  	
	$datetime = $date.'T00:00:00Z';

	$food = sparql_get('
prefix xsd: <http://www.w3.org/2001/XMLSchema#> 
prefix gr: <http://purl.org/goodrelations/v1#>
prefix foaf: <http://xmlns.com/foaf/0.1/> 

SELECT DISTINCT ?name ?start ?minPrice ?maxPrice WHERE {
  <'.$_GET["org_uri"].'> gr:offers ?menu. 
  ?menu a gr:Offering ;
        gr:availabilityStarts ?start ;
        gr:name ?name ;
        gr:hasPriceSpecification ?priceSpec .
  ?priceSpec gr:hasMinCurrencyValue ?minPrice ;
             gr:hasMaxCurrencyValue ?maxPrice . 
  FILTER (?start > "'.$datetime.'"^^xsd:dateTime) .
} ORDER BY MONTH(?start) DAY(?start) LCASE(?mensaname) 
');
	
	if( !isset($food) ) {
		echo '<div class="alert alert-error">Fehler beim Abruf der Mensadaten.</div>';
	}else{		

		// only start if there are any results:
		if($food->results->bindings){
			$header = false;
			
			$tag = 'none';
			$weekdays = array("Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag");
			$weekday = 0;

 			foreach ($food->results->bindings as $menu) {
 				$other = false;
 				
				// create a new list for each day of the week:
 				$day = substr($menu->start->value, 0, 10);

 				if(!$header){
 					echo '<div class="container"><div class="row-fluid">
 					<div class="span12">
 					<h2>Angebote in der Woche vom '.strftime('%e. %B %Y', strtotime($menu->start->value)).'</h2>
 					<hr />
 					</div>
 					</div>
 					';
 					$header = true;
 				}

 				if($day !== $tag){
					if($weekday < count($weekdays)){ 					
 						echo '</tbody></table>';
 						// close the span6 div - but only if we have opened it before!
 						if ($tag !== 'none'){
 							echo '</div>';
 						}
 						// close the row-fluid div after every other span6 div
 						if($other){
 							echo '</div>';
 						}

 						echo '<div class="row-fluid"><div class="span6"';
 						if(!$other){ // move the right column a bit 
 							echo ' style="padding-right: 20px"';
 						}
 						echo '><h3 id="'.$day.'">'.$weekdays[$weekday++].'</h3>
 						<table class="table table-bordered table-striped">';
 						$tag = $day;
 						$other = !$other;

 					}else{
 						$weekday++;
 					}
 				}

	 			if($weekday <= count($weekdays)){	
					echo '<tr><td>'.$menu->name->value.' <span class="pull-right">'.$menu->minPrice->value.'€ | '.$menu->maxPrice->value.'€</span></td></tr>';
 				} 								
 			}  

 			echo '</tbody></table></div></div>';	
 		}
 	}

}


function listSubOrganizations(){

	$orgs = sparql_get("

		prefix foaf: <http://xmlns.com/foaf/0.1/> 
		prefix aiiso: <http://purl.org/vocab/aiiso/schema#>
		prefix lodum: <http://vocab.lodum.de/helper/>
				
		SELECT DISTINCT ?orga ?name WHERE { 
				
			Graph <http://data.uni-muenster.de/context/uniaz/> {
		          ?orga a ?type ; 
			            foaf:name ?name ;
			  aiiso:part_of <".$_GET['org_uri']."> .
			  
			  BIND(lcase(?name) as ?lname) .
			  FILTER langMatches(lang(?name),'DE') .
			  FILTER (STRLEN(?name) > 0) .
			  FILTER regex(str(?orga),'uniaz') . 
		    }

		} ORDER BY ?lname
	");
	
	// only start if there are any results:
	if($orgs->results->bindings){
		echo '<h2>Untergeordnete Einrichtungen</h2>

		<div class="btn-group btn-group-vertical" style="width: 100%">';
		
		foreach ($orgs->results->bindings as $institut) {
				
			$name = $institut->name->value;
			$orga = $institut->orga->value;
 			
 			echo '<a class="btn btn-large btn-stacked internal" href="orgdetails.php?org_uri='.$orga.'">'.$name.'</a>';
 		}

 		echo '</div>';

 	} 

}

flushCache();

?>