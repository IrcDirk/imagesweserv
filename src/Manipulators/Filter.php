<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;

/**
 * @property string $filt
 * @property bool $hasAlpha
 */
class Filter extends BaseManipulator
{
    /**
     * Perform filter image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if ($this->filt === 'greyscale') {
            $image = $this->runGreyscaleFilter($image);
        }

        if ($this->filt === 'sepia') {
            $image = $this->runSepiaFilter($image);
        }

        return $image;
    }

    /**
     * Perform greyscale manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function runGreyscaleFilter(Image $image): Image
    {
        return $image->colourspace(Interpretation::B_W);
    }

    /**
     * Perform sepia manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function runSepiaFilter(Image $image): Image
    {
        $sepia = Image::newFromArray(
            [
                [0.3588, 0.7044, 0.1368],
                [0.2990, 0.5870, 0.1140],
                [0.2392, 0.4696, 0.0912]
            ]
        );

        if ($this->hasAlpha) {
            // Separate alpha channel
            $imageWithoutAlpha = $image->extract_band(0, ['n' => $image->bands - 1]);
            $alpha = $image->extract_band($image->bands - 1, ['n' => 1]);
            return $imageWithoutAlpha->recomb($sepia)->bandjoin($alpha);
        }

        return $image->recomb($sepia);
    }
}
