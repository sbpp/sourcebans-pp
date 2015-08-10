<?php
class clsGoogleMap extends xajaxResponsePlugin{var $sJavascriptURI;var $bInlineScript;function clsGoogleMap(){$this->sJavascriptURI='';$this->bInlineScript=true;}
function configure($sName,$mValue){if('javascript URI'==$sName){$this->sJavascriptURI=$mValue;}else if('inlineScript'==$sName){if(true===$mValue||false===$mValue)
$this->bInlineScript=$mValue;}
}
function generateClientScript(){echo "\n<script src='http://maps.google.com/maps?file=api&amp;v=2&amp;key=";echo $this->sGoogleSiteKey;echo "' type='text/javascript'>\n</script>\n";echo "\n<script type='text/javascript' charset='UTF-8'>\n";echo "/* <![CDATA[ */\n";echo "maps = {};\n";echo "xajax.command.handler.register('gm:cr', function(args) {\n";echo "\tmaps[args.data] = new GMap2(args.objElement);\n";echo "\tvar ptCenter = new GLatLng(0, 10);\n";echo "\tmaps[args.data].setCenter(ptCenter, 10);\n";echo "\tmaps[args.data].addControl(new GSmallMapControl());\n";echo "\tmaps[args.data].addControl(new GMapTypeControl());\n";echo "\tmaps[args.data].setMapType(maps[args.data].getMapTypes()[2]);\n";echo "});\n";echo "xajax.command.handler.register('gm:zm', function(args) {\n";echo "\tmaps[args.id].setZoom(parseInt(args.data));\n";echo "});\n";echo "xajax.command.handler.register('gm:sm', function(args) {\n";echo "\tvar ptCenter = new GLatLng(args.data[0], args.data[1]);\n";echo "\tvar markerNew = new GMarker(ptCenter);\n";echo "\tmarkerNew.text = args.data[2];\n";echo "\tmaps[args.id].addOverlay(markerNew);\n";echo "\tGEvent.addListener(maps[args.id], 'click', function(marker, point) {\n";echo "\t\tif (marker && undefined != marker.openInfoWindowHtml) {\n";echo "\t\t\tmarker.openInfoWindowHtml(marker.text);\n";echo "\t\t}\n";echo "\t} );\n";echo "});\n";echo "/* ]]> */\n";echo "</script>\n";}
function getName(){return get_class($this);}
function setGoogleSiteKey($sKey){$this->sGoogleSiteKey=$sKey;}
function create($sMap,$sParentId){$command=array('n'=>'gm:cr','t'=>$sParentId);$this->addCommand($command,$sMap);}
function zoom($sMap,$nZoom){$command=array('n'=>'gm:zm','t'=>$sMap);$this->addCommand($command,$nZoom);}
function setMarker($sMap,$nLat,$nLon,$sText){$this->addCommand(
array('n'=>'gm:sm','t'=>$sMap),
array($nLat,$nLon,$sText)
);}
function moveTo($sMap,$nLat,$nLon){$command=array('n'=>'et_ar','t'=>$parent);if(null!=$position)
$command['p']=$position;$this->addCommand($command,$row);}
}
$objPluginManager=&xajaxPluginManager::getInstance();$objPluginManager->registerPlugin(new clsGoogleMap());