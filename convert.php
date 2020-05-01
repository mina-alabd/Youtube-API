<?php
require_once __DIR__ . '/vendor/autoload.php';

use YoutubeDl\YoutubeDl;

define("DOWNLOAD_FOLDER", "download/"); //Be sure the chmod the download folder
define("DOWNLOAD_MAX_LENGTH", 0); //max video duration (in seconds) to be able to download, set to 0 to disable

header("Content-Type: application/json");

if(isset($_GET["youtubelink"]) && !empty($_GET["youtubelink"]))
{
	$youtubelink = $_GET["youtubelink"];
	$format = $_GET['format'] ?? 'mp3';

	if(!in_array($format, ['mp3', 'mp4']))
		die(json_encode(array("error" => true, "message" => "Invalid format: only mp3 or mp4 are possible")));

	$success = preg_match('#(?<=v=)[a-zA-Z0-9-]+(?=&)|(?<=v\/)[^&\n]+|(?<=v=)[^&\n]+|(?<=youtu.be/)[^&\n]+#', $youtubelink, $matches);

	if(!$success)
		die(json_encode(array("error" => true, "message" => "No video specified")));

	$id = $matches[0];

	$exists = file_exists(DOWNLOAD_FOLDER.$id.".".$format);

	if(DOWNLOAD_MAX_LENGTH > 0 || $exists)
	{
		$dl = new YoutubeDl(['skip-download' => true]);
		$dl->setDownloadPath(DOWNLOAD_FOLDER);
	
		try	{
			$video = $dl->download($youtubelink);
	
			if($video->getDuration() > DOWNLOAD_MAX_LENGTH && DOWNLOAD_MAX_LENGTH > 0)
				throw new Exception("The duration of the video is {$video->getDuration()} seconds while max video length is ".DOWNLOAD_MAX_LENGTH." seconds.");
		}
		catch (Exception $ex)
		{
			die(json_encode(array("error" => true, "message" => $ex->getMessage())));
		}
	}

	if(!$exists)
	{
		if($format == 'mp3')
		{
			$options = array(
				'extract-audio' => true,
				'audio-format' => 'mp3',
				'audio-quality' => 0,
				'output' => '%(id)s.%(ext)s',
				//'ffmpeg-location' => '/usr/local/bin/ffmpeg'
			);
		}
		else
		{
			$options = array(
				'continue' => true,
				'format' => 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
				'output' => '%(id)s.%(ext)s'
			);
		}

		$dl = new YoutubeDl($options);
		$dl->setDownloadPath(DOWNLOAD_FOLDER);
	}

	try
	{
		$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/download/";
		if($exists)
			$file = $url.$id.".".$format;
		else 
		{
			$video = $dl->download($youtubelink);
			$file = $url.$video->getFilename();
		}

		echo json_encode(array(
			"error" => false,
			"youtube_id" => $video->getId(),
			"title" => $video->getTitle(),
			"alt_title" => $video->getAltTitle(),
			"duration" => $video->getDuration(),
			"file" => $file,
			"uploaded_at" => $video->getUploadDate()
		));
	}
	catch (Exception $e)
	{
		echo json_encode(array("error" => true, "message" => $e->getMessage()));
	}
}
else if(isset($_GET["delete"]) && !empty($_GET["delete"]))
{
	$id = $_GET["delete"];
	$format = $_GET["format"] ?? "mp3";

	if(unlink(DOWNLOAD_FOLDER.$id.".".$format))
		echo json_encode(array("error" => false, "message" => "File removed"));
	else
		echo json_encode(array("error" => true, "message" => "File not found"));
}
else
	echo json_encode(array("error" => true, "message" => "Invalid request"));
?>