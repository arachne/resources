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
class Thumbnailer extends \Nette\Object
{

	/** @var \Arachne\Resources\PublicCache */
	private $cache;

	/** @var string */
	private $imagesDir;

	/** @var string */
	private $linkClass;

	/** @var string */
	private $imageClass;

	/** @var int */
	private $maxWidth;

	/** @var int */
	private $maxHeight;

	/**
	 * @param string $imagesDir
	 * @param \Arachne\Resources\PublicCache $cache
	 */
	public function __construct($imagesDir, \Arachne\Resources\PublicCache $cache)
	{
		$this->imagesDir = $imagesDir;
		$this->cache = $cache;
	}

	/**
	 * @param array $input
	 * @return \Nette\Utils\Html
	 */
	public function getLinkThumbnail($input)
	{
		list($file, $width, $height, $type) = $this->prepareDimensions($input);
		list($path, $modified) = $this->prepareVariables($file);

		$attrs = array('src' => $this->imageUrl($file, $modified, $type, $path, $width, $height));
		if ($this->imageClass) {
			$attrs['class'] = $this->imageClass;
		}
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
	public function getThumbnail($input)
	{
		list($file, $width, $height, $type) = $this->prepareDimensions($input);
		list($path, $modified) = $this->prepareVariables($file);

		$attrs = array('src' => $this->imageUrl($file, $modified, $type, $path, $width, $height));
		if ($this->imageClass) {
			$attrs['class'] = $this->imageClass;
		}
		foreach ($input as $value) {
			$attrs = array_merge($attrs, $value);
		}
		return Html::el('img', $attrs);
	}

	/**
	 * @param array $input
	 * @return string
	 */
	public function getThumbnailUrl($input)
	{
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
	private function prepareDimensions(&$input)
	{
		$file = $this->imagesDir . '/' . array_shift($input);
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
		// If not defined default maxWidth and maxHeight set size from origin file
		if ($this->maxWidth === NULL && $this->maxHeight === NULL) {
			list($this->maxWidth, $this->maxHeight) = getimagesize($file);
		}
		return array($file, $width, $height, $type);
	}

	/**
	 * @param string $file
	 * @return array
	 * @throws FileNotFoundException
	 */
	private function prepareVariables($file)
	{
		$modified = filemtime($file);
		$path = pathinfo($file, PATHINFO_DIRNAME);

		if (Strings::startsWith($path, $this->imagesDir . '/')) {
			$path = Strings::substring($path, Strings::length($this->imagesDir) + 1);
		} else {
			throw new FileNotFoundException("File '$file' not found.");
		}
		return array($path, $modified);
	}

	/**
	 * @param string $file
	 * @param string $modified
	 * @param string $type
	 * @param string $path
	 * @return string
	 */
	private function largeImageUrl($file, $modified, $type, $path)
	{
		$cacheKey = $path . '/' . pathinfo($file, PATHINFO_FILENAME) . '_' . $this->maxWidth . 'x' . $this->maxHeight;
		$cacheFile = $this->cache->load($cacheKey, $modified, image_type_to_extension($type, FALSE));

		if (!$cacheFile) {
			$image = Image::fromFile($file);

			$image->resize($this->maxWidth, $this->maxHeight, Image::FIT | Image::SHRINK_ONLY);
			$image->interlace();

			$cacheFile = $this->cache->save($cacheKey, $image->toString($type), image_type_to_extension($type, FALSE));
		}
		return $this->formatUrl($path, $cacheFile, $modified);
	}

	/**
	 * @param string $file
	 * @param string $modified
	 * @param string $type
	 * @param string $path
	 * @param int $width
	 * @param int $height
	 * @return string
	 */
	private function imageUrl($file, $modified, $type, $path, $width, $height)
	{
		$cacheKey = $path . '/' . pathinfo($file, PATHINFO_FILENAME) . '_' . $width . 'x' . $height;
		$cacheFile = $this->cache->load($cacheKey, $modified, image_type_to_extension($type, FALSE));

		if (!$cacheFile) {
			$image = Image::fromFile($file);

			if ($width !== NULL && $height !== NULL) {
				$image->resize($width, $height, Image::EXACT);
			} else {
				$image->resize($width, $height, Image::SHRINK_ONLY);
			}

			$image->interlace();

			$cacheFile = $this->cache->save($cacheKey, $image->toString($type), image_type_to_extension($type, FALSE));
		}
		return $this->formatUrl($path, $cacheFile, $modified);
	}

	/**
	 * @param string $path
	 * @param string $cacheFile
	 * @param string $modified
	 * @return string
	 */
	private function formatUrl($path, $cacheFile, $modified)
	{
		return $this->cache->getUrl() . '/' . str_replace('\\', '/', $path) . '/' . pathinfo($cacheFile, PATHINFO_BASENAME) . '?' . $modified;
	}

	/**
	 * @param string $maxWidth
	 * @return \Arachne\Resources\Thumbnailer
	 */
	public function setMaxWidth($maxWidth)
	{
		$this->maxWidth = $maxWidth;
		return $this;
	}

	/**
	 * @param string $maxHeight
	 * @return \Arachne\Resources\Thumbnailer
	 */
	public function setMaxHeight($maxHeight)
	{
		$this->maxHeight = $maxHeight;
		return $this;
	}

	/**
	 * @param string $linkClass
	 * @return \Arachne\Resources\Thumbnailer
	 */
	public function setLinkClass($linkClass)
	{
		$this->linkClass = $linkClass;
		return $this;
	}

	/**
	 * @param string $imageClass
	 * @return \Arachne\Resources\Thumbnailer
	 */
	public function setImageClass($imageClass)
	{
		$this->imageClass = $imageClass;
		return $this;
	}

}
