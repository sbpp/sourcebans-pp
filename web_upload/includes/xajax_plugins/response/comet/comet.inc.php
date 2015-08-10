<?php
		define('FILE_APPEND', 1);

/*
	File: comet.inc.php


	Title: clsCometStreaming class

*/

if (false == class_exists('xajaxPlugin') || false == class_exists('xajaxPluginManager'))
{
	$sBaseFolder = dirname(dirname(dirname(__FILE__)));
	$sXajaxCore = $sBaseFolder . '/xajax_core';

	if (false == class_exists('xajaxPlugin'))
		require $sXajaxCore . '/xajaxPlugin.inc.php';
	if (false == class_exists('xajaxPluginManager'))
		require $sXajaxCore . '/xajaxPluginManager.inc.php';
}


/*
	Class: clsCometStreaming
*/
class clsCometStreaming extends xajaxResponsePlugin
{
	/*
		String: sDefer
		
		Used to store the state of the scriptDeferral configuration setting.  When
		script deferral is desired, this member contains 'defer' which will request
		that the browser defer loading of the javascript until the rest of the page 
		has been loaded.
	*/
	var $sDefer;
	
	/*
		String: sJavascriptURI
		
		Used to store the base URI for where the javascript files are located.  This
		enables the plugin to generate a script reference to it's javascript file
		if the javascript code is NOT inlined.
	*/
	var $sJavascriptURI;
	
	/*
		Boolean: bInlineScript
		
		Used to store the value of the inlineScript configuration option.  When true,
		the plugin will return it's javascript code as part of the javascript header
		for the page, else, it will generate a script tag referencing the file by
		using the <clsTableUpdater->sJavascriptURI>.
	*/
	var $bInlineScript;
	
	
	var  $fTimeOut;
	/*
		Function: clsTableUpdater
		
		Constructs and initializes an instance of the table updater class.
	*/
	function clsCometStreaming()
	{
		$this->sDefer = '';
		$this->sJavascriptURI = '';
		$this->bInlineScript = false;
	}
	/*
		Function: configure
		
		Receives configuration settings set by <xajax> or user script calls to 
		<xajax->configure>.
		
		sName - (string):  The name of the configuration option being set.
		mValue - (mixed):  The value being associated with the configuration option.
	*/
	function configure($sName, $mValue)
	{
		if ('scriptDeferral' == $sName) {
			if (true === $mValue || false === $mValue) {
				if ($mValue) $this->sDefer = 'defer ';
				else $this->sDefer = '';
			}
		} else if ('javascript URI' == $sName) {
			$this->sJavascriptURI = $mValue;
		} else if ('inlineScript' == $sName) {
			if (true === $mValue || false === $mValue)
				$this->bInlineScript = $mValue;
		} else if ('cometsleeptimout' == strtolower($sName) ) {
			if ( is_numeric($mValue) )
				$this->fTimeOut = $mValue;
		}
	}
	
	/*
		Function: generateClientScript
		
		Called by the <xajaxPluginManager> during the script generation phase.
		
	*/
	function generateClientScript()
	{
		if ($this->bInlineScript)
		{
			echo "\n<script type='text/javascript' " . $this->sDefer . "charset='UTF-8'>\n";
			echo "/* <![CDATA[ */\n";

			include(dirname(__FILE__) . 'xajax_plugins/response/comet/comet.js');

			echo "/* ]]> */\n";
			echo "</script>\n";
		} else {
			echo "\n<script type='text/javascript' src='" . $this->sJavascriptURI . "xajax_plugins/response/comet/comet.js' " . $this->sDefer . "charset='UTF-8'></script>\n";
		}
	}
	
	
}

class xajaxCometResponse extends xajaxResponse 
{
	var $bHeaderSent = false;
	var $fTimeOut=1;


	/*
		Function: xajaxCometResponse
		
		calls  parent function xajaxResponse();
	*/
	
	function xajaxCometResponse($fTimeOut=false)
	{

		if ( false != $fTimeOut ) $this->fTimeOut=$fTimeOut;

		parent::__construct();		
		
		
	}

	/*
		Function: printOutput
		
		override the original printOutput function. It's no longer needed since the output is already sent.
	*/

	function printOutput()
	{
		$this->flush();
		if ( "HTML5DRAFT" == $_REQUEST['xjxstreaming']) {

			$response = "";
		  $response .= "Event: xjxendstream\n";
	    $response .=  "data: done\n";
	    $response .= "\n";
			print $response;
			
		}
	}

	/*
		Function: flush_XHR
		
		Flushes the command queue for comet browsers.
	*/

	function flush_XHR() 
	{
		
		if (!$this->bHeaderSent) 
		{
			$this->_sendHeaders();
			$this->bHeaderSent=true;
		}
		
		ob_start();
		$this->_printResponse_XML();
		$c = ob_get_contents();
		ob_get_clean();
		$c = str_replace(chr(1)," ",$c);
		$c = str_replace(chr(2)," ",$c);
		$c = str_replace(chr(31)," ",$c);
		$c = str_replace(""," ",$c);
		if ($c == "<xjx></xjx>") return false;
		print $c;
		ob_flush();
		flush();
		$this->sleep( $this->fTimeOut );
	}
	

	/*
		Function: flush_activeX
		
		Flushes the command queue for ActiveX browsers.
	*/

	function flush_activeX() 
	{
		ob_start();
		$this->_printResponse_XML();
		$c = ob_get_contents();
		ob_end_clean();
		
		$c = '<?xml version="1.0" ?>'.$c;
		$c = str_replace('"','\"',$c);
		$c = str_replace("\n",'\n',$c);
		$c = str_replace("\r",'\r',$c);

		$response = "";
		$response .= "<script>top.document.callback(\"";
		$response .= $c;
		$response .= "\");</script>";
		
		
		print $response;
		ob_flush();
		flush();
		$this->sleep( $this->fTimeOut-0.1 );
	}

	/*
		Function: flush_HTML5DRAFT
		
		Flushes the command queue for HTML5DRAFT browsers.
	*/

	function flush_HTML5DRAFT() 
	{


		if (!$this->bHeaderSent) 
		{
			header("Content-Type: application/x-dom-event-stream");
			$this->bHeaderSent=1;
		}
		
		ob_start();
		$this->_printResponse_XML();
		$c = ob_get_contents();
		ob_end_clean();
		$c = str_replace("\n",'\n',$c);
		$c = str_replace("\r",'\r',$c);
		$response = "";
	  $response .= "Event: xjxstream\n";
    $response .=  "data: $c\n";
    $response .= "\n";
		print $response;
		ob_flush();
		flush();
		$this->sleep( $this->fTimeOut );
		
	}


	/*
		Function: flush
		
		Determines which browser is wating for a response and calls the according flush function.
	*/
	function flush() 
	{
		if (0 == count($this->aCommands)) return false;
		if ("xhr" == $_SERVER['HTTP_STREAMING']) 
		{
			$this->flush_XHR();
		} 
		elseif ( "HTML5DRAFT" == $_REQUEST['xjxstreaming'])
		{
			$this->flush_HTML5DRAFT();
		}
		else
		{
			$this->flush_activeX();
		}
		$this->aCommands=array();
	}
 
	/*
		Function: sleep
		
		Very accurate sleep function.
	*/
	function sleep($seconds) 
	{
	   usleep(floor($seconds*1000000));
	}
	
}

$objPluginManager =& xajaxPluginManager::getInstance();
$objPluginManager->registerPlugin(new clsCometStreaming());
