<?php

namespace Arachne\Resources;

use Nette\Utils\Html,
    Nette\Utils\Strings,
    Nette\Image;

/**
 * Thumbnailer.
 *
 * @version    0.1
 * @package    Resources extenstion
 * 
 * @author Jáchym Toušek <enumag@gmail.com>
 * @author Dusan Hudak <admin@dusan-hudak.com>
 * @copyright (c) Jáchym Toušek (enumag@gmail.com)
 */
class Thumbnailer extends \Nette\Object {

    /** @var \Arachne\Resources\PublicCache */
    private $cache;

    /** @var string */
    private $wwwDir;

    /** @var string */
    private $linkClass;

    /** @var int */
    private $originalMaxWidth;

    /** @var int */
    private $originalMaxHeight;

    /**
     * @param string $wwwDir
     * @param int $originalMaxWidth
     * @param int $originalMaxHeight
     * @param string $linkClass
     * @param \Arachne\Resources\PublicCache $cache
     */
    public function __construct($wwwDir, $originalMaxWidth, $originalMaxHeight, $linkClass, \Arachne\Resources\PublicCache $cache) {
        $this->wwwDir = $wwwDir;
        $this->originalMaxWidth = $originalMaxWidth;
        $this->originalMaxHeight = $originalMaxHeight;
        $this->linkClass = $linkClass;
        $this->cache = $cache;
    }

    /**
     * @param array $input
     * @return \Nette\Utils\Html
     */
    public function renderLinkThumbnail($input) {
        list($file, $width, $height, $type) = $this->prepareDimensions($input);
        list($path, $modified) = $this->prepareVariables($file);

        $attrs = array('src' => $this->imageUrl($file, $modified, $type, $path, $width, $height));
        foreach ($input as $value) {
            $attrs = array_merge($attrs, $value);
        }
        return Html::el('a', array(
                    'class' => $this->linkClass,
                    'href' => $this->largeImageUrl($file, $modified, $type, $path),
                ))->setHtml(Html::el('img', $attrs));
    }

    /**
     * @param array $input
     * @return \Nette\Utils\Html
     */
    public function renderThumbnail($input) {
        list($file, $width, $height, $type) = $this->prepareDimensions($input);
        list($path, $modified) = $this->prepareVariables($file);

        $attrs = array('src' => $this->imageUrl($file, $modified, $type, $path, $width, $height));
        foreach ($input as $value) {
            $attrs = array_merge($attrs, $value);
        }
        return Html::el('img', $attrs);
    }

    /**
     * @param array $input
     * @return string
     */
    public function ThumbnailLink($input) {
        list($file, $width, $height, $type) = $this->prepareDimensions($input);
        list($path, $modified) = $this->prepareVariables($file);

        $link = $this->imageUrl($file, $modified, $type, $path, $width, $height);
        return $link;
    }

    /**
     * @param array $input
     * @return array
     * @throws FileNotFoundException
     */
    private function prepareDimensions(&$input) {
        $file = $this->wwwDir . '/' . array_shift($input);
        if (!is_file($file) || !is_readable($file)) {
            throw new FileNotFoundException($file . ' not found');
        }

        $width = array_shift($input);
        $height = array_shift($input);
        // If not defined width and height from macro set size from origin file
        if ($width === NULL && $height === NULL) {
            list($width, $height, $type) = getimagesize($file);
        } else {
            list(,, $type) = getimagesize($file);
        }
        // If not defined default originalMaxWidth and originalMaxHeight set size from origin file
        if ($this->originalMaxWidth === NULL && $this->originalMaxHeight === NULL) {
            list($this->originalMaxWidth, $this->originalMaxHeight) = getimagesize($file);
        }
        return array($file, $width, $height, $type);
    }

    /**
     * @param string $file
     * @return array
     * @throws FileNotFoundException
     */
    private function prepareVariables($file) {
        $modified = filemtime($file);
        $path = pathinfo($file, PATHINFO_DIRNAME);

        if (Strings::startsWith($path, $this->wwwDir . '/')) {
            $path = Strings::substring($path, Strings::length($this->wwwDir) + 1);
        } else {
            throw new FileNotFoundException($file . ' not found');
        }
        return array($path, $modified);
    }

    /**
     * @param string $file
     * @param string $modified
     * @param string $type
     * @param string $path
     * @return string
     * @throws FileNotFoundException
     */
    private function largeImageUrl($file, $modified, $type, $path) {
        $cacheKey = $path . '/' . pathinfo($file, PATHINFO_FILENAME) . '_' . $this->originalMaxWidth . 'x' . $this->originalMaxHeight;
        $cacheFile = $this->cache->load($cacheKey, $modified, image_type_to_extension($type, FALSE));

        if (!$cacheFile) {
            try {
                $image = Image::fromFile($file);
            } catch (\Exception $e) {
                throw new FileNotFoundException($file . ' not found');
            }

            $image->resize($this->originalMaxWidth, $this->originalMaxHeight, Image::FIT | Image::SHRINK_ONLY);
            $image->interlace();

            $cacheFile = $this->cache->save($cacheKey, $image->toString($type), image_type_to_extension($type, FALSE));
        }
        return $this->cache->getUrl() . '/' . str_replace('\\', '/', $path) . '/' . pathinfo($cacheFile, PATHINFO_BASENAME) . '?' . $modified;
    }

    /**
     * @param string $file
     * @param string $modified
     * @param string $type
     * @param string $path
     * @param int $width
     * @param int $height
     * @return string
     * @throws FileNotFoundException
     */
    private function imageUrl($file, $modified, $type, $path, $width, $height) {
        $cacheKey = $path . '/' . pathinfo($file, PATHINFO_FILENAME) . '_' . $width . 'x' . $height;
        $cacheFile = $this->cache->load($cacheKey, $modified, image_type_to_extension($type, FALSE));
        if (!$cacheFile) {
            try {
                $image = Image::fromFile($file);
            } catch (\Exception $e) {
                throw new FileNotFoundException($file . ' not found');
            }

            if ($width !== NULL && $height !== NULL) {
                $image->resize($width, $height, Image::EXACT);
            } else {
                $image->resize($width, $height, Image::SHRINK_ONLY);
            }

            $image->interlace();

            $cacheFile = $this->cache->save($cacheKey, $image->toString($type), image_type_to_extension($type, FALSE));
        }
        return $this->cache->getUrl() . '/' . str_replace('\\', '/', $path) . '/' . pathinfo($cacheFile, PATHINFO_BASENAME) . '?' . $modified;
    }

}

/**
 * The exception that is thrown when file cannot be found.
 */
class FileNotFoundException extends \RuntimeException {
    
}