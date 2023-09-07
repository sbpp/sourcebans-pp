<!doctype html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<link rel="Shortcut Icon" href="../images/favicon.ico" />
		<meta name="description" content="Sourcebans for website - Upload file" />
		<title>Upload File : SourceBans</title>
		<script type="text/javascript" src="../scripts/fontawesome-all.min.js"></script>
	</head>

	<body style="
			background-color: #0f1015;
			background-repeat: repeat-x;
			color: #efefef;
			font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', 
				Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
			font-size: 11px;
			line-height: 135%;
			margin: 5px;
			padding: 0px;
   ">
		<h3>{$title}</h3>
		
		<p>Plese select the file to upload.<br />
		The file must either be {$formats} file format.<br />
		<b>{$message}</b></p>

		<form action="" method="POST" id="{$form_name}" enctype="multipart/form-data">
			<input name="upload" value="1" type="hidden">
			<input name="{$input_name}" size="25" class="submit-fields" type="file">
			<button style="
						background-color: #267b3c;
						display: block;
						font-size: 14px;
						font-weight: 400;
						text-align: center;
						font-family: inherit;
						padding: 5px 10px;
						border-radius: 5px;
						color: #fff !important;
						position: relative;
						z-index: 1;
						overflow: hidden;
						cursor: pointer;
						border: 0;
						margin-top: 5px;"
					type="submit">
				<i class="fas fa-save"></i> Save
			</button>
		</form>
	</body>
</html>
