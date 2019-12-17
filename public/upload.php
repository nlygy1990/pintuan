<?php 
		$filename=$_FILES['file']['name'];
        $type=$_FILES['file']['type'];
        $tmp_name=$_FILES['file']['tmp_name'];
        $size=$_FILES['file']['size'];
        $error=$_FILES['file']['error'];
 
 
        var_dump($_FILES['file']);
 ?>