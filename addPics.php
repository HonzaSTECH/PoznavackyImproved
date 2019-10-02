<?php
	$redirectIn = false;
	$redirectOut = true;
	require 'verification.php';    //Obsahuje session_start();
	
	if (!isset($_SESSION['current']))	//Poznávačka nenastavena --> přesměrování na stránku s výběrem
	{
		echo "<script type='text/javascript'>location.href = 'list.php';</script>";
	}
?>
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width" />
		<link rel="stylesheet" type="text/css" href="css/css.css">
		<script type="text/javascript" src="addPics.js"></script>
		<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
		<link rel="icon" href="favicon.ico">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/icon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/icon-16x16.png">
		<link rel="manifest" href="/manifest.json">
		<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#ffc835">
		<meta name="theme-color" content="#ffffff">
		<title>Přidat obrázky</title>
	</head>
	<body>
    <div class="container">
        <header>
            <h1>Přidat obrázky</h1>
        </header>
		<main class="basic_main">
    		<form>
    			<fieldset id="field1">
    				<div class="prikaz">Vyberte přírodninu, kterou chcete nahrát. V závorce je uvedeno množství obrázků dané přírodniny. Nahrávejte prosím především obrázky přírodnin s menším číslem.</div>
    				<select onchange="selected1()" id="dropList" class="text">
    					<option value="" selected disabled hidden></option>
    					<?php 
    						//Vypisování přírodnin
    						$table = $_SESSION['current'][0].'seznam';
    							
    						include 'connect.php';
    						$query = "SELECT * FROM $table ORDER BY nazev,obrazky,id";
    						$result = mysqli_query($connection, $query);
    						while($row = mysqli_fetch_array($result))
    						{
    							$name = $row['nazev'];
    							$count = $row['obrazky'];
    							echo "<option>$name ($count)</option>";
    						}
    					?>
    				</select>
    			</fieldset>
    			<fieldset id="field2">
    				<div id="duckLink_div"><a id="duckLink" target=_blank>  
    					<div><span>Vyhledat na </span><img id="duckLogo" src="images/duckLogo.png"></div>       
    				</a></div>       
    				<input type=url placeholder="Vložte URL obrázku" id="urlInput" class="text" onkeyup="urlTyped()"/>
    				<button id="urlConfirm" onclick="selected2(event)" class="buttonDisabled" disabled>OK</button>
    			</fieldset>
    				<img id="previewImg" class="img" src="images/imagePreview.png">
    			<fieldset>
    				<input type=submit value="Přidat" onclick="add(event)" id="sendButton" class="buttonDisabled" disabled />
    				<button id="resetButton" onclick="resetForm(event)" class="button">Reset</button>
    			</fieldset>
    		</form>
    		<a href="menu.php"><button class="button">Zpět</button></a>
    	</main>
    </div>
		<footer>
			<div id="help" class="footerOption"><a target='_blank' href="https://github.com/HonzaSTECH/Poznavacky/wiki">Nápověda</a></div>
			<div id="issues" class="footerOption" onclick="showLogin()"><a target='_blank' href="https://github.com/HonzaSTECH/Poznavacky/issues/new/choose">Nalezli jste problém?</a></div>
			<div class="footerOption"><a target='_blank' href='https://github.com/HonzaSTECH/Poznavacky/blob/master/TERMS_OF_SERVICE.md'>Podmínky služby</a></div>
			<div id="about" class="footerOption">&copy Štěchy a Eksyska, 2019</div>
         	<script>
             	function showLogin()
             	{
             		alert("Přihlašovací údaje pro nahlašování chyby:\nJméno: gjvj\nHeslo: poznavacky71");
             	}
         	</script>
         </footer>
	</body>
	<script>resetForm();</script>
</html>
