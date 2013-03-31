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
class ResourcesLoader extends \Nette\Object
{

	/** @var array */
	protected $packages;

	/** @var array */
	protected $mapping;

	/** @var bool */
	protected $concatenate;

	/** @var Compiler */
	protected $compiler;

	/**
	 * @param array $packages
	 * @param array $mapping
	 * @param Compiler $compiler
	 */
	public function __construct(array $packages, array $mapping, Compiler $compiler)
	{
		$this->packages = $packages;
		$this->mapping = $mapping;
		$this->compiler = $compiler;
	}

	/**
	 * Finds packages names for given action.
	 * @param string $action
	 * @return array
	 */
	protected function getPackagesNames($action)
	{
		if ($action[0] === ':') {
			$action = substr($action, 1);
		}
		do {
			if (isset($this->mapping[$action])) {
				$packages = (array) $this->mapping[$action];
				break;
			}
			$pos = strrpos($action, ':');
			if ($pos === FALSE) {
				$packages = isset($this->mapping['*']) ? (array) $this->mapping['*'] : array();
				break;
			}
			$action = substr($action, 0, $pos);
		} while (TRUE);

		while (isset($packages['_extends'])) {
			$extends = $packages['_extends'];
			unset($packages['_extends']);
			if (!isset($this->mapping[$extends])) {
				throw new InvalidStateException("Mapping '$action' extends undefined mapping '$extends'.");
			}
			$packages = array_merge((array) $this->mapping[$extends], $packages);
		}
		return $packages;
	}

	/**
	 * Creates a tag from URL.
	 * @param string $url
	 * @param string $type
	 * @return \Nette\Utils\Html
	 */
	protected function createTag($url, $type)
	{
		if ($type === 'js') {
			return \Nette\Utils\Html::el('script', array('src' => $url));
		} else {
			return \Nette\Utils\Html::el('link', array(
				'rel' => 'stylesheet',
				'href' => $url,
			));
		}
	}

	/**
	 * @param string $action
	 * @return array
	 */
	protected function getFiles($action)
	{
		$packages = $this->getPackagesNames($action);

		// Get files form packages
		$files = array( 
			'css' => array(),
			'js' => array(),
		);
		foreach ($packages as $name) {
			if (!isset($this->packages[$name])) {
				throw new InvalidStateException("Package '$name' not found.");
			}
			foreach ($this->packages[$name] as $value) {
				$files[$this->compiler->detectType($value)][] = $value;
			}
		}
		return $files;
	}

	/**
	 * @param array $files
	 * @param string $type
	 */
	protected function getTypeTags(array $files, $type)
	{
		$tags = '';
		$compile = array();
		foreach ($files as $file) {
			if (\Nette\Utils\Strings::startsWith($file, '/')
				|| \Nette\Utils\Strings::startsWith($file, 'http://')
				|| \Nette\Utils\Strings::startsWith($file, 'https://')) {
				$tags .= $this->createTag($file, $type);
			} else {
				$compile[] = $file;
			}
		}
		if (!empty($compile)) {
			if ($this->concatenate) {
				$tags .= $this->createTag($this->compiler->getCompiledFile($compile, $type), $type);
			} else {
				foreach ($compile as $file) {
					$tags .= $this->createTag($this->compiler->getCompiledFile(array($file), $type), $type);
				}
			}
		}
		return $tags;
	}

	/**
	 * Returns HTML tags of resources for given action.
	 * @param string $action
	 * @param string $type
	 */
	public function getTags($action, $type = NULL)
	{
		$files = $this->getFiles($action);

		$tags = '';
		if ($type) {
			$tags .= $this->getTypeTags($files[$type], $type);
		} else {
			$tags .= $this->getTypeTags($files['css'], 'css');
			$tags .= $this->getTypeTags($files['js'], 'js');
		}

		return $tags;
	}

	/**
	 * @param bool $concatenate
	 * @return ResourcesLoader
	 */
	public function setConcatenate($concatenate)
	{
		$this->concatenate = $concatenate;
		return $this;
	}

}
