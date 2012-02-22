<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
php5 class
Used for generating waveform images for mp3's
Basically, just provide a path to an mp3, setup some other variables 
like where to save the image, the width/height of the resulting image and the image colors


Usage:

Load the library:
$this->load->library('waveform_generation');

$files = get_dir_file_info('music', TRUE);
$save_path = FCPATH."assets/waveforms/";
$width = 234;
$height = 35;
$foreground = "#7285AB";
$background = "";

foreach ($files as $file){
	$saved_image = $this->waveform_generation->process_track(FCPATH."music/".$file['name'], $save_path, $width, $height, $foreground, $background);
	// echo $saved_image;
}

*/

ini_set("max_execution_time", "30000");

class Waveform_generation
{
	// how much detail we want. Larger number means less detail
	// (basically, how many bytes/frames to skip processing)
	// the lower the number means longer processing time
	protected $detail = 5;
	
	public function __construct()
    {
    	
    }

    public function __destruct()
    {
    	
    }

  	public function process_track($file_path, $save_path, $width, $height, $foreground, $background, $is_stereo=false, $draw_flat=true){
		return $this->process_mp3($file_path, $save_path, $width, $height, $foreground, $background, $is_stereo, $draw_flat);
	}
  
	 /**
	 * GENERAL FUNCTIONS
	 */
	public function findValues($byte1, $byte2){
	  $byte1 = hexdec(bin2hex($byte1));                        
	  $byte2 = hexdec(bin2hex($byte2));                        
	  return ($byte1 + ($byte2*256));
	}

	/**
	 * Great function slightly modified as posted by Minux at
	 * http://forums.clantemplates.com/showthread.php?t=133805
	 */
	public function html2rgb($input) {
	  $input=($input[0]=="#")?substr($input, 1,6):substr($input, 0,6);
	  return array(
	   hexdec(substr($input, 0, 2)),
	   hexdec(substr($input, 2, 2)),
	   hexdec(substr($input, 4, 2))
	  );
	}
	
	public function save_file($image_data, $image_name, $image_path){
		if (!write_file($image_path.$image_name, $image_data)){
		     echo 'Unable to create the image';
		} else {
		     return true;
		}
	}

	public function process_mp3($file, $save_path, $width, $height, $foreground, $background, $is_stereo, $draw_flat){
		// temporary file name
		$tmpname = $save_path.substr(md5(time()), 0, 10);

		// copy from temp upload directory to current
		copy($file, "{$tmpname}_o.mp3");
		
		// array of wavs that need to be processed
		$wavs_to_process = array();

		/**
		 * convert mp3 to wav using lame decoder
		 * First, resample the original mp3 using as mono (-m m), 16 bit (-b 16), and 8 KHz (--resample 8)
		 * Secondly, convert that resampled mp3 into a wav
		 * We don't necessarily need high quality audio to produce a waveform, doing this process reduces the WAV
		 * to it's simplest form and makes processing significantly faster
		 */
		if ($is_stereo) {
		// scale right channel down (a scale of 0 does not work)
		  exec("lame {$tmpname}_o.mp3 --scale-r 0.1 -m m -S -f -b 16 --resample 8 {$tmpname}.mp3 && lame -S --decode {$tmpname}.mp3 {$tmpname}_l.wav");
		// same as above, left channel
		  exec("lame {$tmpname}_o.mp3 --scale-l 0.1 -m m -S -f -b 16 --resample 8 {$tmpname}.mp3 && lame -S --decode {$tmpname}.mp3 {$tmpname}_r.wav");
		  $wavs_to_process[] = "{$tmpname}_l.wav";
		  $wavs_to_process[] = "{$tmpname}_r.wav";
		} else {
		  exec("lame {$tmpname}_o.mp3 -m m -S -f -b 16 --resample 8 {$tmpname}.mp3 && lame -S --decode {$tmpname}.mp3 {$tmpname}.wav");
		  $wavs_to_process[] = "{$tmpname}.wav";
		}

		// delete temporary files
		//echo $save_path."{$tmpname}_o.mp3";
		unlink("{$tmpname}_o.mp3");
		unlink("{$tmpname}.mp3");
		
		$img = false;
		
		// generate foreground color
		list($r, $g, $b) = $this->html2rgb($foreground);
		
		// process each wav individually
		for($wav = 1; $wav <= sizeof($wavs_to_process); $wav++) {
			
		  $filename = $wavs_to_process[$wav - 1];

		  /**
		   * Below as posted by "zvoneM" on
		   * http://forums.devshed.com/php-development-5/reading-16-bit-wav-file-318740.html
		   * as findValues() defined above
		   * Translated from Croation to English - July 11, 2011
		   */
		  $handle = fopen($filename, "r");
		  // wav file header retrieval
		  $heading[] = fread($handle, 4);
		  $heading[] = bin2hex(fread($handle, 4));
		  $heading[] = fread($handle, 4);
		  $heading[] = fread($handle, 4);
		  $heading[] = bin2hex(fread($handle, 4));
		  $heading[] = bin2hex(fread($handle, 2));
		  $heading[] = bin2hex(fread($handle, 2));
		  $heading[] = bin2hex(fread($handle, 4));
		  $heading[] = bin2hex(fread($handle, 4));
		  $heading[] = bin2hex(fread($handle, 2));
		  $heading[] = bin2hex(fread($handle, 2));
		  $heading[] = fread($handle, 4);
		  $heading[] = bin2hex(fread($handle, 4));
  
		  // wav bitrate 
		  $peek = hexdec(substr($heading[10], 0, 2));
		  $byte = $peek / 8;
  
		  // checking whether a mono or stereo wav
		  $channel = hexdec(substr($heading[6], 0, 2));
  
		  $ratio = ($channel == 2 ? 40 : 80);
  
		  // start putting together the initial canvas
		  // $data_size = (size_of_file - header_bytes_read) / skipped_bytes + 1
		  $data_size = floor((filesize($filename) - 44) / ($ratio + $byte) + 1);
		  $data_point = 0;
  
		  	// now that we have the data_size for a single channel (they both will be the same)
			  // we can initialize our image canvas
			  if (!$img) {
			    // create original image width based on amount of detail
			// each waveform to be processed with be $height high, but will be condensed
			// and resized later (if specified)
			    $img = imagecreatetruecolor($data_size / $this->detail, $height * sizeof($wavs_to_process));
    
			    // fill background of image
			    	if ($background == "") {
				      // transparent background specified
				      imagesavealpha($img, true);
				      $transparentColor = imagecolorallocatealpha($img, 0, 0, 0, 127);
				      imagefill($img, 0, 0, $transparentColor);
				    } else {
				      list($br, $bg, $bb) = $this->html2rgb($background);
				      imagefilledrectangle($img, 0, 0, (int) ($data_size / $this->detail), $height * sizeof($wavs_to_process), imagecolorallocate($img, $br, $bg, $bb));
				    }
			  }

		  while(!feof($handle) && $data_point < $data_size){
		    if ($data_point++ % $this->detail == 0) {
		      $bytes = array();
      
		      // get number of bytes depending on bitrate
		      for ($i = 0; $i < $byte; $i++)
		        $bytes[$i] = fgetc($handle);
      
		      switch($byte){
		        // get value for 8-bit wav
		        case 1:
		          $data = $this->findValues($bytes[0], $bytes[1]);
		          break;
		        // get value for 16-bit wav
		        case 2:
		          if(ord($bytes[1]) & 128)
		            $temp = 0;
		          else
		            $temp = 128;
		          $temp = chr((ord($bytes[1]) & 127) + $temp);
		          $data = floor($this->findValues($bytes[0], $temp) / 256);
		          break;
		      }
      
		      // skip bytes for memory optimization
		      fseek($handle, $ratio, SEEK_CUR);
      
		      // draw this data point
		      // relative value based on height of image being generated
		      // data values can range between 0 and 255
		      $v = (int) ($data / 255 * $height);
      
		      // don't print flat values on the canvas if not necessary
		      if (!($v / $height == 0.5 && !$draw_flat))
		        // draw the line on the image using the $v value and centering it vertically on the canvas
		        imageline(
		          $img,
		          // x1
		          (int) ($data_point / $this->detail),
		          // y1: height of the image minus $v as a percentage of the height for the wave amplitude
		          $height * $wav - $v,
		          // x2
		          (int) ($data_point / $this->detail),
		          // y2: same as y1, but from the bottom of the image
		          $height * $wav - ($height - $v),
		          imagecolorallocate($img, $r, $g, $b)
		        );         
      
		    } else {
		      // skip this one due to lack of detail
		      fseek($handle, $ratio + $byte, SEEK_CUR);
		    }
		  }
  
		  // close and cleanup
		  fclose($handle);

		  // delete the processed wav file
		  unlink($filename);
		}
		
		// create the image
	    //header("Content-Type: image/png");
  
	    // want it resized?
	    if ($width) {
	      // resample the image to the proportions defined in the form
	      $rimg = imagecreatetruecolor($width, $height);
	      // save alpha from original image
	      imagesavealpha($rimg, true);
	      imagealphablending($rimg, false);
	      // copy to resized
	      imagecopyresampled($rimg, $img, 0, 0, 0, 0, $width, $height, imagesx($img), imagesy($img));
	      imagepng($rimg, $save_path.basename($file, '.mp3').".png");
	      imagedestroy($rimg);
	    } else {
	      imagepng($img, $save_path.basename($file, '.mp3').".png");
	    }
    
	    imagedestroy($img);
		return true;
	  }
	}