PHP MP3 PNG Waveform Generator / Codeigniter Library
===

### About

Generates PNG waveform images for a given MP3 file by converting it to WAV and processing amplitude points and plotting them on a GD canvas.

The codeigniter version was adapted from [Andrew Freiday](https://github.com/afreiday/php-waveform-png) for use as a codeigniter library.

### Requirements

- PHP 5+
- LAME MP3 Encoder (Windows build available from http://www.rarewares.org/mp3-lame-bundle.php)
- Web server (Apache, etc -- unless modified to run as a command line script)

### Usage/Installation

- Copy to your application/libraries location within your codeigniter project
- Ensure that the 'lame' command is accessible and executable from that directory (for Windows systems, place the downloaded .exe and .dll as linked above into that same directory, or modify the script)
- Ensure the directory your writing your images and temporary wav files to supports write persmissions, or specify an alternate temporary output directory that does - 
- Load the library and setup the class within your controller/model:

```php
$this->load->helper('file');
$this->load->library('waveform_generation');

$files = get_dir_file_info('music', TRUE);
$save_path = FCPATH."assets/waveforms/";
$width = 234;
$height = 35;
$foreground = "#7285AB";
$background = "";

foreach ($files as $file){
	$saved_image = $this->waveform_generation->process_track(FCPATH."music/".$file['name'], $save_path, $width, $height, $foreground, $background);
	// echo $saved_image; // if you want to echo 'true'
}
```

### Image generation options

The initial form, when the script is loaded in your browser, allows you to specify some basic parameters for how the image is generated and displayed. Other than the obvious dimensions and colours, there are two advanced options:

- "Draw flat-line" indicates whether or not to draw waveform point details that have a value of 0. Enabling this will allow you to see a flat, zero-level line where there is no audio playing. Otherwise it will be left blank
- The "stereo waveform" option allows you to generate a single image with both left and right audio channels separately
- The resulting image will be named with the original .mp3 filename, but with .png in place of the mp3 file extension

### Other notes

The detail of the waveform can be adjusted by modifying the $detail variable at the top of the class. The lower the number, the less data points are skipped, thus resulting in higher detail waveform.

The most time-consuming factor of the script is the LAME encoder, which first converts the uploaded MP3 into a 16 bit, mono, 8 KHz MP3 and finally to a WAV audio file. Unfortunately this doesn't seem to be possible to perform this in one step with the LAME encoder. If this script is to be modified to generate waveforms on the fly for the same MP3 multiple times, I recommend either storing a copy of the final WAV file or modifying the script to keep a dataset of the amplitude points so that the waveform can be redrawn much more quickly.

### License

Please see the LICENSE file.