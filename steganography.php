<?php

 // Публичный ключ
 define('STEGGER_PUB_KEY', 'Where will the children play?');

 class Stega {

    var $Verbose = TRUE;
    var $CLI = FALSE;

    var $Image;
    var $BitStream;
    var $BitBoundry;

    // сокрытые данные
    var $RawData = array();

    
    function Stega(){

        set_time_limit(0);
        $this->SetEnvironment();
        $this->BitStream = new BitStream();
    }

    // Шифрование
    function Put($secretData, $imageFile, $key = '', $outputFile = ''){

        $StartTime = microtime(TRUE);
        $this->BitStream->FlushStream();
        $this->Info('Загрузка изображения.');

        $this->Image = new Image($imageFile);

        if ($this->Image->EOF()){

            $this->FatalError('Невозможно загрузить изображение!');

        } else {

            $this->Info('Загрузка изображения.');

            if (!$this->Input($secretData)){

                $this->FatalError('Невозможно загрузить предоставленные данные!');

            } else {

                $this->Info('Шифрование.');
                if (!$this->RawToString($key)){

                    $this->FatalError('Невозможно зашифровать полученные данные.');

                } else {

                    $this->Info('Шифрование.');
                    if (!$this->StringToStream()){

                        $this->FatalError('Невозможно зашифровать данные.');

                    } else {

                        $this->Info('Шифрование.');
                        if (!$this->StreamToPixels()){

                            $this->FatalError('Невозможно зашифровать изображение.');

                        } else {

                            $this->Info('Сохранение изображения.');
                            $this->Image->Output($outputFile);
                            $this->Success('Завершено за '.round(microtime(TRUE) - $StartTime).' секунд');
                        }
                    }
                }
            }
        }
    }

    // Дешифрование
    function Get($imageFile, $key = '', $outputPath = ''){

        $StartTime = microtime(TRUE);
        $this->BitStream->FlushStream();
        $this->Info('Загрузка изображения.');
        $this->Image = new Image($imageFile);
        if ($this->Image->EOF()){

            $this->FatalError('Невозможно загрузить изображение');

        } else {
            $this->Info('Чтение изображения.');
            $this->PixelsToStream();
            if ($this->BitStream->EOF()){

                $this->FatalError('Нет скрытых данных в изображении');

            } else {

                $this->Info('Дешифрование.');
                if (!$this->StreamToString()){

                    $this->FatalError('Невозможно дешифровать данные');
                } else {
                    $this->Info('Дешифрование.');

                    if (!$this->StringToRaw($key)){

                        $this->FatalError('Невозможно дешифровать данные');
                    } else {

                        if (!$this->Output($outputPath)){

                            $this->FatalError('Ошибка. Невозможно продолжить');
                        } else {

                            $this->Success('Завершено за '.round(microtime(TRUE) - $StartTime).' секунд');
                        }
                    }
                }
            }
        }
    }

    // Загрузка данных
    function Input($data){

        if (is_array($data) && !isset($data['tmp_name'])){

            foreach ($data as $Element){

                $this->Input($Element);
            }

        } else {

            $this->ReadToRaw($data);
        }

        if (is_array($this->RawData) && count($this->RawData > 0)){

            return TRUE;
        } else {
            return FALSE;
        }
    }

    // вывод данных
    function Output($path = ''){

        if (is_array($this->RawData) && count($this->RawData)){

            if (strlen($path)){

                if (!is_dir($path)){

                    $this->Error('Указанный путь не является директорией');
                    return FALSE;
                }

                if (!is_writable($path)){

                    $this->Error('В указанный путь невозможно произвести запись');
                    return FALSE;
                }

                while (count($this->RawData) > 0){

                    if (!$this->WriteFromRaw($path)){

                        $this->Error('Ошибка извлечения файлов');
                        return FALSE;
                    }

                }
                return TRUE;

            } else {

                if ($this->CommandLineInterface()){

                    $this->Error('Укажите выходной путь');
                    return FALSE;

                } else {

                    $Data = $this->WriteFromRaw('', TRUE);
                    switch ($Data['type']){

                        case 'message':

                            header('Content-type: text/plain');
                            header('Content-Disposition: attachment; filename=message.txt');
                            echo $Data['message'];
                            exit();
                            break;

                        case 'file':

                            header('Content-Disposition: attachment; filename='.$Data['filename']);
                            echo $Data['file'];
                            exit();
                            break;
                    }
                    return FALSE;
                }
            }

        } else {

            $this->Error('Нет сокрытых данных для извлечения');
            return FALSE;
        }
    }

    // Чтение изображения в сырой формат

    function ReadToRaw($data){

        switch ($this->GetArgumentType($data)){

            case 'message':
                if (strlen($data) > 0){

                    array_push($this->RawData, array('type' => 'message', 'message' => base64_encode(gzdeflate($data))));
                    return TRUE;
                }
                break;

            case 'uploaded':

                $Contents = file_get_contents($data['tmp_name']);
                if (strlen($Contents) > 0){

                    array_push($this->RawData, array('type' => 'file', 'file' => base64_encode(gzdeflate($Contents)), 'filename' => $data['name']));
                    return TRUE;
                }
                break;

            case 'glob':

                foreach (glob($data) as $File){

                    $Contents = file_get_contents($File);
                    if (strlen($Contents) > 0){

                        array_push($this->RawData, array('type' => 'file', 'file' => base64_encode(gzdeflate($Contents)), 'filename' => $File));
                    }
                }

                return TRUE;
                break;

            case 'file':
                $Contents = file_get_contents($data);
                if (strlen($Contents) > 0){

                    array_push($this->RawData, array('type' => 'file', 'file' => base64_encode(gzdeflate($Contents)), 'filename' => $data));
                    return TRUE;
                }
                break;
        }
        return FALSE;
    }

    // чтение изображения из сырого формата
    function WriteFromRaw($path = '', $return = FALSE){

        if (is_array($this->RawData) && count($this->RawData) > 0){

            $Data = array_pop($this->RawData);

            switch ($Data['type']){

                case 'message':

                    if ($return == FALSE){

                        $this->Info('Сообщение из изображения:');
                        $this->Info("\t".gzinflate(base64_decode($Data['message'])));
                        return TRUE;

                    } else {

                        $Data['message'] = gzinflate(base64_decode($Data['message']));
                        return $Data;
                    }

                    break;

                case 'file':

                    if ($return == FALSE){

                        if (!strlen($path)){

                            return FALSE;
                        } else {

                            $Info = pathinfo($Data['filename']);
                            $Path = pathinfo($path);
                            $Pointer = fopen($Path['dirname'].'/'.$Path['basename'].'/'.$Info['basename'], 'w+');

                            if (is_resource($Pointer)){

                                fwrite($Pointer, gzinflate(base64_decode($Data['file'])));

                                fclose($Pointer);
                                return TRUE;

                            } else {

                                return FALSE;
                            }

                        }

                    } else {

                        $Data['file'] = gzinflate(base64_decode($Data['file']));

                        return $Data;
                    }

                    break;
            }

        } else {

            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will encode and compress a raw data array                   |
      |                                                                  |
      | @return boolean                                                  |
      +------------------------------------------------------------------+
    */

    function RawToString($key = ''){

        // If we actually have a data array
        if (is_array($this->RawData) && count($this->RawData) > 0){

            // Serialize our data array
            $this->DataString = serialize($this->RawData);

            // Instantiate the Secrypt object
            $Secrypt = new Secrypt();

            // If we can encrypt the data
            if ($Secrypt->Encrypt($this->DataString, $key)){

                // Then update the data string
                $this->DataString = $Secrypt->Data;

                // We are done with the raw data and encryption class
                $this->RawData = array(); unset($Secrypt);

                // Loop untill we have a valid bit boundry
                while (strstr($this->DataString, $Boundry) || strlen($Boundry) <= 0){

                    // Generate a new 24 bit boundry
                    $Boundry = chr(rand(33, 127)).chr(rand(33, 127)).chr(rand(33, 127));
                }

                // Reset the bit boundry
                $this->BitBoundry = '';

                // Loop through each character in the new boundry
                for ($i = 0; $i < 3; $i++){

                    // Add this to the bit boundry
                    $this->BitBoundry .= str_pad(decbin(ord($Boundry[$i])), 8, '0', STR_PAD_LEFT);
                }

                // Success
                return TRUE;

            } else {

                // We have no data string
                $this->DataString = '';

                // Failure
                return FALSE;
            }

        } else {

            // Nothing to do
            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will decompress and decode a string into a raw data array   |
      |                                                                  |
      | @return boolean                                                  |
      +------------------------------------------------------------------+
    */

    function StringToRaw($key = ''){

        // If we actually have an encoded data string
        if (is_string($this->DataString) && strlen($this->DataString) > 0){

            // Create a new instance of the Secrypt object
            $Secrypt = new Secrypt();

            // If we can decrypt the string
            if ($Secrypt->Decrypt($this->DataString, $key)){

                // Then unserialize the data array
                $this->RawData = unserialize($Secrypt->Data);

                // If we have a raw data array
                if (is_array($this->RawData) && count($this->RawData) > 0){

                    // Then we did it
                    return TRUE;

                } else {

                    // Failure
                    return FALSE;
                }

            } else {

                // Failure
                return FALSE;
            }

        } else {

            // Nothing to do
            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will turn a bit stream into a data string                   |
      |                                                                  |
      | @return boolean                                                  |
      +------------------------------------------------------------------+
    */

    function StreamToString(){

        // Make sure we have an empty data string
        $this->DataString = '';

        // Loop untill the end of the bit stream
        while (!$this->BitStream->EOF()){

            // Add the character representation for the next 8 bits to our data string
            $this->DataString .= chr(bindec($this->BitStream->Read(8)));
        }

        // If we have a data string
        if (strlen($this->DataString) > 0){

            // Trim any spare spaces off the string
            $this->DataString = trim($this->DataString, ' ');

            // Success
            return TRUE;

        } else {

            // Summn went wrong
            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will turn an encoded data string into a bit sequence        |
      |                                                                  |
      | @return boolean                                                  |
      +------------------------------------------------------------------+
    */

    function StringToStream(){

        // Flush the bit stream
        $this->BitStream->FlushStream();

        // If we have a data string that will fit in the image
        if ((strlen($this->DataString) * 8) < (($this->Image->CountPixels() - 6) * 3)){

            // While the length of the string is not cleanly divisible by 3
            while (strlen($this->DataString) % 3 > 0){

                // Add a white space character to the data string
                $this->DataString .= ' ';
            }

            // While we still have a data string
            while (strlen($this->DataString) > 0){

                // Write the next chunk of characters to the bit stream
                $this->BitStream->Write(substr($this->DataString, 0, 1));

                // Remove the first character from the data string
                $this->DataString = substr($this->DataString, 1);
            }

            // Success
            return TRUE;

        } else {

            // Work out how many bytes this image can hold
            $Capacity = round(($this->Image->CountPixels() * 3) / 8);

            // If we have less than a kilobyte
            if ($Capacity < 1024){

                // Make the capacity human readable
                $Capacity = $Capacity.' bytes';

            // If the capacity is smaller than a megabyte
            } elseif ($Capacity < 1048576){

                // Make the capacity human readable
                $Capacity = round($Capacity / 1024, 2).' KB';

            // The capacity is 1 megabyte or over
            } else {

                // Make the capacity human readable
                $Capacity = round(($Capacity / 1024) / 1024, 2).' MB';
            }

            // Tell the user why the problem occurred
            $this->Error('That image is not large enough to store that much data');

            // Now go over the top and tell the user how they can fix it
            $this->Error('The image you supplied can only hold '.$Capacity.' of data');

            // Failure
            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will read pixels to obtain a bit stream                     |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function PixelsToStream(){

        // Make a new bit stream for the image
        $BitStream = new BitStream($this->Image->GetBoundry());

        // Move to the start pixel
        $this->Image->StartPixel();

        // While we have bits and pixels
        while (!$this->Image->EOF() && !$BitStream->EOF()){

            // Get the current pixels RGB value
            $Pixel = $this->Image->GetPixel();

            // Write the pixel data to the bit stream
            $BitStream->Write($Pixel);

            // Move to the next pixel
            $this->Image->NextPixel();
        }

        // If we got to the end of the image
        if ($this->Image->EOF()){

            // Then we never found our secret data
            $BitStream->Stream = '';
        }

        // Overwrite the main bit stream with our new one
        $this->BitStream = $BitStream;
    }

    /*
      +------------------------------------------------------------------+
      | This will write a bit stream to pixels                           |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function StreamToPixels(){

        // Move to the start pixel
        $this->Image->StartPixel();

        // While we have bits and pixels
        while (!$this->Image->EOF() && !$this->BitStream->EOF()){

            // Read the next 3 bits from the bit stream
            $Bits = $this->BitStream->Read(3);

            // Write those 3 bits to the current pixel
            $this->Image->SetPixel($Bits);

            // Move to the next pixel
            $this->Image->NextPixel();
        }

        // Set the end bit boundry
        $this->Image->SetBoundry($this->BitBoundry);

        // Move to the first pixel
        $this->Image->FirstPixel();

        // Set the first bit boundry
        $this->Image->SetBoundry($this->BitBoundry);

        // If we got here we probably succeeded
        return TRUE;
    }

    // Enviromental Methods

    /*
      +------------------------------------------------------------------+
      | This will set properties relating to our run time environment    |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function SetEnvironment(){

        // If we have a REQUEST_METHOD
        if ($_SERVER['REQUEST_METHOD']){

            // Then we are probably being called from the web
            $this->CLI = FALSE;

            // Turn verbose output off
            $this->Verbose = FALSE;

        } else {

            // We are being run as a command line (or possibly compiled) app
            $this->CLI = TRUE;

            // Turn verbose output on
            $this->Verbose = TRUE;

            // Make sure we have implicit flush set to on
            ob_implicit_flush(1);
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will determine if we are using a command line interface     |
      |                                                                  |
      | @return boolean                                                  |
      +------------------------------------------------------------------+
    */

    function CommandLineInterface(){

        // If the command line interface flag is set
        if ($this->CLI){

            // Then we are probably using a command line interface
            return TRUE;

        } else {

            // Not a command line interface
            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will attempt to figure out what an argument represents      |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function GetArgumentType($argument){

        // If this is looks like an uploaded file
        if (is_array($argument) && isset($argument['tmp_name'])){

            // Then it probably is one
            return 'uploaded';

        // If this looks like a local file
        } elseif (file_exists($argument)){

            // Handle as a file
            return 'file';

        // If this looks like an external resource (TODO: Do this properly)
        } elseif (strstr($argument, '://')){

            // Handle as a file
            return 'file';

        // If the argument contains an asterix (TODO: Check the validity of the path)
        } elseif (strstr($argument, '*') && ($argument[0] == '.' || $argument[0] == '/')){

            // Then I'm guessing it is a glob style string
            return 'glob';

        // Everything else
        } else {

            // Treat it as a normal message
            return 'message';
        }
    }

    // Message Methods

    /*
      +------------------------------------------------------------------+
      | Print out an error message to the user and exit                  |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function FatalError($msg){

        // First we show the error message to the user
        $this->Error('Fatal Error: '.$msg);

        // Now we exit
        exit(-1);
    }

    /*
      +------------------------------------------------------------------+
      | Print out an error message to the user                           |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Error($msg){

        // If we are running as a command line application
        if ($this->CommandLineInterface()){

            // Just show the message a little formatted for the command line
            echo '[-] '.$msg.".\n";

        } else {

            // Show the error formatted for the web
            echo '<strong>Error:</strong> '.htmlspecialchars($msg).'<br />';
        }
    }

    /*
      +------------------------------------------------------------------+
      | Print out a success message to the user                          |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Success($msg){

        // If we are in verbose mode
        if ($this->Verbose){

            // If we are running as a command line application
            if ($this->CommandLineInterface()){

                // Just show the message a little formatted for the command line
                echo '[+] '.$msg.".\n";

            } else {

                // Show the message formatted for the web
                echo '<strong>Success:</strong> '.htmlspecialchars($msg).'<br />';
            }
        }
    }

    /*
      +------------------------------------------------------------------+
      | Print out an informative message to the user                     |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Info($msg){

        // If we are in verbose mode
        if ($this->Verbose){

            // If we are running as a command line application
            if ($this->CommandLineInterface()){

                // Just show the message a little formatted for the command line
                echo '[i] '.$msg.".\n";

            } else {

                // Show the message formatted for the web
                echo '<strong>Info:</strong> '.htmlspecialchars($msg).'<br />';
            }
        }
    }
}


// работа с битовым представлением
class BitStream {
  
    var $Stream = '';
    var $Boundry = '';
    var $Fresh = TRUE;

    // конструктор
    function BitStream($bitBoundry = ''){

        // If we have a bit boundry, use it
        if ($bitBoundry) $this->Boundry = $bitBoundry;
    }


    // чтение блока с числом бит = 8
    function Read($number = 8){

        // If we are not on the end of the bit stream
        if (strlen($this->Stream) > 0){

            // Grab the chunk of bits from the bit stream
            $return = substr($this->Stream, 0, $number);

            // Remove the chunk of bits from the bit stream
            $this->Stream = substr($this->Stream, $number);

            // Return the chunk of bits
            return $return;

        } else {

            // Nothing to return
            return FALSE;
        }
    }

    // запись в битах
    function Write($data, $binary = FALSE){

        if ($binary){
            $this->Stream .= $data;
        } else {

            switch (gettype($data)){
                case 'string':

                    // Loop through each character in the string
                    for ($i = 0; $i < strlen($data); $i++){

                        // Add the bit representation for this character to the bit stream
                        $this->Stream .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
                    }
                    break;

                case 'integer':

                    // Add the bit representation for this character to the bit stream
                    $this->Stream .= str_pad(decbin($data), 8, '0', STR_PAD_LEFT);
                    break;
                
                case 'boolean':

                    // If the boolean is true
                    if ($data == TRUE){

                        // Then add a 1 to the bit stream
                        $this->Stream .= '1';

                    } else {

                        // Add a 0 to the bit stream
                        $this->Stream .= '0';
                    }
                    break;

                case 'array':

                    // Loop through each primary colour in this RGB array
                    foreach ($data as $PrimaryColour){

                        // Add the bit value of this integer
                        $this->Stream .= (int) $PrimaryColour % 2;
                    }
                    break;
            }
        }
    }

    // обработка события конца файла
    function EOF(){

        // If we have not ran any methods yet
        if ($this->Fresh){

            // We are no longer fresh
            $this->Fresh = FALSE;

            // But we are not at the end of the file either
            return FALSE;
        }

        // If we have a bit of stream left
        if (strlen($this->Stream) > 0){

            // If we have a bit boundry
            if (strlen($this->Boundry)){

                // If we have found our bit boundry
                if (substr($this->Stream, -24) == $this->Boundry){

                    // Then we remove the boundry from the bit stream
                    $this->Stream = substr($this->Stream, 0, -24);

                    // We hit the end of the stream
                    return TRUE;

                } else {

                    // We are not at the end of the stream
                    return FALSE;
                }

            } else {

                // Not at the end
                return FALSE;
            }

        } else {

            // Yeah, we're at the end
            return TRUE;
        }
    }

    // очистка памяти
    function FlushStream(){
        $this->Stream = '';
    }
}


class Image {

    var $Canvas;
    var $Name = '';
    var $Width = 0;
    var $Height = 0;
    var $PixelPointer = array('x' => 0, 'y' => 0);
    var $EOF = TRUE;

    function Image($image){

        if ($image){
            $this->Load($image);

        } else {
            return FALSE;

        }
    }


    function Load($image){

        // If the image looks like it was uploaded
        if (is_array($image) && isset($image['tmp_name'])){

            // Set the default output image name using the original file name
            $this->SetName($image['name']);

            // Create a canvas for this image
            $this->CreateCanvas($image['tmp_name'], $image['name']);

        } else {

            // Set the default output image name using the path or url to the image
            $this->SetName($image);

            // Create a canvas for this image
            $this->CreateCanvas($image);
        }

        // If we actually have a canvas at this point
        if (is_resource($this->Canvas)){

            // We are not at the end of the file
            $this->EOF = FALSE;

            // Clear the canvas
            $this->ClearCanvas();

            // Move to the first pixel
            $this->FirstPixel();

            // Success
            return TRUE;

        } else {

            // Failure
            return FALSE;
        }
    }

    function CreateCanvas($image, $name = ''){

        // If we don't have the original name, then the image contains the name
        if (!$name) $name = $image;


        // Handle each image type differently
        switch ($this->GetImageType($name)){

            // JPG
            case 'JPG':

                // Create a canvas from the JPG
                $this->Canvas = imagecreatefromjpeg($image); break;

            // PNG
            case 'PNG':

                // Create a canvas from the PNG
                $this->Canvas = imagecreatefrompng($image); break;

            // GIF
            case 'GIF':

                // Create a canvas from the GIF
                $this->Canvas = imagecreatefromgif($image); break;

            // Not Supported
            default:

                // Nothing else we can do
                return;
        }

        // If we have an image canvas
        if (is_resource($this->Canvas)){

            // Get the images width
            $this->Width = imagesx($this->Canvas);

            // Get the images height
            $this->Height = imagesy($this->Canvas);

            // We are not at the end of the file
            $this->EOF = FALSE;

        } else {

            // We are at the end of the file
            $this->EOF = TRUE;
        }
    }

    function ClearCanvas(){

        // Create a new true colour canvas based on our images dimensions
        $Canvas = imagecreatetruecolor($this->Width, $this->Height);

        // If we have a canvas and an image
        if (is_resource($Canvas) && is_resource($this->Canvas)){

            // Make sure alpha blending is off
            imagealphablending($Canvas, FALSE);

            // Copy the contents of the original canvas to the new one
            imagecopy($Canvas, $this->Canvas, 0, 0, 0, 0, $this->Width, $this->Height);

            // Overwrite the old canvas with the newly prepaired one
            $this->Canvas = $Canvas;
        }
    }

    // сохранение полученного файла
    function Output($outputFile = ''){

        // If we have an output file specified
        if ($outputFile){

            // Set the output image name
            $this->SetName($outputFile);
        }

        // If we are serving to a browser
        if ($_SERVER['REQUEST_METHOD']){

            // Make sure the browser knows this is a PNG image
            header('Content-type: image/png');

            // Try get the browser to download the image as our name
            header('Content-Disposition: attachment; filename='.$this->Name);

            // Output the image to the browser
            imagepng($this->Canvas);

        } else {

            // Get some information about the output path
            $Info = pathinfo($outputFile);

            // Write the image to the file name specified
            imagepng($this->Canvas, $Info['dirname'].'/'.$this->Name);
        }

        // Destroy the canvas
        imagedestroy($this->Canvas);
    }

    function GetImageType($image){

        // Get some information about the path, URL or image name
        $Info = pathinfo($image);

        // Handle each extension type differently
        switch (strtolower($Info['extension'])){

            // JPEG
            case 'jpg':
            case 'jpeg':

                // We are dealing with a JPG
                return 'JPG';

            // GIF
            case 'gif':

                // We are dealing with a GIF
                return 'GIF';

            // PNG
            case 'png':

                // We are dealing with a PNG
                return 'PNG';

            // *
            default:

                // No idea what the hell this is
                return '';
        }
    }

    function GetPixel(){

        // Get the (32 bit) RGB value from the current image
        $RGB = imagecolorat($this->Canvas, $this->PixelPointer['x'], $this->PixelPointer['y']);

        // Obtain the individual values for each primary colour
        $R = ($RGB >> 16) & 0xFF;
        $G = ($RGB >>  8) & 0xFF;
        $B = ($RGB >>  0) & 0xFF;

        // Return the individual RGB values in an array
        return array($R, $G, $B);
    }

    function SetPixel($rgb){

        // If this looks like a couple of bits
        if (is_string($rgb) && strlen($rgb) == 3){

            // Get the RGB value of the current pixel
            $RGB = $this->GetPixel();

            // Loop through each primary colour in this pixel
            for ($i = 0; $i < 3; $i++){

                // If the current char of our binary string is a 1
                if ($rgb[$i] == '1'){

                    // If the current colour value isn't odd
                    if ($RGB[$i] % 2 != 1){

                        // Increment it
                        $RGB[$i]++;
                    }

                } else {

                    // If the current colour valie isn't even
                    if ($RGB[$i] % 2 != 0){

                        // Decrease it
                        $RGB[$i]--;
                    }
                }
            }

            // Call ourselves again with the RGB array
            $this->SetPixel($RGB);

            // And thats all there is to it
            return TRUE;
        }

        // If we have a full RGB array
        if (is_array($rgb) && count($rgb) == 3){

            // Allocate the colour to the image
            $Colour = imagecolorallocate($this->Canvas, $rgb[0], $rgb[1], $rgb[2]);

            // Assign the colour to the current pixel
            imagesetpixel($this->Canvas, $this->PixelPointer['x'], $this->PixelPointer['y'], $Colour);

            // We're done here
            return TRUE;
        }

        // If we get here we failed
        return FALSE;
    }

    function CountPixels(){

        // Return the width multiplied by the height
        return round($this->Height * $this->Width);
    }

    function FirstPixel(){

        // Reset the pixel pointer
        $this->PixelPointer['x'] = ($this->Width - 1);
        $this->PixelPointer['y'] = ($this->Height - 1);
    }

    function StartPixel(){

        // The data starts 24 bits in (3 bytes)
        $this->PixelPointer['x'] = ($this->Width - 1) - 8;
        $this->PixelPointer['y'] = ($this->Height - 1);
    }

    function NextPixel(){

        // If we are on the last column
        if ($this->PixelPointer['x'] <= 0){

            // If we are on the last row of pixels
            if ($this->PixelPointer['y'] <= 0){

                // We are at the end of the file
                $this->EOF = TRUE;

                // So we can't go any further
                return $this->EOF;

            } else {

                // Move to the next row
                $this->PixelPointer['y']--;

                // Move to the first column of the new row
                $this->PixelPointer['x'] = ($this->Width - 1);
            }

        } else {

            // Move to the next column
            $this->PixelPointer['x']--;
        }
    }

    function PrevPixel(){

        // If we are on the first column
        if ($this->PixelPointer['x'] >= ($this->Width - 1)){

            // If we are on the first row of pixels
            if ($this->PixelPointer['y'] >= ($this->Height - 1)){

                // Then we can't go back any further
                return;

            } else {

                // Move to the previous row
                $this->PixelPointer['y']++;

                // Move to the last column of the current row
                $this->PixelPointer['x'] = 0;
            }

        } else {

            // Move to the previous column
            $this->PixelPointer['x']++;
        }
    }

    // граничный шаблон
    function GetBoundry(){

        // Backup the current pixel pointer
        $PixelPointer = $this->PixelPointer;

        // Move to the first pixel
        $this->FirstPixel();

        // Go through the first 8 pixels (24 bits)
        for ($i = 0; $i < 8; $i++){

            // Get this pixels RGB value
            $Pixel = $this->GetPixel();

            // Loop through each primary colour in this
            foreach ($Pixel as $PrimaryColour){

                // Add the bit value of this number to the final string
                $return .= (int) $PrimaryColour % 2;
            }

            // Move to the next pixel
            $this->NextPixel();
        }

        // Move the pixel pointer back where it was
        $this->PixelPointer = $PixelPointer;

        // Return the final value
        return $return;
    }

    // установка граничных битов

    function SetBoundry($boundry){

        // If we have at least 3 bytes of data (24 bits)
        if (strlen($boundry) >= 24){

            // Initiate the bit counter
            $b = 0;

            // Loop through 8 pixels from our current position
            for ($i = 0; $i < 8; $i++){

                // Get the RGB value of the current value
                $RGB = $this->GetPixel();

                // Loop through each primary colour in the RGB array
                for ($j = 0; $j < 3; $j++){

                    // Get the next bit from the binary string
                    $Bit = $boundry[$b];

                    // Figure out which kind of bit this is
                    switch ($Bit){

                        // 1
                        case '1':

                            // If this colour is not an odd number
                            if ($RGB[$j] % 2 != 1){

                                // Then increase it
                                $RGB[$j]++;
                            }

                            break;

                        // 0
                        case '0':
                            // If this colour is not represented by an even number
                            if ($RGB[$j] % 2 != 0){

                                // Decrease it
                                $RGB[$j]--;
                            }

                            break;
                    }

                    // Increment the bit counter
                    $b++;
                }

                // Set the pixel to our new RGB array
                $this->SetPixel($RGB);

                // Move to the next pixel
                $this->NextPixel();
            }
        }
    }

    // имя результирующего изображения
    function SetName($image){

        // Get some information about the image filename, path or url
        $Info = pathinfo($image);

        // If we have an extension
        if (strlen($Info['extension']) > 0){

            // If the extension is not a PNG
            if (strtolower($Info['extension']) != 'png'){

                // Change the extension to a PNG
                $Info['basename'] = str_replace('.'.$Info['extension'], '.png', $Info['basename']);
            }

        } else {

            // If we have a basename
            if (strlen($Info['basename']) > 0){

                // Then append our extension to it
                $Info['basename'] .= '.png';

            } else {

                // This guy isn't giving us much choice
                $Info['basename'] = 'encoded.png';
            }
        }

        // Set the image name to the base name
        $this->Name = $Info['basename'];
    }

    function EOF(){
        return $this->EOF;
    }
}

class Secrypt {



    // Public Properties

    /**
    * array
    *
    * This is the array of keys we use to encrypt or decrypt data
    */
    var $Keys = array('public' => '', 'private' => '', 'xfactor' => '', 'yfactor' => '', 'zfactor' => '');

    /**
    * string
    *
    * This holds the data after it has been successfully encrypted or decrypted
    */
    var $Data = '';

    /**
    * boolean
    *
    * Determines if we can zip the contents or not
    */
    var $Zip = TRUE;

    /**
    * array
    *
    * All the error messages in an array
    */
    var $Errors = array();

    // Private Properties

    /**
    * array
    *
    * An array that holds each of our base64 compatible charsets
    */
    var $Locks = array();

    /*-------------------*/
    /* F U N C T I O N S */
    /*-------------------*/

    /*
      +------------------------------------------------------------------+
      | Constructor                                                      |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Secrypt(){

        // If we  can't zip
        if (!function_exists('gzdeflate')){

            // Then we don't zip
            $this->Zip = FALSE;
        }

        // Run forever
        set_time_limit(0);

        // Reset the lock
        $this->ResetLock();
    }

    // Public API Methods

    /*
      +------------------------------------------------------------------+
      | This will encrypt $data against the $privateKey and $publicKey   |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function Encrypt($data, $privateKey = '', $publicKey = STEGGER_PUB_KEY){

        // Insert the keys
        $this->InsertKeys($privateKey, $publicKey);

        // Turn all the keys
        $this->TurnKey();

        // Locketh the data
        return $this->Lock($data);
    }

    /*
      +------------------------------------------------------------------+
      | This will decrypt $data against the $privateKey and $publicKey   |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function Decrypt($data, $privateKey = '', $publicKey = STEGGER_PUB_KEY){

        // Insert the keys
        $this->InsertKeys($privateKey, $publicKey);

        // Turn all the keys
        $this->TurnKey();

        // Unlock the data and return the results
        return $this->Unlock($data);
    }

    // Key Methods

    /*
      +------------------------------------------------------------------+
      | This gets a reference to the key that fits in $lockType          |
      |                                                                  |
      | @return reference                                                |
      +------------------------------------------------------------------+
    */

    function &GetKey($lockType){

        // Return the appropriate key
        return $this->Keys[$lockType];
    }

    /*
      +------------------------------------------------------------------+
      | This will set all the keys in the key array at once              |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function InsertKeys($private, $public){

        // Remove all keys
        $this->RemoveKey();

        // Reset all the locks
        $this->ResetLock();

        // Loop through all the keys
        foreach ($this->Keys as $KeyType => $Key){

            // If this is a factor key
            if (strstr($KeyType, 'factor')){

                // Set the key to the md5 hash of the keys array thus far
                $Key = md5(serialize($this->Keys));

            } else {

                // Set the key to the key we were passed
                $Key = $$KeyType;
            }

            // Insert the key we have in the end
            $this->InsertKey($Key, $KeyType);
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will set a $key for $lockType                               |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function InsertKey($key, $lockType){

        // If we have a key
        if (strlen($key) > 0){

            // Set the key
            $this->Keys[$lockType] = $key;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will turn a lock based on a keys contents                   |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function TurnKey($lockType = ''){

        // If we don't have a lock type
        if (!$lockType){

            // Loop through all the locks
            foreach ($this->Locks as $LockType => $Lock){

                // Call ourselves with this lock type
                $this->TurnKey($LockType);
            }

            // Don't pass this bit
            return;
        }

        // Get a reference to the desired key
        $Key =& $this->GetKey($lockType);

        // Loop through each character of the key
        for ($i = 0; $i < strlen($Key); $i++){

            // Work out how many steps to turn the lock
            $Steps = ord($Key[$i]) / ($i + 1);

            // If the decimal value of the current character is odd
            if (ord($Key[$i]) % 2 != 0){

                // Turn the lock left
                $this->TurnLock($lockType, $Steps, 'left');

            } else {

                // Turn the lock right
                $this->TurnLock($lockType, $Steps, 'right');
            }
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will clear a keys contents, all keys if no $lockType is set |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function RemoveKey($lockType = ''){

        // Loop through each of the keys
        foreach($this->Keys as $KeyName => $Key){

            // If this is our desired key or we don't have a desired key
            if ($lockType == $KeyName || strlen($lockType) == 0){

                // Reset this key
                $this->Keys[$KeyName] = '';
            }
        }
    }

    // Lock Methods

    /*
      +------------------------------------------------------------------+
      | This gets a reference to the character set a key manipulates     |
      |                                                                  |
      | @return reference                                                |
      +------------------------------------------------------------------+
    */

    function &GetLock($lockType){

        // Return a reference to the lock
        return $this->Locks[$lockType];
    }

    /*
      +------------------------------------------------------------------+
      | This will lock the data according to the current character index |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function Lock($data){

        // Reset the data
        $this->Data = '';

        // If we are supposed to be zipping
        if ($this->Zip == TRUE){

            // If we can't compress the data
            if (FALSE === ($data = @gzdeflate($data))){

                // Add the error incase the user wants to know why we failed
                $this->Error('There was a problem compressing the data');

                // Huston, we have a problem
                return FALSE;
            }
        }

        // If we can compress the character
        if (FALSE !== ($data = base64_encode($data))){

            // Loop through each character in the data
            for ($i = 0; $i < strlen($data); $i++){

                // Convert this character to its encrypted equivilent
                $data[$i] = $this->GetChar($data[$i], TRUE);
            }

            // Looks like we have ourselves some data
            $this->Data = $data;

            // And thats all folks
            return $this->Data;

        } else {

            // Add the error to let the user know why we failed
            $this->Error('There was a problem encoding the data');

            // Failure
            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This unlocks the data according to the current character index   |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function Unlock($data){

        // Reset the data
        $this->Data = '';

        // Loop through each character in the data
        for ($i = 0; $i < strlen($data); $i++){

            // Convert this character to its decrypted equivilent
            $data[$i] = $this->GetChar($data[$i], FALSE);
        }

        // If we can base64 decode the data
        if (FALSE !== ($data = base64_decode($data))){

            // If we can decompress data
            if (FALSE !== ($data = @gzinflate($data))){

                // Looks like we have ourselves some data
                $this->Data = $data;

                // Thats all folks
                return $this->Data;

            } else {

                // Tell the user why we failed
                $this->Error('There was a problem decompressing the data');

                // Failure
                return FALSE;
            }

        } else {

            // Add the error ro the error stack
            $this->Error('There was a problem decoding the data');

            // Failure
            return FALSE;
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will turn a lock (character set) $steps steps in $direction |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function TurnLock($lockType, $steps = 5, $direction = 'right'){

        // Loop through the required number of steps
        for ($i = 0; $i < $steps; $i++){

            // Get a reference to the lock
            $Lock =& $this->GetLock($lockType);

            // If we are not going right, reverse the string
            if ($direction != 'right') $Lock = strrev($Lock);

            // Make a copy of the counter
            $c = $i;

            // If we are rotating a character passed the end of the character set
            if ($c >= strlen($Lock)){

                // While we still have too little characters to split
                while ($c >= strlen($Lock)){

                    // Minus the lock length from the counter
                    $c = $c - strlen($Lock);
                }
            }

            // Isolate the first character in the charset
            $Char = substr($Lock, 0, 1);
            $Lock = substr($Lock, 1);

            // If our split point exists
            if (strlen($Lock[$c]) > 0){

                // Split the string at the desired position
                $Chunks = explode($Lock[$c], $Lock);

                // If we have some chunks
                if (is_array($Chunks)){

                    // Then piece together the string
                    $Lock = $Chunks[0].$Lock[$c].$Char.$Chunks[1];
                }

            } else {

                // Put the lock back to the way it was
                $Lock = $Char.$Lock;
            }

            // If we are not going right, reverse the string back
            if ($direction != 'right') $Lock = strrev($Lock);
        }
    }

    /*
      +------------------------------------------------------------------+
      | This will generate the original charset and character index      |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function ResetLock($lockType = ''){

        // Get the base 64 compatible character set
        $CharSet = $this->GetCharSet();

        // Loop through the keys we have
        foreach ($this->Keys as $LockType => $Key){

            // If we were supplied a lock type to reset
            if ($lockType){

                // If this is our lock
                if ($LockType == $lockType){

                    // Then reset the lock
                    $this->Locks[$LockType] = $CharSet;

                    // And we're done
                    return;
                }

            } else {

                // Reset this lock
                $this->Locks[$LockType] = $CharSet;
            }
        }
    }

    // Character Set Methods

    /*
      +------------------------------------------------------------------+
      | This will lookup the encrypted/decrypted version of a character  |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function GetChar($char, $encrypt = FALSE){

        // If we are not encrypting, flip the locks
        if (!$encrypt) $this->Locks = array_reverse($this->Locks);

        // Initate the lock counter
        $i = 0;

        // Loop through each lock
        foreach ($this->Locks as $LockType => $Lock){

            // If this is the first lock, set the initial position
            if ($i == 0){

                // Get the initial position
                $Position = strpos($Lock, $char);
            }

            // If the lock counter is odd, or this is the final iteration
            if ($i % 2 > 0){

                // If we are encrypting
                if ($encrypt){

                    // Swap position
                    $Position = strpos($Lock, $char);

                } else {

                    // Swap character
                    $char = $Lock[$Position];
                }

            } else {

                // If we are encrypting
                if ($encrypt){

                    // Swap character
                    $char = $Lock[$Position];

                } else {

                    // Swap position
                    $Position = strpos($Lock, $char);
                }
            }

            // Increment the lock counter
            $i++;
        }

        // If we are not encrypting, flip the locks
        if (!$encrypt) $this->Locks = array_reverse($this->Locks);

        // Return the character
        return $char;
    }

    /*
      +------------------------------------------------------------------+
      | This will generate and return a base 64 compatible charset       |
      |                                                                  |
      | @return string                                                   |
      +------------------------------------------------------------------+
    */

    function GetCharSet(){

        // These are forbidden characters that fall in the range of chars we iterate
        $ForbiddenChars = array_merge(range(44, 46), range(58, 64), range(91, 96));

        // Loop through the base64 compatible range of characters
        for ($i = 43; $i < 123; $i++){

            // If this is not a forbidden character
            if (!in_array($i, $ForbiddenChars)){

                // Then add this to the final character set
                $return .= chr($i);
            }
        }

        // Return the final character set
        return $return;
    }

    // Error Reporting Methods

    /*
      +------------------------------------------------------------------+
      | This will add an error message to the error message stack        |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Error($msg){

        // Add the error to the stack
        $this->Errors[] = $msg;
    }

    /*
      +------------------------------------------------------------------+
      | This will display the error messages specific to the current env |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function ShowErrors($returnVal = FALSE){

        // Loop through all the errors
        foreach ($this->Errors as $Error){

            // If we are being called from the web
            if (strlen($_SERVER['REQUEST_METHOD']) > 0){

                // Format the errors for the web
                $return .= '<strong>Error:</strong> '.$Error.'<br />';

            } else {

                // Format the error message for the command line
                $return .= '[-] '.$Error."\n";
            }
        }

        // Now that we are showing the errors, we can clear them too
        $this->Errors = array();

        // If we are supposed to the return the errors
        if ($returnVal){

            // Then return them we shall
            return $return;

        } else {

            // Output the errors directly
            echo $return;
        }
    }

    // Debug Methods

    /*
      +------------------------------------------------------------------+
      | This will output a message instantly for debugging purposes      |
      |                                                                  |
      | @return void                                                     |
      +------------------------------------------------------------------+
    */

    function Debug($msg){

        // Turn implicit output buffering on incase it is off
        ob_implicit_flush(1);

        // If we are being called from the web
        if (strlen($_SERVER['REQUEST_METHOD'])){

            // Then format the message for the web
            $msg = '<strong>Debug:</strong> '.$msg.'<br />';

        } else {

            // Format the message for a CLI
            $msg = '[i] '.$msg."\n";
        }

        // Output the message
        echo $msg;
    }
 }

?>