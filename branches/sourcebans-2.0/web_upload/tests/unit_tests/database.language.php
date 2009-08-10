<?php
// This test will make sure there are the same amount of translations in each language

require_once "init.php";
require_once "tests/test_helpers.class.php";
require_once "tests/test_suite.class.php";

class CDBLanguage extends CTest implements ITest
{
	public function __construct($name)
	{
		$this->testName = $name;
	}
	
	public function RunTest()
	{
		$db = Env::get('db');
		
		// ENGLISH
		$res = $db->Execute("SELECT count(*) as cnt 
							FROM " . Env::get('prefix') . "_translations
							WHERE language = 'en';");
		if( !$res )
		{
			echo "	Error getting 'en' language count\n";
			return false;
		}
		
		$enCount = $res->fields['cnt'];
		
		
		// NL
		$res = $db->Execute("SELECT count(*) as cnt 
							FROM " . Env::get('prefix') . "_translations
							WHERE language = 'nl';");
		if( !$res )
		{
			echo "	Error getting 'nl' language count\n";
			return false;
		}
		$nlCount = $res->fields['cnt'];
		
		
		// DE
		$res = $db->Execute("SELECT count(*) as cnt 
							FROM " . Env::get('prefix') . "_translations
							WHERE language = 'de';");
		if( !$res )
		{
			echo "	Error getting 'de' language count\n";
			return false;
		}
		$deCount = $res->fields['cnt'];
		
		////
		
		if( $nlCount != $enCount )
		{
			echo "	nl translation count not eq to en\n";
			return false;
		}
		else if( $deCount != $enCount )
		{
			echo "	de translation count not eq to en\n";
			return false;
		}
		
		return true;
	}
}

return new CDBLanguage("Language Count (database.language.php)");

?>
