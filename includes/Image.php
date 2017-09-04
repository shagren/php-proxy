<?php
/**
 * Volcano Framework
 *
 * @category Volcano
 * @package Volcano_Tools
 * @author Ilya Gruzinov
 * @version $Revision$
 * @license http://vifm.volcanoideas.com/license/
 */


/**
 * Static class for soubroutines
 *
 * @category Volcano_Tools
 * @package Volcano_Tools_Image
 */

class Volcano_Tools_Image {


    /**
     * Determinate type of image by extension and load image
     * @return resource Image link
     */
    public static function loadImage($path) {
        list($width, $height, $type, $attr) = @getimagesize($path);
        if (!isset($width) || $width <= 0)
            return false;
        $extension = image_type_to_extension($type, false);
        switch ($extension) {
            case "png":
                return @imagecreatefrompng($path);
            case "jpg":
            case "jpeg":				
                return @imagecreatefromjpeg($path);
            case "gif":
                return @imagecreatefromgif($path);
        }
        return false;
    }

    /**
     * Determinate type of image by extension and save image
     * @return resource Image link
     */
    public static function saveImage($img, $path) {
        $extension = strtolower(strrchr($path,  "."));
        switch ($extension) {
            case ".png":
                return @imagepng($img, $path);
            case ".jpg":
            case ".jpeg":
                return @imagejpeg($img, $path);
            case ".gif":
                return @imagegif($img, $path);
        }
        return false;
    }

    /**
     * Resize image and save
     * @param string $src Path to source image
     * @param string $dst Path to output image
     * @param integer $width Width of output image
     * @param integer $height Height of output image
     * @param string $mode Transformation mode
     */
    public static function resizeImage($src, $dst, $width, $height, $mode = "in") {
  		if (empty($dst)) {
  		  $dst = $src;
  		}
        $srcImage = self::loadImage($src);
        if (!$srcImage)
            return false;
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);
        switch ($mode) {
            case "force":
                if ($srcWidth == $width && $srcHeight == $height) {
                    return @copy($src, $dst);
                }
                $dstImage = imagecreatetruecolor($width, $height);
                imagecopyresampled($dstImage, $srcImage,0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);
                break;
            case "width":
                $dstWidth = $width;
                $dstHeight = round($srcHeight / $srcWidth * $dstWidth);
                if ($srcWidth == $width && $srcHeight == $height) {
                    return @copy($src, $dst);
                }
                $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
                imagecopyresampled($dstImage, $srcImage,0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
                break;
            case "height":
                $dstHeight = $height;
                $dstWidth = round($srcWidth / $srcHeight * $dstHeight);
                if ($srcWidth == $width && $srcHeight == $height) {
                    return @copy($src, $dst);
                }
                $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
                imagecopyresampled($dstImage, $srcImage,0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
                break;
            case "out":
                $ratio_w = $srcWidth / $width;
                $ratio_h = $srcHeight / $height;
                $ratio = min($ratio_h, $ratio_w);
                $dstWidth = $width;
                $dstHeight = $height;
                $x = round(abs($srcWidth - $dstWidth * $ratio) / 2);
                $y = round(abs($srcHeight - $dstHeight * $ratio) / 2);
                if ($x == 0 && $y == 0 && $dstWidth == $srcWidth && $dstHeight == $srcHeight) {
                    return @copy($src, $dst);
                }
                $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
                imagecopyresampled($dstImage, $srcImage, 0, 0, $x, $y, $dstWidth, $dstHeight, ($srcWidth - 2 * $x), ($srcHeight - 2 * $y));
                break;
            case "in":
            default:
                $ratio_w = $srcWidth / $width;
                $ratio_h = $srcHeight / $height;
                $ratio = max($ratio_h, $ratio_w);
                $dstWidth = $srcWidth / $ratio;
                $dstHeight = $srcHeight / $ratio;
                if (round($srcWidth) == $width && round($srcHeight) == $height) {
                    return @copy($src, $dst);
                }

                $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
                imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
                break;
        }
        return self::saveImage($dstImage, $dst);

    }
}	 