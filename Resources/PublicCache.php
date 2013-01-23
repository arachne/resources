<?php

/**
 * This file is part of the Resources extenstion
 *
 * Copyright (c) Jáchym Toušek (enumag@gmail.com)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Arachne\Resources;

/**
 * @author Jáchym Toušek
 */
class PublicCache extends \Nette\Object
{

	/** @var float  probability that the clean() routine is started */
	public static $gcProbability = 0.001;

	/** @var int */
	public static $cleanTime = 604800;

	/** @var string */
	private $dir;

	/** @var string */
	private $url;

	public function __construct($dir, $url)
	{
		$this->dir = realpath($dir);
		if ($this->dir === FALSE) {
			throw new DirectoryNotFoundException("Directory '$dir' not found.");
		}
		$this->url = $url;

		if (mt_rand() / mt_getrandmax() < static::$gcProbability) {
			$this->clean(time() - static::$cleanTime);
		}
	}

	protected function getCacheFile($key, $extension)
	{
		return $this->dir . '/' . $key . ($extension ? '.' . $extension : '');
	}

	public function load($key, $modified, $extension = NULL)
	{
		$cacheFile = $this->getCacheFile($key, $extension);
		if (!file_exists($cacheFile) || filemtime($cacheFile) < $modified) {
			return FALSE;
		}
		return $cacheFile;
	}

	public function getUrl()
	{
		return $this->url;
	}

	public function save($key, $data, $extension = NULL)
	{
		$cacheFile = $this->getCacheFile($key, $extension);
		@mkdir(dirname($cacheFile), 0777, TRUE);
		file_put_contents($cacheFile, $data, LOCK_EX);
		return $cacheFile;
	}

	public function clean($time)
	{
		foreach (\Nette\Utils\Finder::find('*')->exclude('.*')->from($this->dir)->childFirst() as $file) {
			if ($file->isFile() && $file->getATime() < $time) {
				unlink((string) $file);
			}
		}
	}

}
