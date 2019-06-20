<?php

    // обработка отправленной формы
    if ($_POST['submit']){

        // исходное изображение
        $Image = '';

        // если изображение загружено, то используем его как исходное
        if (is_uploaded_file($_FILES['imageFile']['tmp_name'])){

            $Image = $_FILES['imageFile'];
        }

        // если изображение не загружено
        if (!$Image){

            // выдаём соответствубщее сообщение
            exit('<strong>Нет исходного изображения для шифрования</strong>');
        }


        // скрываемое изображение
        $SecretData = array();

        // если скрываемое изображение загружено
        if (is_uploaded_file($_FILES['secretFile']['tmp_name'])){

            // Добавляем файл в массив скрываемых данных
            array_push($SecretData, $_FILES['secretFile']);
        }

        // если имеется скрываемое сообщение
        if ($_POST['secretMessage']){

            // Добавляем сообщение в массив скрываемых данных
            array_push($SecretData, $_POST['secretMessage']);
        }

        // Обращение к классу стеганографии
        require_once('steganography.php');
        $stegano = new Stega();

        // если нет данных для сокрытия
        if (count($SecretData) < 1){

            // то дешифруем изображение
            $stegano->Get($Image, $_POST['key']);

        } else {

            // иначе - шифруем изображение
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
	<title>Стеганография</title>
  </head>

<body>
<div id="login-form">
<h1>Стеганография</h1>
<fieldset>
<form name="steggerForm" method="post" action="<?php echo($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">


  <p>Укажите изображение, в котором следует скрыть данные или из которого следует извлечь скрытые данные.</p>

  <blockquote>
    <strong>Загрузка исходного изображения:</strong><br />
    <input id="opImg1" type="file" name="imageFile" size="40" onchange="showImg(this,0);show(img.a);"/>
    <img id="inputimg" src="#" alt="Исходное изображение" />
  </blockquote>


  <p>Укажите ключ для сокрытия / извлечения данных.</p>

  <blockquote>
    <strong>Ключ для сокрытия / извлечения данных:</strong><br />
    <input type="text" name="key" size="40" />
  </blockquote>


  <p>Укажите данные, которые требуется скрыть в исходном изображении. Для получения сокрытых данных оставьте поля пустыми.</p>

  <blockquote>
    <strong>Загрузка скрываемого изображения:</strong><br />
    <input type="file" name="secretFile" size="40" onchange="showImg(this,1);show(img.b);"/>
    <img id="outputimg" src="#" alt="Скрываемое изображение" />
    <h2>Или</h2>
    <strong>Скрываемое сообщение:</strong><br />
    <textarea name="secretMessage" rows="8" cols="90"></textarea>
  </blockquote>

  <input type="submit" name="submit"  value="Выполнить" />
  <input type="reset" name="reset" onclick="hide(img.a);hide(img.b);" value="Сбросить данные" />

</form>
</fieldset>
</div>
</body>
</html>
