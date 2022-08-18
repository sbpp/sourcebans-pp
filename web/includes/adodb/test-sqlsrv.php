<?php
/**
 * FileDescription
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v5.22.2  2022-05-08 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-v5.22.2  2022-05-08-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 */
/**
 * FileDescription
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v5.22.2  2022-05-08 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-v5.22.2  2022-05-08-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 * @author John Lim <jlim@natsoft.com>
 * @author Damien Regad
 * @author Mark Newnham
 */
/**
 * FileDescription
 *
 * This file is part of ADOdb, a Database Abstraction Layer library for PHP.
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v5.22.2  2022-05-08 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-v5.22.2  2022-05-08-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 * @author John Lim <jlim@natsoft.com>
 * @author Damien Regad
 * @author Mark Newnham
 */
/**
 * ADOdb Library main include file.
 *
 * This file is part of the ADOdb Library, a Database Abstraction layer for PHP
 *
 * @package ADOdb
 * @link https://adodb.org Project's web site and documentation
 * @link https://github.com/ADOdb/ADOdb Source code and issue tracker
 *
 * The ADOdb Library is dual-licensed, released under both the BSD 3-Clause
 * and the GNU Lesser General Public Licence (LGPL) v5.22.2  2022-05-08 or, at your option,
 * any later version. This means you can use it in proprietary products.
 * See the LICENSE.md file distributed with this source code for details.
 * @license BSD-3-Clause
 * @license LGPL-v5.22.2  2022-05-08-or-later
 *
 * @copyright 2000-2013 John Lim
 * @copyright 2014 Damien Regad, Mark Newnham and the ADOdb community
 * @author John Lim <jlim@natsoft.com>
 * @author Damien Regad
 * @author Mark Newnham
 */
$serverName = "localhost";
$connectionOptions = array(
	"database" => "test",
	"uid" => "SA",
	"pwd" => "C0yote71"
);

function exception_handler($exception) {
	echo "<h1>Failure</h1>";
	echo "Uncaught exception: " , $exception->getMessage();
	echo "<h1>PHP Info for troubleshooting</h1>";
	phpinfo();
}

set_exception_handler('exception_handler');

// Establishes the connection
$conn = sqlsrv_connect($serverName, $connectionOptions);
if ($conn === false) {
	die(formatErrors(sqlsrv_errors()));
}

// Select Query
$tsql = "SELECT @@Version AS SQL_VERSION";

// Executes the query
$stmt = sqlsrv_query($conn, $tsql);

// Error handling
if ($stmt === false) {
	die(formatErrors(sqlsrv_errors()));
}
?>

	<h1> Success Results : </h1>

<?php
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
	echo $row['SQL_VERSION'] . PHP_EOL;
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);

function formatErrors($errors)
{
	// Display errors
	echo "<h1>SQL Error:</h1>";
	echo "Error information: <br/>";
	foreach ($errors as $error) {
		echo "SQLSTATE: ". $error['SQLSTATE'] . "<br/>";
		echo "Code: ". $error['code'] . "<br/>";
		echo "Message: ". $error['message'] . "<br/>";
	}
}
?>
