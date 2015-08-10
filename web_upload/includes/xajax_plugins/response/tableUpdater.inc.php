<?php
if(false==class_exists('xajaxPlugin')||false==class_exists('xajaxPluginManager')){$sBaseFolder=dirname(dirname(dirname(__FILE__)));$sXajaxCore=$sBaseFolder . '/xajax_core';if(false==class_exists('xajaxPlugin'))
require $sXajaxCore . '/xajaxPlugin.inc.php';if(false==class_exists('xajaxPluginManager'))
require $sXajaxCore . '/xajaxPluginManager.inc.php';}
class clsTableUpdater extends xajaxResponsePlugin{var $sDefer;var $sJavascriptURI;var $bInlineScript;function clsTableUpdater(){$this->sDefer='';$this->sJavascriptURI='';$this->bInlineScript=true;}
function configure($sName,$mValue){if('scriptDeferral'==$sName){if(true===$mValue||false===$mValue){if($mValue)$this->sDefer='defer ';else $this->sDefer='';}
}else if('javascript URI'==$sName){$this->sJavascriptURI=$mValue;}else if('inlineScript'==$sName){if(true===$mValue||false===$mValue)
$this->bInlineScript=$mValue;}
}
function generateClientScript(){if($this->bInlineScript){echo "\n<script type='text/javascript' " . $this->sDefer . "charset='UTF-8'>\n";echo "/* <![CDATA[ */\n";include(dirname(__FILE__). '/tableUpdater.js');echo "/* ]]> */\n";echo "</script>\n";}else{echo "\n<script type='text/javascript' src='" . $this->sJavascriptURI . "tableUpdater.js' " . $this->sDefer . "charset='UTF-8'>\n";}
}
function getName(){return get_class($this);}
function appendTable($table,$parent){$command=array(
'cmd'=>'et_at',
'id'=>$parent
);$this->addCommand($command,$table);}
function insertTable($table,$parent,$position){$command=array(
'cmd'=>'et_it',
'id'=>$parent,
'pos'=>$position
);$this->addCommand($command,$table);}
function deleteTable($table){$this->addCommand(
array(
'cmd'=>'et_dt'
),
$table
);}
function appendRow($row,$parent,$position=null){$command=array(
'cmd'=>'et_ar',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,$row);}
function insertRow($row,$parent,$position=null,$before=null){$command=array(
'cmd'=>'et_ir',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;if(null!=$before)
$command['type']=$before;$this->addCommand($command,$row);}
function replaceRow($row,$parent,$position=null,$before=null){$command=array(
'cmd'=>'et_rr',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;if(null!=$before)
$command['type']=$before;$this->addCommand($command,$row);}
function deleteRow($parent,$position=null){$command=array(
'cmd'=>'et_dr',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,null);}
function assignRow($values,$parent,$position=null,$start_column=null){$command=array(
'cmd'=>'et_asr',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;if(null!=$start_column)
$command['type']=$start_column;$this->addCommand($command,$values);}
function assignRowProperty($property,$value,$parent,$position=null){$command=array(
'cmd'=>'et_asrp',
'id'=>$parent,
'prop'=>$property
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,$value);}
function appendColumn($column,$parent,$position=null){$command=array(
'cmd'=>'et_acol',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,$column);}
function insertColumn($column,$parent,$position=null){$command=array(
'cmd'=>'et_icol',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,$column);}
function replaceColumn($column,$parent,$position=null){$command=array(
'cmd'=>'et_rcol',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,$column);}
function deleteColumn($parent,$position=null){$command=array(
'cmd'=>'et_dcol',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,null);}
function assignColumn($values,$parent,$position=null,$start_row=null){$command=array(
'cmd'=>'et_ascol',
'id'=>$parent
);if(null!=$position)
$command['pos']=$position;if(null!=$start_row)
$command['type']=$start_row;$this->addCommand($command,$values);}
function assignColumnProperty($property,$value,$parent,$position=null){$command=array(
'cmd'=>'et_ascolp',
'id'=>$parent,
'prop'=>$property
);if(null!=$position)
$command['pos']=$position;$this->addCommand($command,$value);}
function assignCell($row,$column,$value){$this->addCommand(
array(
'cmd'=>'et_asc',
'id'=>$row,
'pos'=>$column
),
$value
);}
function assignCellProperty($row,$column,$property,$value){$this->addCommand(
array(
'cmd'=>'et_ascp',
'id'=>$row,
'pos'=>$column,
'prop'=>$property
),
$value
);}
}
$objPluginManager=&xajaxPluginManager::getInstance();$objPluginManager->registerPlugin(new clsTableUpdater());