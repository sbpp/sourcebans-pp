<html>
<head>
<title>Upload File : SourceBans</title>
<link rel="Shortcut Icon" href="../images/favicon.ico" />
</head>
<body bgcolor="e9e9e9" style="
	background-repeat: repeat-x;
	color: #444;
	font-family: Verdana, Arial, Tahoma, Trebuchet MS, Sans-Serif, Georgia, Courier, Times New Roman, Serif;
	font-size: 11px;
	line-height: 135%;
	margin: 5px;
	padding: 0px;
   ">
<h3>{$title}</h3>


Plese select the file to upload. The file must either be {$formats} file format.<br>
<b>{$message}</b>
<form action="" method="POST" id="{$form_name}" enctype="multipart/form-data">
<input name="upload" value="1" type="hidden">
<input name="{$input_name}" size="25" class="submit-fields" type="file"> <br />
<button style="background-color: #e9e9e9;
	background-repeat: no-repeat;
	background-position: 2px 50%;
	padding:1px 1px 1px 20px;
	font-weight: bold;
	margin: 0 0.5em;
	background-image: url(../images/save.gif);" type="submit">Save</button>

</form>
</body>
</html>
