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
class Compiler extends \Nette\Object
{

	/** @var string */
	protected $resourcesDirectory;

	/** @var IFilter[] */
	protected $cssFilters;

	/** @var IFilter[] */
	protected $jsFilters;

	/** @var PublicCache */
	protected $public;

	/** @var \Nette\Caching\Cache */
	protected $cache;

	/** @var \Nette\DI\Container */
	protected $container;

	/**
	 * @param string $resourcesDirectory
	 * @param PublicCache $cache
	 * @param \Nette\Caching\IStorage $storage
	 * @param \Nette\DI\Container $container
	 */
	public function __construct($resourcesDirectory, PublicCache $public, \Nette\Caching\IStorage $storage, \Nette\DI\Container $container)
	{
		$this->resourcesDirectory = $resourcesDirectory;
		$this->public = $public;
		$this->cache = new \Nette\Caching\Cache($storage, 'Arachne.Resources');
		$this->container = $container;
	}

	/**
	 * @param array $files
	 * @param string $type
	 * @return string
	 */
	public function getCompiledFile(array $files, $type)
	{
		$includedFiles = $this->cache->load($files);
		if ($includedFiles) {
			$modified = $this->getLastModification($includedFiles);
			$cacheKey = $this->getCacheKey($files);
			$cacheFile = $this->public->load($cacheKey, $modified, $type);
			if ($cacheFile) {
				return $this->public->getUrl() . '/' . pathinfo($cacheFile, PATHINFO_BASENAME) . '?' . $modified;
			}
		}

		$output = '';
		$includedFiles = array();
		$filters = &$this->{$type . 'Filters'};
		foreach ($files as $file) {
			$file = $this->resourcesDirectory . '/' . $file;
			if (!is_readable($file)) {
				throw new FileNotFoundException("File '$file' not found.");
			}
			$input = file_get_contents($file);
			$extension = pathinfo($file, PATHINFO_EXTENSION);
			if (isset($filters[$extension])) {
				if (!is_array($filters[$extension])) {
					$filters[$extension] = (array) $filters[$extension];
				}
				foreach ($filters[$extension] as &$filter) {
					if (is_string($filter)) {
						$filter = $this->container->{$filter};
					}
					$input = $filter($input, $file);
					$includedFiles = array_merge($includedFiles, $filter->getIncludedFiles());
				}
			} elseif ($extension == 'css' || $extension == 'js') {
				$includedFiles[] = $file;
			} else {
				throw new InvalidStateException("Unknown extension '$extension'.");
			}
			$output .= $input;
		}

		$cacheKey = $this->getCacheKey($files);
		$this->cache->save($files, $includedFiles, array(\Nette\Caching\Cache::EXPIRE => '+ 1 month'));
		$cacheFile = $this->public->save($cacheKey, $output, $type);

		return $this->public->getUrl() . '/' . pathinfo($cacheFile, PATHINFO_BASENAME) . '?' . $this->getLastModification($includedFiles);
	}

	/**
	 * @param string[] $files
	 */
	protected function getCacheKey(array $files)
	{
		$cacheKey = md5(serialize($files));
		if (count($files) === 1) {
			$cacheKey = pathinfo(reset($files), PATHINFO_FILENAME) . '_' . $cacheKey;
		}
		return $cacheKey;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	protected function getExtension($url)
	{
		$pos = strpos($url, '?');
		if ($pos !== FALSE) {
			$url = substr($url, 0, $pos);
		}
		$dot = strrpos($url, '.');
		if ($dot !== FALSE) {
			return substr($url, $dot + 1);
		}
	}

	/**
	 * @param string[] $files
	 * @return string
	 */
	protected function getLastModification(array $files)
	{
		$modified = 0;
		foreach ($files as $file) {
			if (is_file($file)) {
				$modified = max($modified, filemtime($file));
			} else {
				$modified = time();
			}
		}
		return $modified;
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function detectType($url)
	{
		$extension = $this->getExtension($url);
		if ($extension === 'css' || $extension === 'js') {
			return $extension;
		} elseif (array_key_exists($extension, $this->cssFilters)) {
			return 'css';
		} elseif (array_key_exists($extension, $this->jsFilters)) {
			return 'js';
		}
		throw new InvalidStateException("Could not detect type of '$url'.");
	}

	/**
	 * @param array $cssFilters
	 * @return Compiler
	 */
	public function setCssFilters(array $cssFilters = NULL)
	{
		$this->cssFilters = $cssFilters;
		return $this;
	}

	/**
	 * @param array $jsFilters
	 * @return Compiler
	 */
	public function setJsFilters(array $jsFilters = NULL)
	{
		$this->jsFilters = $jsFilters;
		return $this;
	}

}
