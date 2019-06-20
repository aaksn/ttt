<?php

    // ��������� ������������ �����
    if ($_POST['submit']){

        // �������� �����������
        $Image = '';

        // ���� ����������� ���������, �� ���������� ��� ��� ��������
        if (is_uploaded_file($_FILES['imageFile']['tmp_name'])){

            $Image = $_FILES['imageFile'];
        }

        // ���� ����������� �� ���������
        if (!$Image){

            // ����� ��������������� ���������
            exit('<strong>��� ��������� ����������� ��� ����������</strong>');
        }


        // ���������� �����������
        $SecretData = array();

        // ���� ���������� ����������� ���������
        if (is_uploaded_file($_FILES['secretFile']['tmp_name'])){

            // ��������� ���� � ������ ���������� ������
            array_push($SecretData, $_FILES['secretFile']);
        }

        // ���� ������� ���������� ���������
        if ($_POST['secretMessage']){

            // ��������� ��������� � ������ ���������� ������
            array_push($SecretData, $_POST['secretMessage']);
        }

        // ��������� � ������ �������������
        require_once('steganography.php');
        $stegano = new Stega();

        // ���� ��� ������ ��� ��������
        if (count($SecretData) < 1){

            // �� ��������� �����������
            $stegano->Get($Image, $_POST['key']);

        } else {

            // ����� - ������� �����������
            $stegano->Put($SecretData, $Image, $_POST['key']);
        }

    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
	<link rel="stylesheet" href="css/style.css" media="screen" type="text/css" />
	<link class="jsbin" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1/themes/base/jquery-ui.css" rel="stylesheet" type="text/css" />
	<script class="jsbin" src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
	<script class="jsbin" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.0/jquery-ui.min.js"></script>
	<script type="text/javascript" src="image.js"></script>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<title>�������������</title>
  </head>

<body>
<div id="login-form">
<h1>�������������</h1>
<fieldset>
<form name="steggerForm" method="post" action="<?php echo($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">


  <p>������� �����������, � ������� ������� ������ ������ ��� �� �������� ������� ������� ������� ������.</p>

  <blockquote>
    <strong>�������� ��������� �����������:</strong><br />
    <input id="opImg1" type="file" name="imageFile" size="40" onchange="showImg(this,0);show(img.a);"/>
    <img id="inputimg" src="#" alt="�������� �����������" />
  </blockquote>


  <p>������� ���� ��� �������� / ���������� ������.</p>

  <blockquote>
    <strong>���� ��� �������� / ���������� ������:</strong><br />
    <input type="text" name="key" size="40" />
  </blockquote>


  <p>������� ������, ������� ��������� ������ � �������� �����������. ��� ��������� �������� ������ �������� ���� �������.</p>

  <blockquote>
    <strong>�������� ����������� �����������:</strong><br />
    <input type="file" name="secretFile" size="40" onchange="showImg(this,1);show(img.b);"/>
    <img id="outputimg" src="#" alt="���������� �����������" />
    <h2>���</h2>
    <strong>���������� ���������:</strong><br />
    <textarea name="secretMessage" rows="8" cols="90"></textarea>
  </blockquote>

  <input type="submit" name="submit"  value="���������" />
  <input type="reset" name="reset" onclick="hide(img.a);hide(img.b);" value="�������� ������" />

</form>
</fieldset>
</div>
</body>
</html>
