<?php

/**
 * This file is part of the Resources extenstion
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Resources;

use Nette\Utils\Html;
use Nette\Utils\Image;
use Nette\Utils\Strings;

/**
 * @author Jáchym Toušek <enumag@gmail.com>
 * @author Dusan Hudak <admin@dusan-hudak.com>
 */
class Thumbnailer extends \Nette\Object
{
	/** Tag of array keys for attributes in macro  */
	const IMAGE_ATTRIBUTES = 'imageAttributes';
	const LINK_ATTRIBUTES = 'linkAttributes';
	const FLAGS = 'flags';

	/** @var \Arachne\Resources\PublicCache */
	private $cache;

	/** @var string */
	private $imagesDirectory;

	/** @var array */
	private $linkAttributes;

	/** @var array */
	private $imageAttributes;

	/** @var int */
	private $maxWidth;

	/** @var int */
	private $maxHeight;

	/** @var int */
	private $flags = Image::EXACT;

	/**
	 * @param string $imagesDirectory
	 * @param \Arachne\Resources\PublicCache $cache
	 */
	public function __construct($imagesDirectory, \Arachne\Resources\PublicCache $cache)
	{
		$this->imagesDirectory = $imagesDirectory;
		$this->cache = $cache;
	}

	/**
	 * @param array $input
	 * @return \Nette\Utils\Html
	 */
	public function getLinkThumbnail($input)
	{
		try {
			list($file, $modified, $path, $width, $height, $flags, $imageMacroAttributes, $linkMacroAttributes) = $this->prepareInput($input);
		} catch (FileNotFoundException $e) {
			return Html::el('span', [ 'class' => 'no-image' ]);
		}

		// Set image attributes
		$imageAttributes = $this->getImageAttributes($file, $modified, $path, $width, $height, $flags);
		$imageAttributes = array_merge($imageAttributes, $this->imageAttributes, $imageMacroAttributes);

		// Set link attributes
		$linkAttributes = array('href' => $this->largeImageUrl($file, $modified, $path));
		$linkAttributes = array_merge($linkAttributes, $this->linkAttributes, $linkMacroAttributes);

		return Html::el('a', $linkAttributes)->setHtml(Html::el('img', $imageAttributes));
	}

	/**
	 * @param array $input
	 * @return \Nette\Utils\Html
	 */
	public function getThumbnail($input)
	{
		try {
			list($file, $modified, $path, $width, $height, $flags, $imageMacroAttributes) = $this->prepareInput($input);
		} catch (FileNotFoundException $e) {
			return Html::el('span', [ 'class' => 'no-image' ]);
		}

		// Set image attributes
		$imageAttributes = $this->getImageAttributes($file, $modified, $path, $width, $height, $flags);
		$imageAttributes = array_merge($imageAttributes, $this->imageAttributes, $imageMacroAttributes);

		return Html::el('img', $imageAttributes);
	}

	/**
	 * @param array $input
	 * @return string
	 */
	public function getThumbnailUrl($input)
	{
		try {
			list($file, $modified, $path, $width, $height, $flags) = $this->prepareInput($input);
		} catch (FileNotFoundException $e) {
			return;
		}

		// Set image attributes
		$imageAttributes = $this->getImageAttributes($file, $modified, $path, $width, $height, $flags);
		return $imageAttributes['src'];
	}

	/**
	 * @param array $input
	 * @return array
	 * @throws FileNotFoundException
	 */
	private function prepareInput(&$input)
	{
		$file = $this->imagesDirectory . '/' . array_shift($input);
		if (!is_file($file) || !is_readable($file)) {
			throw new FileNotFoundException("File '$file' not found.");
		}

		$modified = filemtime($file);
		$path = pathinfo($file, PATHINFO_DIRNAME);

		if (Strings::startsWith($path, $this->imagesDirectory . '/')) {
			$path = Strings::substring($path, Strings::length($this->imagesDirectory) + 1);
		} else {
			throw new FileNotFoundException("File '$file' not found.");
		}

		$width = array_shift($input);
		$height = array_shift($input);

		// Get flags from macro
		if (isset($input[self::FLAGS])) {
			$flags = $input[self::FLAGS];
		} else {
			$flags = $this->flags;
		}

		// Get image attributes from macro
		$imageMacroAttributes = array();
		if (isset($input[self::IMAGE_ATTRIBUTES])) {
			$imageMacroAttributes = $input[self::IMAGE_ATTRIBUTES];
		}

		// Get link attributes from macro
		$linkMacroAttributes = array();
		if (isset($input[self::LINK_ATTRIBUTES])) {
			$linkMacroAttributes = $input[self::LINK_ATTRIBUTES];
		}

		// If not defined default maxWidth and maxHeight set size from origin file
		if ($this->maxWidth === NULL && $this->maxHeight === NULL) {
			list($this->maxWidth, $this->maxHeight) = getimagesize($file);
		}

		return array($file, $modified, $path, $width, $height, $flags, $imageMacroAttributes, $linkMacroAttributes);
	}

	/**
	 * @param string $file
	 * @param int $modified
	 * @param string $path
	 * @return string
	 */
	private function largeImageUrl($file, $modified, $path)
	{
		list(,, $type) = getimagesize($file);

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
	 * @param int $modified
	 * @param string $path
	 * @param int $width
	 * @param int $height
	 * @param int $flags
	 * @return array
	 */
	private function getImageAttributes($file, $modified, $path, $width, $height, $flags)
	{
		list($srcWidth, $srcHeight, $type) = getimagesize($file);

		if ($width === NULL && $height === NULL) {
			$width = $imgWidth = $srcWidth;
			$height = $imgHeight = $srcHeight;
		} else if ($flags === Image::EXACT && $width !== NULL && $height !== NULL) {
			$imgWidth = $width;
			$imgHeight = $height;
		} else {
			list($calculateWidth, $calculateHeight) = Image::calculateSize($srcWidth, $srcHeight, $width, $height, $flags);
			$width = $imgWidth = $calculateWidth;
			$height = $imgHeight = $calculateHeight;
		}

		$cacheKey = $path . '/' . pathinfo($file, PATHINFO_FILENAME) . '_' . $imgWidth . 'x' . $imgHeight . '-' . $flags;
		$cacheFile = $this->cache->load($cacheKey, $modified, image_type_to_extension($type, FALSE));

		if (!$cacheFile) {
			$image = Image::fromFile($file);
			$image->resize($width, $height, $flags);
			$image->interlace();
			$cacheFile = $this->cache->save($cacheKey, $image->toString($type), image_type_to_extension($type, FALSE));
		}

		$formatUrl = $this->formatUrl($path, $cacheFile, $modified);
		$imageAttributes = array(
			'src' => $formatUrl,
			'width' => $imgWidth,
			'height' => $imgHeight,
		);
		return $imageAttributes;
	}

	/**
	 * @param string $path
	 * @param string $cacheFile
	 * @param int $modified
	 * @return string
	 */
	private function formatUrl($path, $cacheFile, $modified)
	{
		$path = str_replace('\\', '/', $path);
		return $this->cache->getUrl() . (substr($path, 0, 1) === '/' ? '' : '/') . $path . '/' . pathinfo($cacheFile, PATHINFO_BASENAME) . '?' . $modified;
	}

	/**
	 * @param int $maxWidth
	 */
	public function setMaxWidth($maxWidth)
	{
		$this->maxWidth = $maxWidth;
	}

	/**
	 * @param int $maxHeight
	 */
	public function setMaxHeight($maxHeight)
	{
		$this->maxHeight = $maxHeight;
	}

	/**
	 * @param array $linkAttributes
	 */
	public function setLinkAttributes($linkAttributes)
	{
		$this->linkAttributes = $linkAttributes;
	}

	/**
	 * @param array $imageAttributes
	 */
	public function setImageAttributes($imageAttributes)
	{
		$this->imageAttributes = $imageAttributes;
	}

	/**
	 * @param int $flags
	 */
	public function setFlags($flags)
	{
		if ($flags != NULL) {
			$this->flags = $flags;
		}
	}

}
