<?php  
   	require_once("functions.php");
   	checkCache();
	getHead();
?>

	<div class="container">
			
		<?php 
			if(isset($_GET['lang'])){
				getFBs($_GET['lang']);			 
			} else {
				getFBs();
			}
		?>	

		
	</div><!--/container -->


<?php

getFoot();

// loads all university departments in $lang (currently supported: de, en (TODO!))

function getFBs($lang = "de"){
	
	echo '<div class="row-fluid">
		<h1>Fachbereiche</h1>
		</div>
		<div class="row-fluid">
		<div class="btn-group btn-group-vertical" style="width: 100%">
		';

	$fbs = sparql_get("


prefix foaf: <http://xmlns.com/foaf/0.1/> 
prefix lodum: <http://vocab.lodum.de/helper/>
prefix owl: <http://www.w3.org/2002/07/owl#>

SELECT DISTINCT * WHERE {
  ?fb a lodum:Department ;
     foaf:name ?name;
     lodum:departmentNo ?no.   
  FILTER langMatches(lang(?name),'".$lang."') . 
  FILTER regex(?name,' - ') . 
  FILTER regex(str(?fb), '/fb') .
} ORDER BY ?no

");
	
	if( !isset($fbs) ) {
		print "<li>Fehler beim Abruf der Fachbereichsdaten.</li>";
	}else{		

		// only start if there are any results:
		if($fbs->results->bindings){
			
			foreach ($fbs->results->bindings as $fb) {
 				
 				$name  = $fb->name->value;
 				$title = substr($name, 0, 14);
 				$desc  = substr($name, 17);
 				$title = str_replace("Fachbereich 0", "Fachbereich ", $title);
 				$url   = $fb->fb->value;
 				echo '<a class="btn btn-large btn-stacked internal" href="orgdetails.php?org_uri='.$url.'">'.$title.'<br class="visible-phone" /><span class="hidden-phone" style="margin-left:30px">&nbsp;</span><small>'.$desc.'</small></a>'; 				 				
 			}
 		 			
 		}
 	}

 	echo '</div>';

}


flushCache();

?>