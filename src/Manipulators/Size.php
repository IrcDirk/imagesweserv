<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $t
 * @property string $h
 * @property string $w
 * @property string $a
 * @property string $tmpFileName
 * @property string $page
 * @property string $or
 * @property string $trim
 */
class Size extends BaseManipulator
{
    /**
     * Maximum image size in pixels.
     *
     * @var int|null
     */
    protected $maxImageSize;

    /**
     * Create Size instance.
     *
     * @param int|null $maxImageSize Maximum image size in pixels.
     */
    public function __construct($maxImageSize = null)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Get the maximum image size.
     *
     * @return int|null Maximum image size in pixels.
     */
    public function getMaxImageSize()
    {
        return $this->maxImageSize;
    }

    /**
     * Set the maximum image size.
     *
     * @param int|null Maximum image size in pixels.
     */
    public function setMaxImageSize($maxImageSize)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Perform size image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $width = $this->w;
        $height = $this->h;
        $fit = $this->getFit();

        // Check if image size is greater then the maximum allowed image size after dimension is resolved
        $this->checkImageSize($image, $width, $height);

        $image = $this->doResize($image, $fit, $width, $height);

        return $image;
    }

    /**
     * Indicating if we should not enlarge the output if the input width
     * *and* height are already less than the required dimensions
     *
     * @param  string $fit The resolved fit.
     *
     * @return bool
     */
    public function withoutEnlargement(string $fit): bool
    {
        $keys = ['fit' => 0, 'squaredown' => 1];
        if (isset($keys[$fit])) {
            return true;
        }

        return false;
    }

    /**
     * Resolve fit.
     *
     * @return string The resolved fit.
     */
    public function getFit(): string
    {
        $validFitArr = ['fit' => 0, 'fitup' => 1, 'square' => 2, 'squaredown' => 3, 'absolute' => 4, 'letterbox' => 5];
        if (isset($validFitArr[$this->t])) {
            return $this->t;
        }

        if (substr($this->t, 0, 4) === 'crop') {
            return 'crop';
        }

        return 'fit';
    }

    /**
     * Check if image size is greater then the maximum allowed image size.
     *
     * @param  Image $image The source image.
     * @param  int $width The image width.
     * @param  int $height The image height.
     *
     * @throws ImageTooLargeException if the provided image is too large for processing.
     */
    public function checkImageSize(Image $image, int $width, int $height)
    {
        if ($width === 0 && $height === 0) {
            $width = $image->width;
            $height = $image->height;
        }
        if ($width !== 0) {
            $width = $height * ($image->width / $image->height);
        }
        if ($height !== 0) {
            $height = $width / ($image->width / $image->height);
        }

        if ($this->maxImageSize) {
            $imageSize = $width * $height;

            if ($imageSize > $this->maxImageSize) {
                throw new ImageTooLargeException();
            }
        }
    }

    /**
     * Perform resize image manipulation.
     *
     * @param  Image $image The source image.
     * @param  string $fit The fit.
     * @param  int $width The width.
     * @param  int $height The height.
     *
     * @return Image The manipulated image.
     */
    public function doResize(Image $image, string $fit, int $width, int $height): Image
    {
        // Default settings
        $thumbnailOptions = [
            'auto_rotate' => true,
            'linear' => false,
        ];

        $inputWidth = $image->width;
        $inputHeight = $image->height;

        $orientation = $this->or;
        $exifOrientation = Utils::resolveExifOrientation($image);
        $userRotate = $orientation === '90' || $orientation === '270';
        $autoRotate = $exifOrientation === 90 || $exifOrientation === 270;

        if ($userRotate || $autoRotate) {
            // Swap input output width and height when rotating by 90 or 270 degrees
            // Or when the image has exif orientation
            list($inputWidth, $inputHeight) = [$inputHeight, $inputWidth];
        }

        $cropPosition = $this->a;

        // Scaling calculations
        $targetResizeWidth = $width;
        $targetResizeHeight = $height;

        // Is smart crop?
        if ($cropPosition === 'entropy' || $cropPosition === 'attention') {
            // Set crop option
            $thumbnailOptions['crop'] = $cropPosition;
        } elseif ($width > 0 && $height > 0) {
            // Fixed width and height
            $xFactor = (float)($inputWidth / $width);
            $yFactor = (float)($inputHeight / $height);
            switch ($fit) {
                case 'square':
                case 'squaredown':
                case 'crop':
                    if ($xFactor < $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                    }
                    break;
                case 'letterbox':
                case 'fit':
                case 'fitup':
                    if ($xFactor > $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                    }
                    break;
            }
        } elseif ($width > 0) {
            // Fixed width
            $xFactor = (float)($inputWidth / $width);
            if ($fit === 'absolute') {
                $targetResizeHeight = $inputHeight;
            } else {
                // Auto height
                $yFactor = $xFactor;
                $targetResizeHeight = (int)round((float)($inputHeight / $yFactor));
            }
        } elseif ($height > 0) {
            // Fixed height
            $yFactor = (float)($inputHeight / $height);
            if ($fit === 'absolute') {
                $targetResizeWidth = $inputWidth;
            } else {
                // Auto width
                $xFactor = $yFactor;
                $targetResizeWidth = (int)round((float)($inputWidth / $xFactor));
            }
        } else {
            // No resize required; return original image
            return $image;
        }

        if ($userRotate) {
            // Swap target output width and height when rotating by 90 or 270 degrees
            // Note: don't check here for EXIF orientation because that's handled
            // in the thumbnail operator.
            list($targetResizeWidth, $targetResizeHeight) = [$targetResizeHeight, $targetResizeWidth];
        }

        // Assign settings
        $thumbnailOptions['height'] = $targetResizeHeight;
        $thumbnailOptions['size'] = $this->withoutEnlargement($fit) ? 'down' : 'both';

        if ($this->trim) {
            // Trimming occurs before any resize operation, but we can't thumbnail
            // on an existing image due to shrink-on-load. To achieve this:
            // write the image to a buffer and do the thumbnail operator on that buffer.
            // (Feels a little hackish but there is not an alternative at this moment..)
            // TODO Thumbnail on an existing image?
            return $image->thumbnail_buffer($image->writeToBuffer('.png'), $targetResizeWidth, $thumbnailOptions);
        } else {
            // TODO Pass $this->page to thumbnail a single page?
            // TODO Ignore aspect ratio?
            // Mocking on static methods is not possible, so we don't use `Image::`.
            return $image->thumbnail($this->tmpFileName, $targetResizeWidth, $thumbnailOptions);
        }
    }
}
