<?php 
header("Access-Control-Allow-Origin: *");
?>
<!-- <title>webdamn.com : Demo To Create Simple REST API with PHP and MySQL</title> -->
<?php include('inc/container.php');?>
<div class="container">
	<!-- <h2>Simple REST API with PHP and MySQL</h2>	 -->
	<br>
	<br>
	<form action="endpoints/login.php" method="POST">
		<div class="form-group">
			<label for="name">username</label>
			<input type="text" name="username" value="sacure" class="form-control" required/>
		</div>
		<div class="form-group">
			<label for="name">password</label>
			<input type="text" name="password" value="1234567" class="form-control" required/>
		</div>
		<button type="submit" name="submit" class="btn btn-default">Make API Request</button>
	</form>
	<p>&nbsp;</p>
</div>
