<?php

ini_set("memory_limit", "-1");
set_time_limit(0);
include "EncodeException.php";

if (!function_exists("ftrim")) {
  function ftrim($data)
  {
    return trim($data);
  }
}

$oldDir = isset($argv[1]) ? $argv[1] : ".";
$newDir = isset($argv[2]) ? $argv[2] : ".";

$it = new RecursiveIteratorIterator(new RecursivedirectoryIterator($oldDir));

$enFiles = [
  "flv" => "mkv",
  "mpeg" => "mkv",
  "mpg" => "mkv",
  "avi" => "mkv",
  "wmv" => "mkv",
  "vob" => "mkv",
  "mp4" => "mkv",
  "3gp" => "mkv",
  "asf" => "mkv",
  "cdi" => "mkv",
  "mov" => "mkv",
  "ts" => "mkv",
];

foreach ($it as $file) {

  try {

    $extension = $file->getExtension();

    if (
      strpos($file->getPath(), "error") !== false or
      !array_key_exists($extension, $enFiles)
    ) {
      continue;
    }

    $newDirPath = str_replace($oldDir, $newDir, $file->getPath());

    if (!is_dir($newDirPath)) {
      mkdir($newDirPath, 0777, true);
    }

    $basename = $file->getBasename($extension);
    $lockPath = $newDirPath . "\\" . $basename . 'lock';
    new SplFileObject($lockPath, 'w');

    $oldFile = $file->getPathname();
    $newFile = $newDirPath . "\\" . $basename . $enFiles[$extension];

    system("ffmpeg -i \"{$oldFile}\" > \"{$lockPath}\" 2>&1");

    $lockFile = new SplFileObject($lockPath);
    $lockFile->setFlags(SplFileObject::DROP_NEW_LINE);

    $channels["stereo"] = 2;
    $channels["mono"] = 1;
    $channels["2 channels"] = 2;
    $channels["1 channels"] = 1;
    $videos["kb/s"] = "videoBitRate";
    $videos["fps"] = "frameRate";
    $videos["tbr"] = "frameRate";
    $audios["kb/s"] = "audioBitRate";
    $audios["Hz"] = "audioSampleRate";

    while (!$lockFile->eof()) {

      $line = $lockFile->fgets();

      if (strpos($line, "N/A") !== false) {
        throw new EncodeException("{$oldFile} N/A\n");
      }

      if (strpos($line, "Duration:") !== false) {

        $tmp = array_map("ftrim", explode(",", $line));
        $tmp2 = explode(" ", $tmp[0]);
        $tmp3 = explode(":", $tmp2[1]);

        $duration = ($tmp3[0] * 60 * 60) + ($tmp3[1] * 60) + (int) $tmp3[2];
      }

      if (strpos($line, "Video:") !== false) {

        $data = array_map("ftrim", explode(",", $line));

        foreach ($data as $v) {

          foreach ($videos as $search => $method) {

            if (strpos($v, $search) !== false) {
              $tmp = explode(" ", $v);
              $$method = $tmp[0];
            }
          }
        }
      }

      if (strpos($line, "Audio:") !== false) {

        $data = array_map("ftrim", explode(",", $line));

        foreach ($data as $v) {

          foreach ($audios as $search => $method) {

            if (strpos($v, $search) !== false) {
              $tmp = explode(" ", $v);
              $$method = $tmp[0];
            }
          }

          if (array_key_exists($v, $channels) && $channels[$v]) {
            $audioChannels = $channels[$v];
          }
        }
      }
    }

    if (
      empty($duration) or
      empty($frameRate) or
      empty($videoBitRate) or
      empty($audioBitRate) or
      empty($audioSampleRate) or
      empty($audioChannels)
    ) {
      throw new EncodeException("{$oldFile} is not info\n");
    }

    $cmd = "ffmpeg -i \"{$oldFile}\" -b:v {$videoBitRate}k -b:a {$audioBitRate}k -r {$frameRate} -ar {$audioSampleRate} -ac {$audioChannels} -y \"{$newFile}\" > \"{$lockPath}\" 2>&1";

    system($cmd);

    $lockFile = new SplFileObject($lockPath);
    $lockFile->setFlags(SplFileObject::DROP_NEW_LINE);

    while (!$lockFile->eof()) {

      $line = $lockFile->fgets();

      if (preg_match('/(Operation not permitted)|(Conversion failed!)/', $line, $matches)) {
        throw new EncodeException(print_r($matches, true));
      }
    }

    if (is_file($newFile) and filesize($newFile) > 10000) {
      unlink($oldFile);
    }

    unlink($lockPath);
  } catch (EncodeException $e) {

    if (is_file($newFile)) {
      unlink($newFile);
    }

    unlink($lockPath);

    $errorDir = $oldDir . '\error\\';

    if (!is_dir($errorDir)) {
      mkdir($errorDir, 0777, true);
    }

    rename($oldFile, $errorDir . $basename . $extension);
    echo $e;
  } catch (RuntimeException $e) {
    echo $e;
  } catch (Exception $e) {
    echo $e;
    exit;
  }
}