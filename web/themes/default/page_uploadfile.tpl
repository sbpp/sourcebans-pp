<html>
<head>
<title>Upload File : SourceBans</title>
<link rel="Shortcut Icon" href="../images/favicon.ico" />
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.7.0/css/all.css">
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
<b>{$message nofilter}</b>
<form action="" method="POST" id="{$form_name}" enctype="multipart/form-data">
<input name="upload" value="1" type="hidden">
<input name="{$input_name}" size="25" class="submit-fields" type="file"> <br />
<button style="background-color: #e9e9e9;
	font-weight: bold;
	margin: 0 0.5em;" type="submit"><i class="fas fa-save"></i> Save</button>

</form>
</body>
</html>
